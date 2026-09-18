<?php

declare(strict_types=1);

/**
 * ResponseCheck が応答のヘッダーと本文を応答定義と照合することを確認する。
 */

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Route;
use Kojirock5260\OpenApiProbe\Spec\MatchedOperation;
use Kojirock5260\OpenApiProbe\Spec\OperationMatcher;
use Kojirock5260\OpenApiProbe\Validation\ResponseCheck;

function listMembers(): MatchedOperation
{
    return (new OperationMatcher(fixtureDocument()))->match(new Route(['GET'], 'members', static fn () => null), 'GET');
}

it('accepts a response that matches the documented headers and body', function (): void {
    $response = (new JsonResponse(['data' => [['name' => 'kojirock', 'status' => 'active']]]))->header('X-Total-Count', '1');

    expect((new ResponseCheck)->errors(listMembers(), $response))->toBe([]);
});

it('reports a missing required header and a body that breaks the schema', function (): void {
    $errors = (new ResponseCheck)->errors(listMembers(), new JsonResponse(['data' => [['name' => 123, 'status' => 'active']]]));

    expect($errors)->toContain('X-Total-Count: The response header is required.')
        ->and(implode("\n", $errors))->toContain('body.data.0.name');
});

it('resolves a status through the 4XX range and default', function (): void {
    expect(listMembers()->responseFor(404))->not->toBeNull()
        ->and(listMembers()->responseFor(500))->not->toBeNull()
        ->and((new ResponseCheck)->errors(listMembers(), new JsonResponse(['message' => 'nope'], 404)))->toBe([]);
});
