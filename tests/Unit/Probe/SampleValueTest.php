<?php

declare(strict_types=1);

/**
 * SampleValue が spec の示す値を優先し、無ければ制約から値を作ることを確認する。
 */

use Kojirock5260\OpenApiProbe\Probe\SampleValue;

it('prefers const, then example, then enum, then default', function (): void {
    expect(SampleValue::forSchema(json_decode('{"const":"fixed","example":"x"}')))->toBe('fixed')
        ->and(SampleValue::forSchema(json_decode('{"type":"string","example":"shown","enum":["a"]}')))->toBe('shown')
        ->and(SampleValue::forSchema(json_decode('{"type":"string","enum":["first","second"]}')))->toBe('first')
        ->and(SampleValue::forSchema(json_decode('{"type":"integer","default":7}')))->toBe(7);
});

it('builds strings that satisfy length and format', function (): void {
    expect(SampleValue::forSchema(json_decode('{"type":"string","minLength":8}')))->toHaveLength(8)
        ->and(SampleValue::forSchema(json_decode('{"type":"string","maxLength":3}')))->toBe('pro')
        ->and(SampleValue::forSchema(json_decode('{"type":"string","format":"date"}')))->toBe('2024-01-31');
});

it('builds numbers at the lower bound', function (): void {
    expect(SampleValue::forSchema(json_decode('{"type":"integer","minimum":5}')))->toBe(5)
        ->and(SampleValue::forSchema(json_decode('{"type":"integer","exclusiveMinimum":0}')))->toBe(1)
        ->and(SampleValue::forSchema(json_decode('{"type":"number"}')))->toBe(1);
});

it('builds objects with only the required properties', function (): void {
    $value = SampleValue::forSchema(json_decode('{"type":"object","required":["name"],"properties":{"name":{"type":"string"},"nickname":{"type":["string","null"]}}}'));

    expect($value)->toBeInstanceOf(stdClass::class)
        ->and($value->name)->toBe('probe')
        ->and(property_exists($value, 'nickname'))->toBeFalse();
});

it('uses the first non-null type of a union', function (): void {
    expect(SampleValue::primaryType(json_decode('{"type":["null","integer"]}')))->toBe('integer')
        ->and(SampleValue::forSchema(json_decode('{"type":["null","integer"],"minimum":2}')))->toBe(2);
});
