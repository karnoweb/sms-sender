<?php

namespace Karnoweb\SmsSender\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Facades\Sms;
use Karnoweb\SmsSender\Models\Sms as SmsModel;
use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Tests\Fakes\RecordingDriver;
use Karnoweb\SmsSender\Tests\TestCase;

class ConcurrencyTest extends TestCase
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
        config([
            'sms.default' => 'recording',
            'sms.drivers.recording' => [
                'class'       => RecordingDriver::class,
                'credentials' => [],
            ],
        ]);
        $this->app->forgetInstance(SmsManager::class);
        Sms::clearResolvedInstances();
    }

    public function test_rapid_sequential_sends_are_independent(): void
    {
        $phones = [
            '09121111111',
            '09122222222',
            '09123333333',
            '09124444444',
            '09125555555',
        ];

        foreach ($phones as $i => $phone) {
            Sms::message("پیام شماره {$i}")
                ->number($phone)
                ->send();
        }

        $this->assertDatabaseCount('sms_messages', 5);
        foreach ($phones as $i => $phone) {
            $this->assertDatabaseHas('sms_messages', [
                'phone'   => $phone,
                'message' => "پیام شماره {$i}",
            ]);
            $this->assertEquals(1, SmsModel::where('phone', $phone)->count());
        }
    }

    public function test_singleton_remains_usable_after_many_operations(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $phone = '0912' . str_pad((string) $i, 7, '0', STR_PAD_LEFT);
            Sms::message("پیام {$i}")
                ->number($phone)
                ->send();
        }

        $this->assertDatabaseCount('sms_messages', 20);
        $this->assertEquals(
            20,
            SmsModel::where('status', SmsSendStatusEnum::SENT)->count(),
        );
    }
}
