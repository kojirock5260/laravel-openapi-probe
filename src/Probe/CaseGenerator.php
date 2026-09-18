<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Probe;

use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\RequestBody;
use Kojirock5260\JsonSchemaValidate\Spec\ResolvedOperation;
use Kojirock5260\JsonSchemaValidate\Spec\SpecSchema;
use Kojirock5260\JsonSchemaValidate\Validation\MediaTypeMatcher;
use stdClass;

/**
 * 操作の定義から、適合するリクエスト 1 件と、制約を 1 つずつ破ったリクエストを組み立てる。
 */
final readonly class CaseGenerator
{
    private const string UNKNOWN_ID = '999999999';

    /**
     * @param  string  $pathValue  パスパラメータに使う値
     */
    public function __construct(
        private string $pathValue = '1',
    ) {}

    /**
     * 操作に対するケースを返す。先頭は常に spec に適合するリクエスト。
     *
     * @param  ResolvedOperation  $operation  対象の操作
     * @param  int  $limit  返すケースの上限
     * @return list<ProbeCase>
     */
    public function generate(ResolvedOperation $operation, int $limit): array
    {
        $parameters = $this->parameters($operation);
        $path = [];
        $query = [];
        $headers = [];

        foreach ($parameters as $parameter) {
            $schema = SpecSchema::toJsonSchema($parameter->schema);

            if ($parameter->in === 'path') {
                $path[$parameter->name] = $this->pathValue($schema);
            } elseif ($parameter->in === 'query' && $parameter->required) {
                $query[$parameter->name] = $this->plain(SampleValue::forSchema($schema ?? true));
            } elseif ($parameter->in === 'header' && $parameter->required) {
                $headers[$parameter->name] = (string) $this->plain(SampleValue::forSchema($schema ?? true));
            }
        }

        $query = $this->spreadDates($parameters, $query);

        $bodySchema = $this->bodySchema($operation);
        $body = $bodySchema === null ? null : SampleValue::forSchema($bodySchema);

        $cases = [new ProbeCase('conforming request', true, $path, $query, $headers, $body)];

        if ($path !== []) {
            $unknown = array_map(static fn (): string => self::UNKNOWN_ID, $path);
            $cases[] = new ProbeCase('unknown path parameter', true, $unknown, $query, $headers, $body);
        }

        foreach ($parameters as $parameter) {
            if ($parameter->in !== 'query') {
                continue;
            }

            if ($parameter->required) {
                $without = $query;
                unset($without[$parameter->name]);
                $cases[] = new ProbeCase("missing {$parameter->name}", false, $path, $without, $headers, $body);
            }

            $schema = SpecSchema::toJsonSchema($parameter->schema);

            if ($schema === null) {
                continue;
            }

            foreach (Mutation::forSchema($schema) as $label => $value) {
                if ($label === 'wrong type' && SampleValue::primaryType($schema) === 'string') {
                    continue;
                }

                $mutated = [$parameter->name => $this->plain($value)] + $query;
                $cases[] = new ProbeCase("{$parameter->name}: {$label}", false, $path, $mutated, $headers, $body);
            }
        }

        if ($bodySchema !== null) {
            $requestBody = $operation->operation->requestBody;

            if ($requestBody instanceof RequestBody && $requestBody->required) {
                $cases[] = new ProbeCase('missing body', false, $path, $query, $headers, null);
            }

            foreach ($this->bodyMutations($bodySchema, $body) as $label => $mutated) {
                $cases[] = new ProbeCase($label, false, $path, $query, $headers, $mutated);
            }
        }

        return array_slice($cases, 0, max(1, $limit));
    }

    /**
     * 本文の制約を 1 つずつ破った本文を返す。
     *
     * @param  object  $schema  本文のスキーマ
     * @param  mixed  $body  適合する本文
     * @return array<string, mixed> 説明 => 本文
     */
    private function bodyMutations(object $schema, mixed $body): array
    {
        if (! $body instanceof stdClass) {
            return [];
        }

        $mutations = [];
        $required = is_array($schema->required ?? null) ? $schema->required : [];
        $properties = is_object($schema->properties ?? null) ? get_object_vars($schema->properties) : [];

        foreach ($required as $name) {
            if (! is_string($name)) {
                continue;
            }

            $mutated = clone $body;
            unset($mutated->{$name});
            $mutations["body without {$name}"] = $mutated;
        }

        foreach ($properties as $name => $property) {
            if (! is_object($property)) {
                continue;
            }

            foreach (Mutation::forSchema($property) as $label => $value) {
                $mutated = clone $body;
                $mutated->{$name} = $value;
                $mutations["body.{$name}: {$label}"] = $mutated;
            }
        }

        if (($schema->additionalProperties ?? null) === false) {
            $mutated = clone $body;
            $mutated->probe_unknown_property = 'x';
            $mutations['body with unknown property'] = $mutated;
        }

        return $mutations;
    }

    /**
     * 日付型のクエリパラメータに、出現順に離れた日付を割り当てる。
     *
     * `start` と `end` のような組に同じ日付を入れると「start は end より前」で
     * 拒否されるため、1 か月ずつずらす。
     *
     * @param  list<Parameter>  $parameters  操作のパラメータ
     * @param  array<string, mixed>  $query  組み立て済みのクエリ
     * @return array<string, mixed>
     */
    private function spreadDates(array $parameters, array $query): array
    {
        $month = 1;

        foreach ($parameters as $parameter) {
            if ($parameter->in !== 'query' || ! array_key_exists($parameter->name, $query)) {
                continue;
            }

            $schema = SpecSchema::toJsonSchema($parameter->schema);
            $format = $schema->format ?? null;

            if ($format !== 'date' && $format !== 'date-time') {
                continue;
            }

            $date = sprintf('2024-%02d-01', min($month, 12));
            $query[$parameter->name] = $format === 'date' ? $date : $date.'T12:00:00+00:00';
            $month++;
        }

        return $query;
    }

    /**
     * PathItem と Operation のパラメータを、後者優先で統合する。
     *
     * @return list<Parameter>
     */
    private function parameters(ResolvedOperation $operation): array
    {
        $merged = [];

        foreach ([$operation->pathItem->parameters, $operation->operation->parameters] as $group) {
            foreach ($group as $parameter) {
                if ($parameter instanceof Parameter) {
                    $merged[$parameter->in.':'.$parameter->name] = $parameter;
                }
            }
        }

        return array_values($merged);
    }

    /**
     * JSON の本文スキーマを返す。JSON 系のメディアタイプが無ければ null。
     */
    private function bodySchema(ResolvedOperation $operation): ?object
    {
        $requestBody = $operation->operation->requestBody;

        if (! $requestBody instanceof RequestBody) {
            return null;
        }

        foreach ($requestBody->content as $mediaType => $media) {
            if (MediaTypeMatcher::isJson((string) $mediaType)) {
                return SpecSchema::toJsonSchema($media->schema);
            }
        }

        return null;
    }

    /**
     * パスパラメータの値を、スキーマの型に合わせて返す。
     */
    private function pathValue(?object $schema): string|int
    {
        if ($schema !== null && SampleValue::primaryType($schema) === 'integer' && is_numeric($this->pathValue)) {
            return (int) $this->pathValue;
        }

        return $this->pathValue;
    }

    /**
     * クエリやヘッダーに載せられるよう、オブジェクトを配列に崩す。
     */
    private function plain(mixed $value): mixed
    {
        if (is_object($value) || is_array($value)) {
            return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }

        return $value;
    }
}
