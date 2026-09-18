<?php

declare(strict_types=1);

/**
 * openapi:probe が生成したリクエストをルートに送り、spec とのズレを報告することを確認する。
 */

use Illuminate\Support\Facades\Route;

it('reports bypassed validation, undocumented statuses and mismatching responses', function (): void {
    // page が無くても 200 を返し、本文も spec に合わない一覧
    Route::get('members', static fn () => response()->json(['items' => []]));
    // どんな本文でも 201 を返す作成
    Route::post('members', static fn () => response()->json([], 201));
    // 常に 500 で落ちる詳細
    Route::get('members/{memberId}', static function (): never {
        throw new RuntimeException('boom');
    });

    $this->artisan('openapi:probe', ['--strict' => true])
        ->expectsOutputToContain('validation-bypass')
        ->expectsOutputToContain('response-mismatch')
        ->expectsOutputToContain('server-error')
        ->expectsOutputToContain('Operations probed')
        ->assertExitCode(1);
});

it('lists documented operations that have no route', function (): void {
    Route::get('members', static fn () => response()->json(['data' => []])->header('X-Total-Count', '0'));

    // 同じ行に 2 つの期待文字列は置けない（最初に一致した期待だけが消費される）
    $this->artisan('openapi:probe')
        ->expectsOutputToContain('missing-route POST /members')
        ->assertExitCode(0);
});

it('limits the run to matching operations and passes headers through', function (): void {
    Route::get('members', static function () {
        return request()->header('X-Probe') === 'yes'
            ? response()->json(['data' => []])->header('X-Total-Count', '0')
            : response()->json(['data' => []], 200);
    });
    Route::post('members', static fn () => response()->json([], 201));

    $this->artisan('openapi:probe', ['--only' => 'GET /members', '--header' => ['X-Probe: yes'], '--strict' => true])
        ->doesntExpectOutputToContain('POST /members')
        ->expectsOutputToContain('validation-bypass')
        ->assertExitCode(1);
});

it('refuses to run in production unless forced', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');

    $this->artisan('openapi:probe')
        ->expectsOutputToContain('Refusing to probe a production environment')
        ->assertExitCode(1);
});

it('accepts null for nullable fields of an OpenAPI 3.0 document', function (): void {
    config()->set('openapi-probe.path', __DIR__.'/../../fixtures/openapi-30.yaml');
    Route::get('profiles', static fn () => response()->json(['nickname' => null]));

    $this->artisan('openapi:probe', ['--strict' => true])
        ->expectsOutputToContain('No findings.')
        ->assertExitCode(0);
});
