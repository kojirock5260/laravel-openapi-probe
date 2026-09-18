<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Spec;

use cebe\openapi\spec\Schema;

/**
 * パーサの Schema を、バリデータに渡せる JSON Schema オブジェクトに変換する。
 */
final class JsonSchema
{
    private function __construct() {}

    /**
     * @param  mixed  $schema  パーサが返した Schema
     * @return object|null JSON Schema。Schema でなければ null
     */
    public static function from(mixed $schema): ?object
    {
        if (! $schema instanceof Schema) {
            return null;
        }

        $decoded = json_decode(json_encode($schema->getSerializableData(), JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);

        return is_object($decoded) ? $decoded : null;
    }
}
