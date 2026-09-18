<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Probe;

/**
 * 1 回の探査リクエストの内容。
 */
final readonly class ProbeCase
{
    /**
     * @param  string  $label  何を試すケースかの短い説明
     * @param  bool  $conforming  spec に適合するリクエストなら true
     * @param  array<string, mixed>  $pathParameters  spec 上の名前をキーにしたパスパラメータ
     * @param  array<string, mixed>  $query  クエリパラメータ
     * @param  array<string, string>  $headers  ヘッダー
     * @param  mixed  $body  JSON にエンコードして送る本文。null なら本文なし
     */
    public function __construct(
        public string $label,
        public bool $conforming,
        public array $pathParameters = [],
        public array $query = [],
        public array $headers = [],
        public mixed $body = null,
    ) {}
}
