<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Spec;

use JsonException;
use Kojirock5260\JsonSchemaValidate\Spec\OperationResolver;
use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * 探査に使う OpenAPI ドキュメントを用意する。
 *
 * 3.0 の文書は {@see Dialect30} で 3.1 の表現に直した写しを一時ファイルに書き、
 * そちらを読む。元のファイルには触らない。3.1 の文書はそのまま読む。
 */
final readonly class ProbeSpec
{
    public function __construct(
        public SpecRepository $repository,
        public OperationResolver $resolver,
    ) {}

    /**
     * 定義の場所と、ルートから取り除く接頭辞から組み立てる。
     *
     * @param  string  $path  OpenAPI ドキュメントのパス
     * @param  string  $basePath  ルート URI から取り除く接頭辞
     */
    public static function load(string $path, string $basePath = ''): self
    {
        $repository = new SpecRepository(self::normalizedPath($path));

        return new self($repository, new OperationResolver($repository, $basePath));
    }

    /**
     * 3.0 の文書なら正規化した写しのパスを、それ以外なら元のパスを返す。
     */
    private static function normalizedPath(string $path): string
    {
        $document = self::read($path);
        $version = is_string($document['openapi'] ?? null) ? $document['openapi'] : '';

        if (! str_starts_with($version, '3.0')) {
            return $path;
        }

        $target = sys_get_temp_dir().'/openapi-probe-'.sha1($path.'|'.filemtime($path)).'.json';

        if (! is_file($target)) {
            $normalized = Dialect30::normalize($document);
            $normalized['openapi'] = '3.1.0';

            try {
                $json = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            } catch (JsonException $exception) {
                throw new RuntimeException("The OpenAPI document [{$path}] could not be normalized: ".$exception->getMessage(), 0, $exception);
            }

            file_put_contents($target, $json);
        }

        return $target;
    }

    /**
     * 文書を配列として読む。
     *
     * @return array<array-key, mixed>
     */
    private static function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("The OpenAPI document [{$path}] does not exist.");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $document = $extension === 'json'
            ? json_decode((string) file_get_contents($path), true)
            : Yaml::parseFile($path);

        if (! is_array($document)) {
            throw new RuntimeException("The OpenAPI document [{$path}] is not a mapping.");
        }

        return $document;
    }
}
