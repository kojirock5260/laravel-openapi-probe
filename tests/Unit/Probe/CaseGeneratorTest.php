<?php

declare(strict_types=1);

/**
 * CaseGenerator が fixture の操作から、適合ケースと制約違反ケースを作ることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Spec\OperationResolver;
use Kojirock5260\OpenApiProbe\Probe\CaseGenerator;
use Kojirock5260\OpenApiProbe\Probe\ProbeCase;

/**
 * fixture の操作に対するケースを、説明をキーにして返す。
 *
 * @return array<string, ProbeCase>
 */
function casesFor(string $template, string $method): array
{
    $operation = (new OperationResolver(specRepository()))->locate($template, $method);

    expect($operation)->not->toBeNull();

    $keyed = [];

    foreach ((new CaseGenerator)->generate($operation, 50) as $case) {
        $keyed[$case->label] = $case;
    }

    return $keyed;
}

it('starts with a conforming request built from required parameters', function (): void {
    $cases = casesFor('/members', 'get');

    expect(array_key_first($cases))->toBe('conforming request')
        ->and($cases['conforming request']->conforming)->toBeTrue()
        ->and($cases['conforming request']->query)->toBe(['page' => 1]);
});

it('drops and mutates required query parameters', function (): void {
    $cases = casesFor('/members', 'get');

    expect($cases)->toHaveKeys(['missing page', 'page: at exclusiveMinimum', 'page: wrong type'])
        ->and($cases['missing page']->query)->toBe([])
        ->and($cases['page: at exclusiveMinimum']->query)->toBe(['page' => 0])
        ->and($cases['missing page']->conforming)->toBeFalse();
});

it('builds a body from the schema and mutates each property', function (): void {
    $cases = casesFor('/members', 'post');
    $body = $cases['conforming request']->body;

    expect($body->name)->toBe('probe')
        ->and($body->status)->toBe('active')
        ->and($cases)->toHaveKeys(['missing body', 'body without name', 'body.name: under minLength', 'body.status: not the const'])
        ->and($cases['body.name: under minLength']->body->name)->toBe('')
        ->and($cases['body without name']->body->status)->toBe('active');
});

it('adds an unknown-id case for operations with path parameters', function (): void {
    $cases = casesFor('/members/{memberId}', 'get');

    expect($cases['conforming request']->pathParameters)->toBe(['memberId' => 1])
        ->and($cases['unknown path parameter']->pathParameters)->toBe(['memberId' => '999999999'])
        ->and($cases['unknown path parameter']->conforming)->toBeTrue();
});

it('spreads date parameters so ranges are not empty', function (): void {
    $cases = casesFor('/reports', 'get');

    expect($cases['conforming request']->query)->toBe(['start' => '2024-01-01', 'end' => '2024-02-01']);
});
