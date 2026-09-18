<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Spec;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Response as SpecResponse;
use cebe\openapi\spec\Responses;

/**
 * ルートに対応づいた、ドキュメント上の操作。
 */
final readonly class MatchedOperation
{
    /**
     * @param  string  $path  ドキュメント上のパステンプレート
     * @param  string  $method  小文字の HTTP メソッド
     * @param  Operation  $operation  操作
     * @param  PathItem  $pathItem  操作が属する PathItem
     * @param  array<string, string>  $parameterMap  ドキュメント上のパスパラメータ名 => ルート上の名前
     */
    public function __construct(
        public string $path,
        public string $method,
        public Operation $operation,
        public PathItem $pathItem,
        public array $parameterMap = [],
    ) {}

    /**
     * ドキュメント上のパスパラメータ名に対応する、ルート上の名前を返す。
     */
    public function routeParameterName(string $specName): string
    {
        return $this->parameterMap[$specName] ?? $specName;
    }

    /**
     * "GET /members" 形式の名前を返す。
     */
    public function describe(): string
    {
        return strtoupper($this->method).' '.$this->path;
    }

    /**
     * ステータスコードに対応する応答定義を返す。完全一致、範囲（4XX）、default の順に探す。
     */
    public function responseFor(int $status): ?SpecResponse
    {
        $responses = $this->operation->responses;

        if (! $responses instanceof Responses) {
            return null;
        }

        $text = (string) $status;

        foreach ([$text, $text[0].'XX', 'default'] as $candidate) {
            if (! $responses->hasResponse($candidate)) {
                continue;
            }

            $found = $responses->getResponse($candidate);

            if ($found instanceof SpecResponse) {
                return $found;
            }
        }

        return null;
    }
}
