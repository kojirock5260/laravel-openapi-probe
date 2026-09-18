<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe;

use Illuminate\Support\ServiceProvider;
use Kojirock5260\OpenApiProbe\Console\ProbeCommand;

/**
 * openapi:probe コマンドと設定を登録する。
 */
final class OpenApiProbeServiceProvider extends ServiceProvider
{
    private const string CONFIG_PATH = __DIR__.'/../config/openapi-probe.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'openapi-probe');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::CONFIG_PATH => config_path('openapi-probe.php')], 'config');
            $this->commands([ProbeCommand::class]);
        }
    }
}
