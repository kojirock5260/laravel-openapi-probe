<?php

declare(strict_types=1);

/**
 * OperationMatcher がルートをドキュメント上の操作に、パラメータ名によらず位置で対応づけることを確認する。
 */

use Illuminate\Routing\Route;
use Kojirock5260\OpenApiProbe\Spec\OperationMatcher;

it('matches routes by position, ignoring parameter names and the base path', function (): void {
    $matcher = new OperationMatcher(fixtureDocument(), 'api');
    $operation = $matcher->match(new Route(['GET'], 'api/members/{member}', static fn () => null), 'GET');

    expect($operation)->not->toBeNull()
        ->and($operation->describe())->toBe('GET /members/{memberId}')
        ->and($operation->routeParameterName('memberId'))->toBe('member');
});

it('returns null for routes and methods the document does not describe', function (): void {
    $matcher = new OperationMatcher(fixtureDocument());

    expect($matcher->match(new Route(['GET'], 'unknown', static fn () => null), 'GET'))->toBeNull()
        ->and($matcher->match(new Route(['DELETE'], 'members', static fn () => null), 'DELETE'))->toBeNull();
});

it('lists every documented operation', function (): void {
    expect((new OperationMatcher(fixtureDocument()))->documented())
        ->toContain('GET /members', 'POST /members', 'GET /members/{memberId}', 'GET /reports');
});
