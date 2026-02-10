<?php

namespace Karnoweb\SmsSender\Tests\Unit\Contracts;

use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Contracts\SmsUsageHandler;
use Karnoweb\SmsSender\Tests\TestCase;
use ReflectionMethod;

class SmsUsageHandlerContractTest extends TestCase
{
    public function test_interface_exists(): void
    {
        $this->assertTrue(interface_exists(SmsUsageHandler::class));
    }

    public function test_has_ensure_usable_method(): void
    {
        $this->assertTrue(method_exists(SmsUsageHandler::class, 'ensureUsable'));
    }

    public function test_ensure_usable_signature(): void
    {
        $method = new ReflectionMethod(SmsUsageHandler::class, 'ensureUsable');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('driverName', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()?->getName());
        $this->assertEquals('driver', $params[1]->getName());
        $this->assertEquals(SmsDriver::class, $params[1]->getType()?->getName());
        $this->assertEquals('void', $method->getReturnType()?->getName());
    }
}
