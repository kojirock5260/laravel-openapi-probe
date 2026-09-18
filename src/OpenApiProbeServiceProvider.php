<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe;

use Illuminate\Support\ServiceProvider;
use Kojirock5260\OpenApiProbe\Console\ProbeCommand;

/**
 * openapi:probe コマンドを登録する。
 *
 * 定義の読み込みと応答の検証は kojirock5260/laravel-json-schema-validate に依存し、
 * そのサービスプロバイダが用意する SpecRepository と OperationResolver を使う。
 */
final class OpenApiProbeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ProbeCommand::class]);
        }
    }
}
