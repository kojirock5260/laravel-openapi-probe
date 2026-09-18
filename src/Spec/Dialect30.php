<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Spec;

/**
 * OpenAPI 3.0 方言のキーワードを、3.1（JSON Schema 2020-12）の表現に書き換える。
 *
 * 3.0 の `nullable: true` は型の union に、真偽値の `exclusiveMinimum` /
 * `exclusiveMaximum` は数値の境界に変換する。文書全体を配列のまま辿るので、
 * スキーマがどこに現れても同じ規則で置き換わる。
 */
final class Dialect30
{
    /** 値をそのまま残すキー。中身はスキーマではなくデータなので辿らない */
    private const array DATA_KEYS = ['enum', 'const', 'default', 'example', 'examples'];

    private function __construct() {}

    /**
     * 文書、またはその一部を正規化して返す。
     *
     * @param  array<array-key, mixed>  $node  文書の一部
     * @return array<array-key, mixed>
     */
    public static function normalize(array $node): array
    {
        $node = self::rewriteNullable($node);
        $node = self::rewriteExclusiveBound($node, 'exclusiveMinimum', 'minimum');
        $node = self::rewriteExclusiveBound($node, 'exclusiveMaximum', 'maximum');

        foreach ($node as $key => $value) {
            if (is_array($value) && ! in_array($key, self::DATA_KEYS, true)) {
                $node[$key] = self::normalize($value);
            }
        }

        return $node;
    }

    /**
     * `nullable: true` を型の union に変換し、キーワード自体は取り除く。
     *
     * `properties` の下に "nullable" という名前のプロパティがある場合、値は配列なので触らない。
     *
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private static function rewriteNullable(array $node): array
    {
        if (! array_key_exists('nullable', $node) || ! is_bool($node['nullable'])) {
            return $node;
        }

        $nullable = $node['nullable'];
        unset($node['nullable']);

        if (! $nullable || ! isset($node['type'])) {
            return $node;
        }

        $types = is_array($node['type']) ? array_values($node['type']) : [$node['type']];

        if (! in_array('null', $types, true)) {
            $types[] = 'null';
        }

        $node['type'] = $types;

        return $node;
    }

    /**
     * 真偽値の排他境界を、数値の境界に変換する。
     *
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private static function rewriteExclusiveBound(array $node, string $exclusive, string $bound): array
    {
        if (! array_key_exists($exclusive, $node) || ! is_bool($node[$exclusive])) {
            return $node;
        }

        $flag = $node[$exclusive];
        unset($node[$exclusive]);

        if ($flag && isset($node[$bound]) && is_numeric($node[$bound])) {
            $node[$exclusive] = $node[$bound];
            unset($node[$bound]);
        }

        return $node;
    }
}
