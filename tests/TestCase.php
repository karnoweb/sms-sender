<?php

namespace Karnoweb\SmsSender\Tests;

use Karnoweb\SmsSender\SmsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            SmsServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Sms' => \Karnoweb\SmsSender\Facades\Sms::class,
        ];
    }
}
