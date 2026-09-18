<?php

declare(strict_types=1);

/**
 * Dialect30 が 3.0 のキーワードを 3.1 の表現に書き換えることを確認する。
 */

use Kojirock5260\OpenApiProbe\Spec\Dialect30;

it('turns nullable into a type union', function (): void {
    expect(Dialect30::normalize(['type' => 'string', 'nullable' => true]))
        ->toBe(['type' => ['string', 'null']]);
});

it('drops nullable false without touching the type', function (): void {
    expect(Dialect30::normalize(['type' => 'integer', 'nullable' => false]))
        ->toBe(['type' => 'integer']);
});

it('converts boolean exclusive bounds into numeric ones', function (): void {
    $normalized = Dialect30::normalize([
        'type' => 'integer', 'minimum' => 0, 'exclusiveMinimum' => true, 'maximum' => 10, 'exclusiveMaximum' => false,
    ]);

    expect($normalized)->toBe(['type' => 'integer', 'maximum' => 10, 'exclusiveMinimum' => 0]);
});

it('walks the whole document but leaves example data and a property named nullable alone', function (): void {
    $normalized = Dialect30::normalize([
        'components' => ['schemas' => ['Thing' => [
            'type' => 'object',
            'properties' => [
                'tags' => ['type' => 'array', 'items' => ['type' => 'string', 'nullable' => true]],
                'nullable' => ['type' => 'boolean'],
            ],
            'example' => ['nullable' => true, 'type' => 'kept'],
        ]]],
    ]);

    $thing = $normalized['components']['schemas']['Thing'];

    expect($thing['properties']['tags']['items']['type'])->toBe(['string', 'null'])
        ->and($thing['properties']['nullable'])->toBe(['type' => 'boolean'])
        ->and($thing['example'])->toBe(['nullable' => true, 'type' => 'kept']);
});
