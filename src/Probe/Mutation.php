<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Probe;

/**
 * スキーマの制約を 1 つずつ破った値を作る。
 */
final class Mutation
{
    private function __construct() {}

    /**
     * 制約に違反する値を、説明をキーにして返す。
     *
     * @param  object  $schema  JSON Schema
     * @return array<string, mixed> 説明 => 違反する値
     */
    public static function forSchema(object $schema): array
    {
        $variants = [];

        if (isset($schema->enum) && is_array($schema->enum)) {
            $variants['not in enum'] = '__not_in_enum__';
        }

        if (property_exists($schema, 'const')) {
            $variants['not the const'] = '__not_the_const__';
        }

        switch (SampleValue::primaryType($schema)) {
            case 'string':
                if (isset($schema->maxLength)) {
                    $variants['over maxLength'] = str_repeat('a', (int) $schema->maxLength + 1);
                }

                if (isset($schema->minLength) && (int) $schema->minLength > 0) {
                    $variants['under minLength'] = str_repeat('a', (int) $schema->minLength - 1);
                }

                $format = $schema->format ?? null;

                if (in_array($format, ['date', 'date-time', 'email', 'uuid', 'uri', 'url', 'ipv4', 'ipv6'], true)) {
                    $variants["invalid {$format}"] = 'not-valid';
                }

                $variants['wrong type'] = 12345;
                break;

            case 'integer':
            case 'number':
                if (isset($schema->minimum) && is_numeric($schema->minimum)) {
                    $variants['below minimum'] = $schema->minimum - 1;
                }

                if (isset($schema->exclusiveMinimum) && is_numeric($schema->exclusiveMinimum)) {
                    $variants['at exclusiveMinimum'] = $schema->exclusiveMinimum + 0;
                }

                if (isset($schema->maximum) && is_numeric($schema->maximum)) {
                    $variants['above maximum'] = $schema->maximum + 1;
                }

                if (isset($schema->exclusiveMaximum) && is_numeric($schema->exclusiveMaximum)) {
                    $variants['at exclusiveMaximum'] = $schema->exclusiveMaximum + 0;
                }

                $variants['wrong type'] = 'not-a-number';
                break;

            case 'boolean':
                $variants['wrong type'] = 'maybe';
                break;

            case 'array':
                $variants['wrong type'] = 'not-an-array';
                break;

            case 'object':
                $variants['wrong type'] = 'not-an-object';
                break;
        }

        return $variants;
    }
}
