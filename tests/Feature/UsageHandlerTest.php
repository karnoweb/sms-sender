<?php

namespace Karnoweb\SmsSender\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Contracts\SmsUsageHandler;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Exceptions\DriverNotAvailableException;
use Karnoweb\SmsSender\Facades\Sms;
use Karnoweb\SmsSender\Models\Sms as SmsModel;
use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Support\DefaultUsageHandler;
use Karnoweb\SmsSender\Support\NullUsageHandler;
use Karnoweb\SmsSender\Tests\TestCase;

class UsageRecordingDriver implements SmsDriver
{
    /** @var array<int, array{phone: string, message: string}> */
    public static array $sent = [];

    public function __construct(protected readonly array $config = []) {}

    public static function reset(): void
    {
        static::$sent = [];
    }

    /**
     * @param array<int, string> $recipients
     * @return array{message_id: string}
     */
    public function send(array $recipients, string $message, ?string $from = null): array
    {
        foreach ($recipients as $phone) {
            static::$sent[] = ['phone' => $phone, 'message' => $message];
        }

        return ['message_id' => 'usage-rec-' . uniqid()];
    }
}

class AlwaysRejectHandler implements SmsUsageHandler
{
    public function ensureUsable(string $driverName, SmsDriver $driver): void
    {
        throw new DriverNotAvailableException(
            "Driver [{$driverName}] rejected by custom handler.",
        );
    }
}

class UsageHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
        UsageRecordingDriver::reset();
    }

    private function rebuildManager(): void
    {
        $this->app->forgetInstance(SmsManager::class);
        Sms::clearResolvedInstances();
    }

    public function test_default_handler_is_null_usage_handler(): void
    {
        $manager = $this->app->make(SmsManager::class);
        $reflection = new \ReflectionProperty(SmsManager::class, 'usageHandler');
        $handler = $reflection->getValue($manager);
        $this->assertInstanceOf(NullUsageHandler::class, $handler);
    }

    public function test_null_handler_allows_sending(): void
    {
        config([
            'sms.default' => 'recording',
            'sms.drivers.recording' => [
                'class'       => UsageRecordingDriver::class,
                'credentials' => [],
            ],
        ]);
        $this->rebuildManager();
        Sms::message('تست')->number('09120000000')->send();
        $this->assertCount(1, UsageRecordingDriver::$sent);
    }

    public function test_default_usage_handler_from_config(): void
    {
        config(['sms.usage_handler' => DefaultUsageHandler::class]);
        $this->rebuildManager();
        $manager = $this->app->make(SmsManager::class);
        $reflection = new \ReflectionProperty(SmsManager::class, 'usageHandler');
        $handler = $reflection->getValue($manager);
        $this->assertInstanceOf(DefaultUsageHandler::class, $handler);
    }

    public function test_disabled_driver_triggers_failover(): void
    {
        config([
            'sms.usage_handler' => DefaultUsageHandler::class,
            'sms.default'       => 'disabled_driver',
            'sms.failover'      => ['recording'],
            'sms.drivers.disabled_driver' => [
                'class'       => UsageRecordingDriver::class,
                'credentials' => [],
                'usage'       => ['enabled' => false],
            ],
            'sms.drivers.recording' => [
                'class'       => UsageRecordingDriver::class,
                'credentials' => [],
            ],
        ]);
        $this->rebuildManager();
        Sms::message('تست failover')->number('09120000000')->send();
        $this->assertCount(1, UsageRecordingDriver::$sent);
        $this->assertDatabaseHas('sms_messages', [
            'driver' => 'recording',
            'status' => SmsSendStatusEnum::SENT->value,
        ]);
    }

    public function test_daily_limit_triggers_failover(): void
    {
        config([
            'sms.usage_handler' => DefaultUsageHandler::class,
            'sms.default'       => 'limited',
            'sms.failover'      => ['recording'],
            'sms.drivers.limited' => [
                'class'       => UsageRecordingDriver::class,
                'credentials' => [],
                'usage'       => ['daily_limit' => 3],
            ],
            'sms.drivers.recording' => [
                'class'       => UsageRecordingDriver::class,
                'credentials' => [],
            ],
        ]);
        $this->rebuildManager();
        for ($i = 0; $i < 3; $i++) {
            SmsModel::create([
                'driver'  => 'limited',
                'phone'   => '09120000000',
                'message' => "Test {$i}",
                'status'  => SmsSendStatusEnum::SENT,
            ]);
        }
        Sms::message('باید از recording برود')->number('09120000000')->send();
        $this->assertDatabaseHas('sms_messages', [
            'driver'  => 'recording',
            'message' => 'باید از recording برود',
        ]);
    }
}
