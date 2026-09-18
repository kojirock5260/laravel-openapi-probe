<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;
use Kojirock5260\OpenApiProbe\Probe\CaseGenerator;
use Kojirock5260\OpenApiProbe\Probe\Finding;
use Kojirock5260\OpenApiProbe\Probe\Prober;
use Kojirock5260\OpenApiProbe\Spec\ProbeSpec;

/**
 * spec からリクエストを生成してアプリケーションに送り、応答を spec と突き合わせるコマンド。
 */
final class ProbeCommand extends Command
{
    /**
     * コマンドの呼び出し名。
     *
     * @var string
     */
    protected $signature = 'openapi:probe
        {--token= : Bearer token sent with every request (defaults to OPENAPI_PROBE_TOKEN)}
        {--header=* : Extra header, e.g. --header="X-Api-Key: secret"}
        {--id=1 : Value used for path parameters}
        {--only= : Only probe operations whose "METHOD /path" contains this text}
        {--limit=16 : Maximum number of cases per operation}
        {--keep-throttle : Keep throttle middleware (removed by default)}
        {--allow-production : Run even when APP_ENV is production}
        {--strict : Exit with a failure code when findings exist}';

    /**
     * コマンドの説明。
     *
     * @var string
     */
    protected $description = 'Generate requests from the OpenAPI document, send them to the application, and compare the responses with the document';

    /**
     * コマンドを実行する。
     *
     * @param  Router  $router  登録済みルート
     * @param  Prober  $prober  リクエストの送信と照合
     * @return int 終了コード
     */
    public function handle(Router $router, Prober $prober): int
    {
        // 送ったリクエストの副作用のうち巻き戻せるのは DB だけなので、本番では動かさない
        if ($this->laravel->environment('production') && ! $this->option('allow-production')) {
            $this->components->error('Refusing to probe a production environment. Pass --allow-production to override.');

            return self::FAILURE;
        }

        if (! $this->option('keep-throttle')) {
            $this->removeThrottle($router);
        }

        $probeSpec = ProbeSpec::load(
            (string) config('json-schema.path'),
            (string) config('json-schema.base_path', ''),
        );
        $resolver = $probeSpec->resolver;
        $spec = $probeSpec->repository;

        $generator = new CaseGenerator($this->stringOption('id', '1'));
        $headers = $this->headers();
        $only = $this->stringOption('only', '');
        $limit = (int) $this->stringOption('limit', '16');

        $findings = [];
        $matched = [];
        $operations = 0;
        $cases = 0;

        foreach ($router->getRoutes()->getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $operation = $resolver->resolve($route, $method);

                if ($operation === null) {
                    continue;
                }

                $matched[$operation->describe()] = true;

                if ($only !== '' && ! str_contains($operation->describe(), $only)) {
                    continue;
                }

                $operations++;

                foreach ($generator->generate($operation, $limit) as $case) {
                    $cases++;
                    $found = $prober->probe($route, $operation, $case, $headers);

                    foreach ($found as $finding) {
                        $findings[] = $finding;
                        $this->report($finding);
                    }
                }
            }
        }

        if ($only === '') {
            foreach ($this->missingRoutes($spec, $matched) as $finding) {
                $findings[] = $finding;
                $this->report($finding);
            }
        }

        $this->summary($operations, $cases, $findings);

        return $findings !== [] && $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 問題を 1 件出力する。
     */
    private function report(Finding $finding): void
    {
        $status = $finding->status === 0 ? '' : " → {$finding->status}";
        $this->components->twoColumnDetail("<fg=yellow>{$finding->kind}</> {$finding->operation}", $finding->case.$status);

        foreach ($finding->messages as $message) {
            $this->line("    <fg=gray>{$message}</>");
        }
    }

    /**
     * 種類ごとの件数をまとめて出力する。
     *
     * @param  list<Finding>  $findings  見つかった問題
     */
    private function summary(int $operations, int $cases, array $findings): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Operations probed', (string) $operations);
        $this->components->twoColumnDetail('Requests sent', (string) $cases);

        $counts = [];

        foreach ($findings as $finding) {
            $counts[$finding->kind] = ($counts[$finding->kind] ?? 0) + 1;
        }

        ksort($counts);

        foreach ($counts as $kind => $count) {
            $this->components->twoColumnDetail($kind, (string) $count);
        }

        $this->newLine();

        if ($findings === []) {
            $this->components->info('No findings.');
        } else {
            $this->components->warn(sprintf('Found %d finding(s).', count($findings)));
        }
    }

    /**
     * spec に定義されているが、対応するルートが無い操作を列挙する。
     *
     * @param  array<string, true>  $matched  ルートが見つかった操作
     * @return list<Finding>
     */
    private function missingRoutes(SpecRepository $spec, array $matched): array
    {
        $findings = [];

        foreach ($spec->load()->paths->getPaths() as $template => $pathItem) {
            foreach (array_keys($pathItem->getOperations()) as $method) {
                $name = strtoupper((string) $method).' '.$template;

                if (! isset($matched[$name])) {
                    $findings[] = new Finding(Finding::MISSING_ROUTE, $name, 'no route matches this operation', 0);
                }
            }
        }

        return $findings;
    }

    /**
     * ミドルウェアグループから throttle を取り除く。プロセス内で大量に送るため。
     */
    private function removeThrottle(Router $router): void
    {
        foreach ($router->getMiddlewareGroups() as $group => $middleware) {
            $kept = array_values(array_filter(
                $middleware,
                static fn (mixed $entry): bool => ! is_string($entry)
                    || (! str_starts_with($entry, 'throttle') && ! str_contains($entry, 'ThrottleRequests')),
            ));

            $router->middlewareGroup((string) $group, $kept);
        }
    }

    /**
     * 文字列として扱えるオプションの値を返す。
     *
     * @param  string  $name  オプション名
     * @param  string  $default  値が無いときの既定値
     */
    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    /**
     * 全リクエストに付けるヘッダーを組み立てる。
     *
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [];
        $token = $this->stringOption('token', '');

        if ($token === '') {
            $fromEnv = env('OPENAPI_PROBE_TOKEN', '');
            $token = is_string($fromEnv) ? $fromEnv : '';
        }

        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        foreach ((array) $this->option('header') as $raw) {
            if (! is_string($raw) || ! str_contains($raw, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $raw, 2);
            $headers[trim($name)] = trim($value);
        }

        return $headers;
    }
}
