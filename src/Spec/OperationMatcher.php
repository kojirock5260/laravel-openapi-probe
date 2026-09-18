<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Spec;

use cebe\openapi\spec\OpenApi;
use Illuminate\Routing\Route;

/**
 * Laravel のルートを、ドキュメント上の操作に対応づける。
 *
 * パスパラメータの名前は無視し、位置で照合する。`members/{member}` は
 * `/members/{memberId}` に一致する。
 */
final readonly class OperationMatcher
{
    /**
     * @param  OpenApi  $document  ドキュメント
     * @param  string  $basePath  ルート URI から取り除く接頭辞
     */
    public function __construct(
        private OpenApi $document,
        private string $basePath = '',
    ) {}

    /**
     * ルートとメソッドに対応する操作を返す。無ければ null。
     */
    public function match(Route $route, string $method): ?MatchedOperation
    {
        $method = strtolower($method);
        $target = $this->shape($this->stripBasePath($route->uri()));

        foreach ($this->document->paths->getPaths() as $template => $pathItem) {
            if ($this->shape((string) $template) !== $target) {
                continue;
            }

            $operations = $pathItem->getOperations();

            if (! isset($operations[$method])) {
                return null;
            }

            return new MatchedOperation(
                (string) $template,
                $method,
                $operations[$method],
                $pathItem,
                $this->parameterMap((string) $template, $route->uri()),
            );
        }

        return null;
    }

    /**
     * ドキュメント上の全操作を "GET /members" 形式で返す。
     *
     * @return list<string>
     */
    public function documented(): array
    {
        $names = [];

        foreach ($this->document->paths->getPaths() as $template => $pathItem) {
            foreach (array_keys($pathItem->getOperations()) as $method) {
                $names[] = strtoupper((string) $method).' '.$template;
            }
        }

        return $names;
    }

    /**
     * パラメータ名を潰した、照合用の形を返す。
     */
    private function shape(string $path): string
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '') {
            return '/';
        }

        return '/'.(preg_replace('/\{[^}]*\}/', '{}', $trimmed) ?? $trimmed);
    }

    /**
     * ドキュメント上のパラメータ名を、同じ位置にあるルート上の名前に対応づける。
     *
     * @return array<string, string>
     */
    private function parameterMap(string $template, string $uri): array
    {
        $specNames = $this->parameterNames($template);
        $routeNames = $this->parameterNames($uri);
        $map = [];

        foreach ($specNames as $index => $specName) {
            if (isset($routeNames[$index])) {
                $map[$specName] = $routeNames[$index];
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function parameterNames(string $path): array
    {
        preg_match_all('/\{([^}]*)\}/', $path, $matches);

        return array_map(static fn (string $name): string => rtrim($name, '?'), $matches[1]);
    }

    private function stripBasePath(string $uri): string
    {
        $base = trim($this->basePath, '/');
        $uri = ltrim($uri, '/');

        if ($base === '') {
            return $uri;
        }

        if ($uri === $base) {
            return '/';
        }

        return str_starts_with($uri, $base.'/') ? substr($uri, strlen($base) + 1) : $uri;
    }
}
