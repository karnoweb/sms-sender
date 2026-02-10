<?php

namespace Karnoweb\SmsSender\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Facades\Sms;
use Karnoweb\SmsSender\Models\Sms as DefaultSmsModel;
use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Tests\Fakes\RecordingDriver;
use Karnoweb\SmsSender\Tests\TestCase;

class CustomSmsModel extends DefaultSmsModel
{
    public static bool $customMethodCalled = false;

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            static::$customMethodCalled = true;
        });
    }

    public static function resetCustomFlag(): void
    {
        static::$customMethodCalled = false;
    }
}

class CustomModelTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
        RecordingDriver::reset();
        CustomSmsModel::resetCustomFlag();
    }

    private function rebuildManager(): void
    {
        $this->app->forgetInstance(SmsManager::class);
        Sms::clearResolvedInstances();
    }

    public function test_custom_model_is_used_when_configured(): void
    {
        config([
            'sms.model'   => CustomSmsModel::class,
            'sms.default' => 'recording',
            'sms.drivers.recording' => [
                'class'       => RecordingDriver::class,
                'credentials' => [],
            ],
        ]);
        $this->rebuildManager();

        Sms::message('تست مدل سفارشی')
            ->number('09120000000')
            ->send();

        $this->assertTrue(CustomSmsModel::$customMethodCalled);
        $this->assertDatabaseHas('sms_messages', [
            'message' => 'تست مدل سفارشی',
        ]);
        $record = CustomSmsModel::first();
        $this->assertInstanceOf(CustomSmsModel::class, $record);
    }

    public function test_default_model_used_when_not_configured(): void
    {
        config([
            'sms.default' => 'recording',
            'sms.drivers.recording' => [
                'class'       => RecordingDriver::class,
                'credentials' => [],
            ],
        ]);
        $this->rebuildManager();

        Sms::message('تست مدل پیش‌فرض')
            ->number('09120000000')
            ->send();

        $record = DefaultSmsModel::first();
        $this->assertInstanceOf(DefaultSmsModel::class, $record);
    }
}
