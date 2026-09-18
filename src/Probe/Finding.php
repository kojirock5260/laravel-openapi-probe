<?php

declare(strict_types=1);

namespace Kojirock5260\OpenApiProbe\Probe;

/**
 * 探査で見つかった問題 1 件。
 */
final readonly class Finding
{
    /** 5xx が返った */
    public const string SERVER_ERROR = 'server-error';

    /** 返ったステータスコードが spec に定義されていない */
    public const string UNDOCUMENTED_STATUS = 'undocumented-status';

    /** 応答が spec のスキーマに合わない */
    public const string RESPONSE_MISMATCH = 'response-mismatch';

    /** spec に反するリクエストが 2xx で受理された */
    public const string VALIDATION_BYPASS = 'validation-bypass';

    /** spec に適合するリクエストが 400 / 422 で拒否された */
    public const string SPEC_REJECTED = 'spec-rejected';

    /** spec に定義された操作に対応するルートが無い */
    public const string MISSING_ROUTE = 'missing-route';

    /**
     * @param  string  $kind  問題の種類
     * @param  string  $operation  "GET /members" 形式の操作名
     * @param  string  $case  ケースの説明
     * @param  int  $status  返ったステータスコード。ルートが無い場合は 0
     * @param  list<string>  $messages  詳細
     */
    public function __construct(
        public string $kind,
        public string $operation,
        public string $case,
        public int $status,
        public array $messages = [],
    ) {}
}
