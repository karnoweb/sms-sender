<?php

namespace Karnoweb\SmsSender\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Facades\Sms;
use Karnoweb\SmsSender\Models\Sms as SmsModel;
use Karnoweb\SmsSender\Tests\Fakes\DeliveryReportDriver;
use Karnoweb\SmsSender\Tests\Fakes\FailingDeliveryReportDriver;
use Karnoweb\SmsSender\Tests\TestCase;

class DeliveryReportTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
        DeliveryReportDriver::reset();
    }

    private function createSmsRecord(array $overrides = []): SmsModel
    {
        return SmsModel::create(array_merge([
            'driver'              => 'delivery_driver',
            'phone'               => '09120000000',
            'message'             => 'Test',
            'status'              => SmsSendStatusEnum::SENT,
            'provider_message_id' => 'msg_test_1',
        ], $overrides));
    }

    private function registerDeliveryDriver(): void
    {
        config([
            'sms.drivers.delivery_driver' => [
                'class'       => DeliveryReportDriver::class,
                'credentials' => [],
            ],
        ]);
    }

    private function registerFailingDeliveryDriver(): void
    {
        config([
            'sms.drivers.failing_delivery' => [
                'class'       => FailingDeliveryReportDriver::class,
                'credentials' => [],
            ],
        ]);
    }

    public function test_record_without_provider_id_is_skipped(): void
    {
        $this->registerDeliveryDriver();
        $this->createSmsRecord(['provider_message_id' => null]);

        $results = Sms::number('09120000000')->checkStatus();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['skipped']);
        $this->assertStringContainsString('provider_message_id', $results[0]['reason']);
    }

    public function test_driver_without_delivery_report_support_is_skipped(): void
    {
        $this->createSmsRecord(['driver' => 'null']);

        $results = Sms::number('09120000000')->checkStatus();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['skipped']);
        $this->assertStringContainsString('does not support', $results[0]['reason']);
    }

    public function test_delivered_status_updates_record(): void
    {
        $this->registerDeliveryDriver();
        $record = $this->createSmsRecord(['provider_message_id' => 'msg_delivered']);
        DeliveryReportDriver::$deliveryReports['msg_delivered'] = ['status' => 'delivered'];

        $results = Sms::number('09120000000')->checkStatus();

        $this->assertCount(1, $results);
        $this->assertEquals('delivered', $results[0]['new_status']);
        $this->assertEquals('sent', $results[0]['old_status']);
        $fresh = $record->fresh();
        $this->assertSame(SmsSendStatusEnum::DELIVERED, $fresh->status);
        $this->assertArrayHasKey('delivered_at', $fresh->metadata ?? []);
    }

    public function test_failed_status_updates_record(): void
    {
        $this->registerDeliveryDriver();
        $record = $this->createSmsRecord(['provider_message_id' => 'msg_failed']);
        DeliveryReportDriver::$deliveryReports['msg_failed'] = ['status' => 'failed'];

        $results = Sms::number('09120000000')->checkStatus();

        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['new_status']);
        $fresh = $record->fresh();
        $this->assertSame(SmsSendStatusEnum::FAILED, $fresh->status);
        $this->assertStringContainsString('failed by provider', $fresh->metadata['failure_reason']);
    }

    public function test_unknown_status_does_not_change_record(): void
    {
        $this->registerDeliveryDriver();
        $record = $this->createSmsRecord(['provider_message_id' => 'msg_unknown']);

        $results = Sms::number('09120000000')->checkStatus();

        $this->assertCount(1, $results);
        $this->assertEquals('unknown', $results[0]['new_status']);
        $fresh = $record->fresh();
        $this->assertSame(SmsSendStatusEnum::SENT, $fresh->status);
    }

    public function test_delivered_records_are_not_checked(): void
    {
        $this->registerDeliveryDriver();
        $this->createSmsRecord([
            'status'              => SmsSendStatusEnum::DELIVERED,
            'provider_message_id' => 'msg_already_done',
        ]);

        $results = Sms::number('09120000000')->checkStatus();

        $this->assertCount(0, $results);
    }

    public function test_no_records_returns_empty_results(): void
    {
        $results = Sms::number('09999999999')->checkStatus();
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
}
