<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Probe;

use stdClass;

/**
 * JSON Schema に適合する値を 1 つ組み立てる。
 *
 * `const`、`example`、`enum`、`default` の順に spec が示す値を優先し、
 * 無ければ型と制約から最小限の値を作る。
 */
final class SampleValue
{
    private const int MAX_DEPTH = 8;

    private function __construct() {}

    /**
     * スキーマに適合する値を返す。
     *
     * @param  object|bool  $schema  JSON Schema
     * @param  int  $depth  再帰の深さ
     * @return mixed 適合する値
     */
    public static function forSchema(object|bool $schema, int $depth = 0): mixed
    {
        if ($schema === true) {
            return 'probe';
        }

        if ($schema === false || $depth > self::MAX_DEPTH) {
            return null;
        }

        if (property_exists($schema, 'const')) {
            return $schema->const;
        }

        if (property_exists($schema, 'example')) {
            return $schema->example;
        }

        if (isset($schema->examples) && is_array($schema->examples) && $schema->examples !== []) {
            return $schema->examples[0];
        }

        if (isset($schema->enum) && is_array($schema->enum) && $schema->enum !== []) {
            return $schema->enum[0];
        }

        if (property_exists($schema, 'default')) {
            return $schema->default;
        }

        if (isset($schema->allOf) && is_array($schema->allOf)) {
            return self::merged(array_values($schema->allOf), $depth);
        }

        foreach (['oneOf', 'anyOf'] as $keyword) {
            if (isset($schema->{$keyword}) && is_array($schema->{$keyword}) && $schema->{$keyword} !== []) {
                $first = $schema->{$keyword}[0];

                if (is_object($first) || is_bool($first)) {
                    return self::forSchema($first, $depth + 1);
                }
            }
        }

        return match (self::primaryType($schema)) {
            'string' => self::string($schema),
            'integer' => (int) self::numeric($schema),
            'number' => self::numeric($schema),
            'boolean' => true,
            'array' => self::array($schema, $depth),
            'object' => self::object($schema, $depth),
            'null' => null,
            default => isset($schema->properties) ? self::object($schema, $depth) : 'probe',
        };
    }

    /**
     * スキーマの主たる型を返す。union なら null 以外の最初の型。
     *
     * @param  object  $schema  JSON Schema
     * @return string|null 型名。型が無ければ null
     */
    public static function primaryType(object $schema): ?string
    {
        $type = $schema->type ?? null;

        if (is_string($type)) {
            return $type;
        }

        if (is_array($type)) {
            foreach ($type as $candidate) {
                if (is_string($candidate) && $candidate !== 'null') {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @param  object  $schema  文字列のスキーマ
     */
    private static function string(object $schema): string
    {
        $value = match ($schema->format ?? null) {
            'date' => '2024-01-31',
            'date-time' => '2024-01-31T12:00:00+00:00',
            'email' => 'probe@example.com',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'uri', 'url' => 'https://example.com/probe',
            'ipv4' => '192.0.2.1',
            'ipv6' => '2001:db8::1',
            'hostname' => 'example.com',
            default => 'probe',
        };

        $min = (int) ($schema->minLength ?? 0);

        if (strlen($value) < $min) {
            $value = str_pad($value, $min, 'a');
        }

        if (isset($schema->maxLength) && strlen($value) > (int) $schema->maxLength) {
            $value = substr($value, 0, (int) $schema->maxLength);
        }

        return $value;
    }

    /**
     * @param  object  $schema  数値のスキーマ
     */
    private static function numeric(object $schema): float|int
    {
        if (isset($schema->minimum) && is_numeric($schema->minimum)) {
            return $schema->minimum + 0;
        }

        if (isset($schema->exclusiveMinimum) && is_numeric($schema->exclusiveMinimum)) {
            return $schema->exclusiveMinimum + 1;
        }

        if (isset($schema->maximum) && is_numeric($schema->maximum)) {
            return $schema->maximum + 0;
        }

        if (isset($schema->exclusiveMaximum) && is_numeric($schema->exclusiveMaximum)) {
            return $schema->exclusiveMaximum - 1;
        }

        return 1;
    }

    /**
     * @param  object  $schema  配列のスキーマ
     * @return list<mixed>
     */
    private static function array(object $schema, int $depth): array
    {
        $min = (int) ($schema->minItems ?? 0);
        $items = $schema->items ?? null;

        if ($min === 0 || (! is_object($items) && ! is_bool($items))) {
            return [];
        }

        return array_fill(0, $min, self::forSchema($items, $depth + 1));
    }

    /**
     * 必須プロパティだけを持つオブジェクトを作る。
     *
     * @param  object  $schema  オブジェクトのスキーマ
     */
    private static function object(object $schema, int $depth): stdClass
    {
        $value = new stdClass;
        $required = is_array($schema->required ?? null) ? $schema->required : [];
        $properties = is_object($schema->properties ?? null) ? get_object_vars($schema->properties) : [];

        foreach ($required as $name) {
            if (! is_string($name) || ! isset($properties[$name])) {
                continue;
            }

            $property = $properties[$name];

            if (is_object($property) || is_bool($property)) {
                $value->{$name} = self::forSchema($property, $depth + 1);
            }
        }

        return $value;
    }

    /**
     * allOf の各スキーマから作った値を 1 つのオブジェクトに重ねる。
     *
     * @param  list<mixed>  $schemas  allOf の要素
     */
    private static function merged(array $schemas, int $depth): mixed
    {
        $merged = new stdClass;
        $last = null;

        foreach ($schemas as $schema) {
            if (! is_object($schema) && ! is_bool($schema)) {
                continue;
            }

            $last = self::forSchema($schema, $depth + 1);

            if ($last instanceof stdClass) {
                foreach (get_object_vars($last) as $name => $value) {
                    $merged->{$name} = $value;
                }
            }
        }

        return get_object_vars($merged) === [] ? $last : $merged;
    }
}
