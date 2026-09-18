<?php

declare(strict_types=1);

use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;
use Kojirock5260\OpenApiProbe\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * fixture の OpenAPI ドキュメントを指す SpecRepository を生成する。
 *
 * @param  string  $file  fixtures 配下のファイル名
 */
function specRepository(string $file = 'openapi.yaml'): SpecRepository
{
    return new SpecRepository(__DIR__.'/fixtures/'.$file);
}
