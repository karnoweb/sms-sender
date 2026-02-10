<?php

namespace Karnoweb\SmsSender\Tests\Unit\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\SmsSender\Contracts\SmsUsageHandler;
use Karnoweb\SmsSender\Drivers\NullDriver;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Exceptions\DriverNotAvailableException;
use Karnoweb\SmsSender\Models\Sms as SmsModel;
use Karnoweb\SmsSender\Support\DefaultUsageHandler;
use Karnoweb\SmsSender\Tests\TestCase;

class DefaultUsageHandlerTest extends TestCase
{
    use RefreshDatabase;

    private DefaultUsageHandler $handler;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new DefaultUsageHandler();
    }

    private function createSmsRecord(string $driver, array $overrides = []): SmsModel
    {
        return SmsModel::create(array_merge([
            'driver'  => $driver,
            'phone'   => '09120000000',
            'message' => 'Test',
            'status'  => SmsSendStatusEnum::SENT,
        ], $overrides));
    }

    private function createMultipleSmsRecords(string $driver, int $count, array $overrides = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->createSmsRecord($driver, $overrides);
        }
    }

    public function test_no_usage_config_allows_all(): void
    {
        config(['sms.drivers.test_driver' => [
            'class'       => NullDriver::class,
            'credentials' => [],
        ]]);
        $driver = new NullDriver();
        $this->handler->ensureUsable('test_driver', $driver);
        $this->assertTrue(true);
    }

    public function test_disabled_driver_throws_exception(): void
    {
        config(['sms.drivers.test_driver' => [
            'class'       => NullDriver::class,
            'credentials' => [],
            'usage'       => ['enabled' => false],
        ]]);
        $this->expectException(DriverNotAvailableException::class);
        $this->expectExceptionMessage('disabled');
        $this->handler->ensureUsable('test_driver', new NullDriver());
    }

    public function test_enabled_driver_passes(): void
    {
        config(['sms.drivers.test_driver' => [
            'class'       => NullDriver::class,
            'credentials' => [],
            'usage'       => ['enabled' => true],
        ]]);
        $this->handler->ensureUsable('test_driver', new NullDriver());
        $this->assertTrue(true);
    }

    public function test_under_daily_limit_passes(): void
    {
        config(['sms.drivers.test_driver' => [
            'class'       => NullDriver::class,
            'credentials' => [],
            'usage'       => ['daily_limit' => 10],
        ]]);
        $this->createMultipleSmsRecords('test_driver', 5);
        $this->handler->ensureUsable('test_driver', new NullDriver());
        $this->assertTrue(true);
    }

    public function test_at_daily_limit_throws_exception(): void
    {
        config(['sms.drivers.test_driver' => [
            'class'       => NullDriver::class,
            'credentials' => [],
            'usage'       => ['daily_limit' => 5],
        ]]);
        $this->createMultipleSmsRecords('test_driver', 5);
        $this->expectException(DriverNotAvailableException::class);
        $this->expectExceptionMessage('daily limit');
        $this->handler->ensureUsable('test_driver', new NullDriver());
    }

    public function test_under_monthly_limit_passes(): void
    {
        config(['sms.drivers.test_driver' => [
            'class'       => NullDriver::class,
            'credentials' => [],
            'usage'       => ['monthly_limit' => 100],
        ]]);
        $this->createMultipleSmsRecords('test_driver', 50);
        $this->handler->ensureUsable('test_driver', new NullDriver());
        $this->assertTrue(true);
    }

    public function test_at_monthly_limit_throws_exception(): void
    {
        config(['sms.drivers.test_driver' => [
            'class'       => NullDriver::class,
            'credentials' => [],
            'usage'       => ['monthly_limit' => 10],
        ]]);
        $this->createMultipleSmsRecords('test_driver', 10);
        $this->expectException(DriverNotAvailableException::class);
        $this->expectExceptionMessage('monthly limit');
        $this->handler->ensureUsable('test_driver', new NullDriver());
    }

    public function test_implements_interface(): void
    {
        $this->assertInstanceOf(SmsUsageHandler::class, new DefaultUsageHandler());
    }
}
