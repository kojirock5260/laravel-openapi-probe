<?php

declare(strict_types=1);

use cebe\openapi\spec\OpenApi;
use Kojirock5260\OpenApiProbe\Spec\Document;
use Kojirock5260\OpenApiProbe\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * fixture の OpenAPI ドキュメントを読み込む。
 *
 * @param  string  $file  fixtures 配下のファイル名
 */
function fixtureDocument(string $file = 'openapi.yaml'): OpenApi
{
    return Document::load(__DIR__.'/fixtures/'.$file);
}
