<?php

namespace Karnoweb\SmsSender\Tests\Unit\SmsManager;

use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Drivers\NullDriver;
use Karnoweb\SmsSender\Exceptions\InvalidDriverConfigurationException;
use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Tests\TestCase;

class DriverResolutionTest extends TestCase
{
    private SmsManager $manager;

    /** @var \ReflectionMethod */
    private \ReflectionMethod $resolveDriverMethod;

    /** @var \ReflectionMethod */
    private \ReflectionMethod $getDriverOrderMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->app->make(SmsManager::class);
        $this->resolveDriverMethod  = new \ReflectionMethod(SmsManager::class, 'resolveDriver');
        $this->getDriverOrderMethod = new \ReflectionMethod(SmsManager::class, 'getDriverOrder');
    }

    public function test_default_driver_is_first_in_order(): void
    {
        $order = $this->getDriverOrderMethod->invoke($this->manager);
        $this->assertEquals(['null'], $order);
    }

    public function test_failover_drivers_come_after_default(): void
    {
        config([
            'sms.default'  => 'driver_a',
            'sms.failover' => ['driver_b', 'driver_c'],
        ]);
        $order = $this->getDriverOrderMethod->invoke($this->manager);
        $this->assertEquals(['driver_a', 'driver_b', 'driver_c'], $order);
    }

    public function test_duplicate_drivers_are_removed(): void
    {
        config([
            'sms.default'  => 'driver_a',
            'sms.failover' => ['driver_a', 'driver_b'],
        ]);
        $order = $this->getDriverOrderMethod->invoke($this->manager);
        $this->assertEquals(['driver_a', 'driver_b'], $order);
    }

    public function test_empty_config_throws_exception(): void
    {
        config([
            'sms.default'  => null,
            'sms.failover' => [],
        ]);
        $this->expectException(InvalidDriverConfigurationException::class);
        $this->expectExceptionMessage('No SMS driver configured');
        $this->getDriverOrderMethod->invoke($this->manager);
    }

    public function test_empty_default_with_failover_still_works(): void
    {
        config([
            'sms.default'  => '',
            'sms.failover' => ['driver_a'],
        ]);
        $order = $this->getDriverOrderMethod->invoke($this->manager);
        $this->assertEquals(['driver_a'], $order);
    }

    public function test_resolve_null_driver(): void
    {
        $driver = $this->resolveDriverMethod->invoke($this->manager, 'null');
        $this->assertInstanceOf(NullDriver::class, $driver);
        $this->assertInstanceOf(SmsDriver::class, $driver);
    }

    public function test_resolve_undefined_driver_throws_exception(): void
    {
        $this->expectException(InvalidDriverConfigurationException::class);
        $this->expectExceptionMessage('not defined');
        $this->resolveDriverMethod->invoke($this->manager, 'nonexistent');
    }

    public function test_resolve_driver_without_class_throws_exception(): void
    {
        config(['sms.drivers.broken' => ['credentials' => []]]);
        $this->expectException(InvalidDriverConfigurationException::class);
        $this->expectExceptionMessage('not specified');
        $this->resolveDriverMethod->invoke($this->manager, 'broken');
    }

    public function test_resolve_driver_with_nonexistent_class_throws_exception(): void
    {
        config(['sms.drivers.broken' => [
            'class'       => 'App\\NonExistent\\Driver',
            'credentials' => [],
        ]]);
        $this->expectException(InvalidDriverConfigurationException::class);
        $this->expectExceptionMessage('does not exist');
        $this->resolveDriverMethod->invoke($this->manager, 'broken');
    }

    public function test_resolve_driver_not_implementing_interface_throws_exception(): void
    {
        config(['sms.drivers.invalid' => [
            'class'       => \stdClass::class,
            'credentials' => [],
        ]]);
        $this->expectException(InvalidDriverConfigurationException::class);
        $this->expectExceptionMessage('must implement');
        $this->resolveDriverMethod->invoke($this->manager, 'invalid');
    }

    public function test_resolve_driver_passes_credentials(): void
    {
        config(['sms.drivers.test_driver' => [
            'class'       => NullDriver::class,
            'credentials' => ['api_key' => 'test_123'],
        ]]);
        $driver = $this->resolveDriverMethod->invoke($this->manager, 'test_driver');
        $this->assertInstanceOf(NullDriver::class, $driver);
    }
}
