<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Validation;

use cebe\openapi\spec\Header;
use cebe\openapi\spec\MediaType as SpecMediaType;
use cebe\openapi\spec\Response as SpecResponse;
use JsonException;
use Kojirock5260\OpenApiProbe\Spec\JsonSchema;
use Kojirock5260\OpenApiProbe\Spec\MatchedOperation;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * 応答のヘッダーと本文を、操作の応答定義と照合する。
 */
final readonly class ResponseCheck
{
    private const int MAX_ERRORS = 50;

    public function __construct(
        private Validator $validator = new Validator(max_errors: self::MAX_ERRORS),
        private ErrorFormatter $formatter = new ErrorFormatter,
    ) {}

    /**
     * 食い違いを "場所: 内容" の一覧で返す。ステータスが未定義の場合は呼び出し側で扱う。
     *
     * @return list<string>
     */
    public function errors(MatchedOperation $operation, Response $response): array
    {
        $specResponse = $operation->responseFor($response->getStatusCode());

        if (! $specResponse instanceof SpecResponse) {
            return [];
        }

        return [...$this->headerErrors($specResponse, $response), ...$this->bodyErrors($specResponse, $response)];
    }

    /**
     * @return list<string>
     */
    private function headerErrors(SpecResponse $specResponse, Response $response): array
    {
        $errors = [];

        foreach ($specResponse->headers as $name => $header) {
            if (! $header instanceof Header) {
                continue;
            }

            $value = $response->headers->get((string) $name);

            if ($value === null) {
                if ($header->required) {
                    $errors[] = "{$name}: The response header is required.";
                }

                continue;
            }

            $schema = JsonSchema::from($header->schema);

            if ($schema !== null) {
                foreach ($this->validate($this->coerce($value, $schema), $schema) as $message) {
                    $errors[] = "{$name}: {$message}";
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function bodyErrors(SpecResponse $specResponse, Response $response): array
    {
        if ($specResponse->content === []) {
            return [];
        }

        $contentType = $response->headers->get('Content-Type');
        $mediaType = MediaType::match(array_map('strval', array_keys($specResponse->content)), $contentType);

        if ($mediaType === null) {
            return ["body: The content type [{$contentType}] is not defined for this response."];
        }

        $media = $specResponse->content[$mediaType] ?? null;

        if (! MediaType::isJson($mediaType) || ! $media instanceof SpecMediaType) {
            return [];
        }

        $schema = JsonSchema::from($media->schema);

        if ($schema === null) {
            return [];
        }

        $content = (string) $response->getContent();

        if ($content === '') {
            return ['body: The response body is empty but a schema is defined.'];
        }

        try {
            $decoded = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return ['body: The response body is not valid JSON: '.$exception->getMessage()];
        }

        $errors = [];

        foreach ($this->validate($decoded, $schema, 'body') as $message) {
            $errors[] = $message;
        }

        return $errors;
    }

    /**
     * 値をスキーマで検証し、"場所: 内容" の一覧を返す。
     *
     * @return list<string>
     */
    private function validate(mixed $data, object $schema, string $root = ''): array
    {
        $error = $this->validator->validate($data, $schema)->error();

        if ($error === null) {
            return [];
        }

        $messages = [];

        foreach ($this->formatter->formatKeyed($error) as $pointer => $lines) {
            $path = trim(str_replace('/', '.', (string) $pointer), '.');
            $place = $root === '' ? $path : ($path === '' ? $root : $root.'.'.$path);

            foreach ((array) $lines as $line) {
                $messages[] = $place === '' ? (string) $line : "{$place}: {$line}";
            }
        }

        return $messages;
    }

    /**
     * ヘッダーの文字列を、スキーマが宣言する型に寄せる。変換できなければそのまま返す。
     */
    private function coerce(string $value, object $schema): mixed
    {
        $type = $schema->type ?? null;
        $types = is_array($type) ? $type : [$type];

        if (in_array('integer', $types, true) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (in_array('number', $types, true) && is_numeric($value)) {
            return $value + 0;
        }

        if (in_array('boolean', $types, true) && in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true';
        }

        return $value;
    }
}
