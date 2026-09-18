<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Spec;

use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use JsonException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * OpenAPI ドキュメントを読み込む。
 *
 * 3.0 の文書は {@see Dialect30} でメモリ上で 3.1 の表現に直してから解析する。
 * 元のファイルには触らない。
 */
final class Document
{
    private function __construct() {}

    /**
     * ファイルを読み、参照を解決した OpenApi を返す。
     *
     * @param  string  $path  .json / .yaml / .yml のパス
     */
    public static function load(string $path): OpenApi
    {
        if (! is_file($path)) {
            throw new RuntimeException("The OpenAPI document [{$path}] does not exist.");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $data = $extension === 'json'
            ? json_decode((string) file_get_contents($path), true)
            : Yaml::parseFile($path);

        if (! is_array($data)) {
            throw new RuntimeException("The OpenAPI document [{$path}] is not a mapping.");
        }

        $version = is_string($data['openapi'] ?? null) ? $data['openapi'] : '';

        if (str_starts_with($version, '3.0')) {
            $data = Dialect30::normalize($data);
            $data['openapi'] = '3.1.0';
        }

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException("The OpenAPI document [{$path}] could not be read: ".$exception->getMessage(), 0, $exception);
        }

        /** @var OpenApi $document */
        $document = Reader::readFromJson($json, OpenApi::class);
        $document->resolveReferences(new ReferenceContext($document, (string) realpath($path)));

        return $document;
    }
}
