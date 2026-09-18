<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Probe;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Kojirock5260\OpenApiProbe\Spec\MatchedOperation;
use Kojirock5260\OpenApiProbe\Validation\ResponseCheck;
use Symfony\Component\HttpFoundation\Response;

/**
 * ケースをアプリケーションにプロセス内で送り、応答を spec と突き合わせる。
 *
 * 各リクエストはトランザクションの中で実行し、終わったら巻き戻す。
 */
final readonly class Prober
{
    private const int EXCERPT_LENGTH = 160;

    /**
     * @param  Kernel  $kernel  HTTP カーネル
     * @param  DatabaseManager  $db  トランザクションの巻き戻しに使う
     * @param  ResponseCheck  $responses  応答の照合
     */
    public function __construct(
        private Kernel $kernel,
        private DatabaseManager $db,
        private ResponseCheck $responses = new ResponseCheck,
    ) {}

    /**
     * ケースを 1 件送り、見つかった問題を返す。
     *
     * @param  Route  $route  対象のルート
     * @param  MatchedOperation  $operation  spec 上の操作
     * @param  ProbeCase  $case  送る内容
     * @param  array<string, string>  $headers  全リクエストに付けるヘッダー
     * @return list<Finding>
     */
    public function probe(Route $route, MatchedOperation $operation, ProbeCase $case, array $headers = []): array
    {
        $request = $this->request($route, $operation, $case, $headers);

        $this->db->beginTransaction();

        try {
            $response = $this->kernel->handle($request);
            $this->kernel->terminate($request, $response);
        } finally {
            $this->db->rollBack();
        }

        return $this->findings($operation, $case, $response);
    }

    /**
     * ケースからリクエストを組み立てる。
     *
     * @param  array<string, string>  $headers  全リクエストに付けるヘッダー
     */
    private function request(Route $route, MatchedOperation $operation, ProbeCase $case, array $headers): Request
    {
        $uri = '/'.ltrim($route->uri(), '/');

        foreach ($case->pathParameters as $specName => $value) {
            $name = preg_quote($operation->routeParameterName($specName), '/');
            $uri = preg_replace('/\{'.$name.'\??\}/', rawurlencode((string) $value), $uri) ?? $uri;
        }

        $uri = preg_replace('#/\{[^}]*\?\}#', '', $uri) ?? $uri;

        if ($case->query !== []) {
            $uri .= '?'.http_build_query($case->query);
        }

        $server = ['HTTP_ACCEPT' => 'application/json'];

        foreach ($headers + $case->headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $content = null;

        if ($case->body !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($case->body, JSON_THROW_ON_ERROR);
        }

        return Request::create($uri, strtoupper($operation->method), [], [], [], $server, $content);
    }

    /**
     * 応答を spec と突き合わせ、問題を列挙する。
     *
     * @return list<Finding>
     */
    private function findings(MatchedOperation $operation, ProbeCase $case, Response $response): array
    {
        $status = $response->getStatusCode();
        $name = $operation->describe();
        $findings = [];

        if ($status >= 500) {
            $findings[] = new Finding(Finding::SERVER_ERROR, $name, $case->label, $status, [$this->excerpt($response)]);
        }

        if ($operation->responseFor($status) === null) {
            $findings[] = new Finding(Finding::UNDOCUMENTED_STATUS, $name, $case->label, $status, [$this->excerpt($response)]);
        } else {
            $errors = $this->responses->errors($operation, $response);

            if ($errors !== []) {
                $findings[] = new Finding(Finding::RESPONSE_MISMATCH, $name, $case->label, $status, $errors);
            }
        }

        if (! $case->conforming && $status >= 200 && $status < 300) {
            $findings[] = new Finding(Finding::VALIDATION_BYPASS, $name, $case->label, $status);
        }

        if ($case->conforming && in_array($status, [400, 422], true)) {
            $findings[] = new Finding(Finding::SPEC_REJECTED, $name, $case->label, $status, [$this->excerpt($response)]);
        }

        return $findings;
    }

    /**
     * 応答本文の先頭を返す。
     */
    private function excerpt(Response $response): string
    {
        $content = (string) $response->getContent();
        $flat = trim((string) preg_replace('/\s+/', ' ', $content));

        return strlen($flat) > self::EXCERPT_LENGTH ? substr($flat, 0, self::EXCERPT_LENGTH).'…' : $flat;
    }
}
