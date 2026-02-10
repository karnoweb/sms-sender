<?php

namespace Karnoweb\SmsSender\Tests\Unit\Support;

use Karnoweb\SmsSender\Contracts\SmsUsageHandler;
use Karnoweb\SmsSender\Drivers\NullDriver;
use Karnoweb\SmsSender\Support\NullUsageHandler;
use Karnoweb\SmsSender\Tests\TestCase;

class NullUsageHandlerTest extends TestCase
{
    public function test_implements_interface(): void
    {
        $handler = new NullUsageHandler();
        $this->assertInstanceOf(SmsUsageHandler::class, $handler);
    }

    public function test_ensure_usable_never_throws(): void
    {
        $handler = new NullUsageHandler();
        $driver  = new NullDriver();
        $handler->ensureUsable('any_driver', $driver);
        $handler->ensureUsable('nonexistent', $driver);
        $handler->ensureUsable('', $driver);
        $this->assertTrue(true);
    }
}
