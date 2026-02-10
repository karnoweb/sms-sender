<?php

namespace Karnoweb\SmsSender\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Enums\SmsTemplateEnum;
use Karnoweb\SmsSender\Facades\Sms;
use Karnoweb\SmsSender\Models\Sms as SmsModel;
use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Support\DefaultUsageHandler;
use Karnoweb\SmsSender\Tests\Fakes\DeliveryReportDriver;
use Karnoweb\SmsSender\Tests\Fakes\FailingDriver;
use Karnoweb\SmsSender\Tests\Fakes\RecordingDriver;
use Karnoweb\SmsSender\Tests\TestCase;

class EndToEndTest extends TestCase
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
        DeliveryReportDriver::reset();
    }

    private function rebuildManager(): void
    {
        $this->app->forgetInstance(SmsManager::class);
        Sms::clearResolvedInstances();
    }

    public function test_full_lifecycle_send_and_check_delivery(): void
    {
        config([
            'sms.default' => 'delivery',
            'sms.drivers.delivery' => [
                'class'       => DeliveryReportDriver::class,
                'credentials' => [],
            ],
        ]);
        $this->rebuildManager();

        Sms::otp(SmsTemplateEnum::LOGIN_OTP)
            ->input('code', '9876')
            ->number('09120000000')
            ->send();

        $this->assertDatabaseCount('sms_messages', 1);
        $record = SmsModel::first();
        $this->assertEquals('delivery', $record->driver);
        $this->assertEquals('LOGIN_OTP', $record->template);
        $this->assertEquals(['code' => '9876'], $record->inputs);
        $this->assertEquals('09120000000', $record->phone);
        $this->assertStringContainsString('9876', $record->message);
        $this->assertSame(SmsSendStatusEnum::SENT, $record->status);

        $providerMsgId = DeliveryReportDriver::lastProviderMessageId();
        $record->update(['provider_message_id' => $providerMsgId]);
        DeliveryReportDriver::$deliveryReports[$providerMsgId] = ['status' => 'delivered'];

        $results = Sms::number('09120000000')->checkStatus();
        $this->assertCount(1, $results);
        $this->assertEquals('delivered', $results[0]['new_status']);
        $fresh = $record->fresh();
        $this->assertSame(SmsSendStatusEnum::DELIVERED, $fresh->status);
        $this->assertArrayHasKey('delivered_at', $fresh->metadata);
    }

    public function test_sequential_sends_are_completely_independent(): void
    {
        config([
            'sms.default' => 'recording',
            'sms.drivers.recording' => [
                'class'       => RecordingDriver::class,
                'credentials' => [],
            ],
        ]);
        $this->rebuildManager();

        Sms::otp(SmsTemplateEnum::LOGIN_OTP)
            ->input('code', '1111')
            ->number('09121111111')
            ->send();

        Sms::message('پیام ساده')
            ->number('09122222222')
            ->send();

        Sms::otp(SmsTemplateEnum::VERIFY_PHONE)
            ->input('code', '3333')
            ->numbers(['09123333333', '09124444444'])
            ->send();

        $this->assertDatabaseCount('sms_messages', 4);
        $this->assertDatabaseHas('sms_messages', [
            'phone'    => '09121111111',
            'template' => 'LOGIN_OTP',
        ]);
        $sms2 = SmsModel::where('phone', '09122222222')->first();
        $this->assertEquals('پیام ساده', $sms2->message);
        $this->assertNull($sms2->template);
    }

    public function test_facade_and_instance_and_di_all_work(): void
    {
        config([
            'sms.default' => 'recording',
            'sms.drivers.recording' => [
                'class'       => RecordingDriver::class,
                'credentials' => [],
            ],
        ]);
        $this->rebuildManager();

        Sms::message('از Facade')->number('09121111111')->send();
        SmsManager::instance()
            ->message('از Instance')
            ->number('09122222222')
            ->send();
        $manager = $this->app->make(SmsManager::class);
        $manager->message('از DI')->number('09123333333')->send();

        $this->assertDatabaseCount('sms_messages', 3);
        $this->assertEquals(3, SmsModel::where('driver', 'recording')->count());
    }
}
