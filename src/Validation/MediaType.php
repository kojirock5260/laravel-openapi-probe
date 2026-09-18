<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Validation;

/**
 * メディアタイプの照合。
 */
final class MediaType
{
    private function __construct() {}

    /**
     * Content-Type ヘッダーに対応する、定義済みメディアタイプを返す。
     * 完全一致、`type/*`、`* /*` の順に探す。
     *
     * @param  list<string>  $available  定義済みのメディアタイプ
     */
    public static function match(array $available, ?string $header): ?string
    {
        $actual = strtolower(trim(explode(';', (string) $header)[0]));

        if ($actual === '') {
            return null;
        }

        $byLower = [];

        foreach ($available as $candidate) {
            $byLower[strtolower($candidate)] = $candidate;
        }

        $type = explode('/', $actual)[0];

        foreach ([$actual, $type.'/*', '*/*'] as $candidate) {
            if (isset($byLower[$candidate])) {
                return $byLower[$candidate];
            }
        }

        return null;
    }

    /**
     * JSON として本文を検証できるメディアタイプか。
     */
    public static function isJson(?string $mediaType): bool
    {
        $type = strtolower(trim(explode(';', (string) $mediaType)[0]));

        return $type === 'application/json' || str_ends_with($type, '+json');
    }
}
