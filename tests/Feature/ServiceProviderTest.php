<?php

namespace Karnoweb\SmsSender\Tests\Feature;

use Karnoweb\SmsSender\Facades\Sms;
use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_config_is_merged_with_default_values(): void
    {
        $this->assertNotNull(config('sms.default'));
        $this->assertIsArray(config('sms.drivers'));
        $this->assertIsArray(config('sms.failover'));
        $this->assertNotNull(config('sms.model'));
        $this->assertNotNull(config('sms.table'));
    }

    public function test_default_driver_is_null_in_default_config(): void
    {
        $this->assertEquals('null', config('sms.default'));
    }

    public function test_null_driver_is_defined_in_drivers(): void
    {
        $drivers = config('sms.drivers');
        $this->assertArrayHasKey('null', $drivers);
        $this->assertArrayHasKey('class', $drivers['null']);
    }

    public function test_null_driver_class_exists(): void
    {
        $class = config('sms.drivers.null.class');
        $this->assertTrue(class_exists($class), "Driver class [{$class}] does not exist.");
    }

    public function test_manager_is_bound_in_container(): void
    {
        $this->assertTrue($this->app->bound(SmsManager::class));
    }

    public function test_manager_is_singleton(): void
    {
        $first  = $this->app->make(SmsManager::class);
        $second = $this->app->make(SmsManager::class);
        $this->assertSame($first, $second);
    }

    public function test_manager_is_instance_of_sms_manager(): void
    {
        $manager = $this->app->make(SmsManager::class);
        $this->assertInstanceOf(SmsManager::class, $manager);
    }

    public function test_facade_resolves_to_manager(): void
    {
        $resolved = Sms::getFacadeRoot();
        $this->assertInstanceOf(SmsManager::class, $resolved);
    }

    public function test_facade_resolves_same_singleton(): void
    {
        $fromFacade    = Sms::getFacadeRoot();
        $fromContainer = $this->app->make(SmsManager::class);
        $this->assertSame($fromFacade, $fromContainer);
    }

    public function test_static_instance_returns_manager(): void
    {
        $instance = SmsManager::instance();
        $this->assertInstanceOf(SmsManager::class, $instance);
    }

    public function test_static_instance_returns_same_singleton(): void
    {
        $instance  = SmsManager::instance();
        $container = $this->app->make(SmsManager::class);
        $this->assertSame($instance, $container);
    }
}
