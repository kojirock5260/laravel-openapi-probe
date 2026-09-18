<?php

declare(strict_types=1);

return [
    // OpenAPI ドキュメントのパス。.json / .yaml / .yml を読める。
    'path' => env('OPENAPI_PROBE_PATH', base_path('openapi.yaml')),

    // ルート URI から取り除く接頭辞。ルートが /api/members で、ドキュメントが /members と書いている場合は 'api'。
    'base_path' => env('OPENAPI_PROBE_BASE_PATH', ''),
];
