<?php

declare(strict_types=1);

namespace Obelaw\SystemOne\Clef\Tests;

use Obelaw\SystemOne\Clef\ClefServiceProvider;
use Obelaw\SystemOne\SystemOneServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SystemOneServiceProvider::class,
            ClefServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('systemone.default', 'cloudflare');
        $app['config']->set('clef', [
            'account_id' => 'test-cf-account-12345',
            'api_token' => 'test-cf-token-secret',
            'model' => '@cf/cloudflare/clef',
            'base_url' => 'https://api.cloudflare.com/client/v4',
            'timeout' => 30,
        ]);
    }
}
