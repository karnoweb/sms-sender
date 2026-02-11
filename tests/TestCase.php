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

        $app['config']->set('sms.default', 'null');
        $app['config']->set('sms.drivers.null', [
            'class'       => \Karnoweb\SmsSender\Drivers\NullDriver::class,
            'credentials' => [],
        ]);
        $app['config']->set('sms.failover', $app['config']->get('sms.failover', []));
        $app['config']->set('sms.model', $app['config']->get('sms.model', \Karnoweb\SmsSender\Models\Sms::class));
        $app['config']->set('sms.table', $app['config']->get('sms.table', 'sms_messages'));
        $app['config']->set('sms.templates', [
            'login_otp'      => 'Your login code: {code}',
            'verify_phone'   => 'Phone verification code: {code}',
            'password_reset' => 'Password reset code: {code}',
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
