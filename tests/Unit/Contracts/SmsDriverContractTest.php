<?php

namespace Karnoweb\SmsSender\Tests\Unit\Contracts;

use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Drivers\NullDriver;
use Karnoweb\SmsSender\Tests\TestCase;
use ReflectionMethod;

class SmsDriverContractTest extends TestCase
{
    public function test_sms_driver_interface_exists(): void
    {
        $this->assertTrue(interface_exists(SmsDriver::class));
    }

    public function test_sms_driver_has_send_method(): void
    {
        $this->assertTrue(method_exists(SmsDriver::class, 'send'));
    }

    public function test_send_method_signature(): void
    {
        $method = new ReflectionMethod(SmsDriver::class, 'send');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('phone', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()?->getName());
        $this->assertEquals('message', $params[1]->getName());
        $this->assertEquals('string', $params[1]->getType()?->getName());
        $this->assertEquals('void', $method->getReturnType()?->getName());
    }

    public function test_null_driver_implements_sms_driver(): void
    {
        $driver = new NullDriver();
        $this->assertInstanceOf(SmsDriver::class, $driver);
    }

    public function test_null_driver_does_not_implement_delivery_report(): void
    {
        $driver = new NullDriver();
        $this->assertNotInstanceOf(DeliveryReportFetcher::class, $driver);
    }

    public function test_null_driver_send_does_not_throw(): void
    {
        $driver = new NullDriver();
        $driver->send('09120000000', 'Test message');
        $this->assertTrue(true);
    }

    public function test_null_driver_accepts_config(): void
    {
        $config = ['api_key' => 'test_key'];
        $driver = new NullDriver($config);
        $this->assertInstanceOf(SmsDriver::class, $driver);
    }
}
