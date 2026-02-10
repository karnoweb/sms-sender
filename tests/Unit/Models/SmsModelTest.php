<?php

namespace Karnoweb\SmsSender\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Models\Sms;
use Karnoweb\SmsSender\Tests\TestCase;

class SmsModelTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }

    private function createSms(array $overrides = []): Sms
    {
        return Sms::create(array_merge([
            'driver'  => 'null',
            'phone'   => '09120000000',
            'message' => 'Test message',
            'status'  => SmsSendStatusEnum::PENDING,
        ], $overrides));
    }

    public function test_can_create_sms_record(): void
    {
        $sms = $this->createSms();
        $this->assertDatabaseHas('sms_messages', ['id' => $sms->id, 'phone' => '09120000000']);
    }

    public function test_table_name_comes_from_config(): void
    {
        $sms = new Sms();
        $this->assertEquals(config('sms.table', 'sms_messages'), $sms->getTable());
    }

    public function test_fillable_fields(): void
    {
        $sms = $this->createSms([
            'driver'              => 'kavenegar',
            'template'            => 'LOGIN_OTP',
            'inputs'              => ['code' => '1234'],
            'phone'               => '09130000000',
            'message'             => 'کد ورود: 1234',
            'provider_message_id' => 'msg_abc123',
            'status'              => SmsSendStatusEnum::SENT,
            'metadata'            => ['response_code' => 200],
        ]);
        $this->assertEquals('kavenegar', $sms->driver);
        $this->assertEquals('LOGIN_OTP', $sms->template);
        $this->assertEquals('09130000000', $sms->phone);
        $this->assertEquals('msg_abc123', $sms->provider_message_id);
    }

    public function test_status_is_cast_to_enum(): void
    {
        $sms = $this->createSms();
        $fresh = $sms->fresh();
        $this->assertInstanceOf(SmsSendStatusEnum::class, $fresh->status);
        $this->assertSame(SmsSendStatusEnum::PENDING, $fresh->status);
    }

    public function test_inputs_is_cast_to_array(): void
    {
        $inputs = ['code' => '5678', 'name' => 'علی'];
        $sms = $this->createSms(['inputs' => $inputs]);
        $fresh = $sms->fresh();
        $this->assertIsArray($fresh->inputs);
        $this->assertEquals('5678', $fresh->inputs['code']);
    }

    public function test_metadata_is_cast_to_array(): void
    {
        $metadata = ['api_response' => 'OK'];
        $sms = $this->createSms(['metadata' => $metadata]);
        $fresh = $sms->fresh();
        $this->assertIsArray($fresh->metadata);
        $this->assertEquals('OK', $fresh->metadata['api_response']);
    }

    public function test_nullable_fields_default_to_null(): void
    {
        $sms = $this->createSms();
        $fresh = $sms->fresh();
        $this->assertNull($fresh->template);
        $this->assertNull($fresh->inputs);
        $this->assertNull($fresh->provider_message_id);
        $this->assertNull($fresh->metadata);
    }

    public function test_scope_checkable(): void
    {
        $this->createSms(['phone' => '09121111111', 'status' => SmsSendStatusEnum::PENDING]);
        $this->createSms(['phone' => '09122222222', 'status' => SmsSendStatusEnum::SENT]);
        $this->createSms(['phone' => '09123333333', 'status' => SmsSendStatusEnum::DELIVERED]);
        $this->createSms(['phone' => '09124444444', 'status' => SmsSendStatusEnum::FAILED]);
        $checkable = Sms::checkable()->get();
        $this->assertCount(2, $checkable);
    }

    public function test_scope_for_driver(): void
    {
        $this->createSms(['driver' => 'kavenegar']);
        $this->createSms(['driver' => 'kavenegar']);
        $this->createSms(['driver' => 'melipayamak']);
        $this->assertEquals(2, Sms::forDriver('kavenegar')->count());
        $this->assertEquals(1, Sms::forDriver('melipayamak')->count());
    }

    public function test_scope_for_phone(): void
    {
        $this->createSms(['phone' => '09120000000']);
        $this->createSms(['phone' => '09120000000']);
        $this->createSms(['phone' => '09130000000']);
        $this->assertEquals(2, Sms::forPhone('09120000000')->count());
    }

    public function test_is_terminal(): void
    {
        $pending   = $this->createSms(['status' => SmsSendStatusEnum::PENDING]);
        $delivered = $this->createSms(['status' => SmsSendStatusEnum::DELIVERED]);
        $failed    = $this->createSms(['status' => SmsSendStatusEnum::FAILED]);
        $this->assertFalse($pending->isTerminal());
        $this->assertTrue($delivered->isTerminal());
        $this->assertTrue($failed->isTerminal());
    }

    public function test_has_provider_message_id(): void
    {
        $without = $this->createSms();
        $with    = $this->createSms(['provider_message_id' => 'msg_123']);
        $this->assertFalse($without->hasProviderMessageId());
        $this->assertTrue($with->hasProviderMessageId());
    }

    public function test_mark_as_sent_without_provider_id(): void
    {
        $sms = $this->createSms();
        $sms->markAsSent();
        $fresh = $sms->fresh();
        $this->assertSame(SmsSendStatusEnum::SENT, $fresh->status);
        $this->assertNull($fresh->provider_message_id);
    }

    public function test_mark_as_sent_with_provider_id(): void
    {
        $sms = $this->createSms();
        $sms->markAsSent('msg_xyz789');
        $fresh = $sms->fresh();
        $this->assertSame(SmsSendStatusEnum::SENT, $fresh->status);
        $this->assertEquals('msg_xyz789', $fresh->provider_message_id);
    }

    public function test_mark_as_failed_without_reason(): void
    {
        $sms = $this->createSms();
        $sms->markAsFailed();
        $this->assertSame(SmsSendStatusEnum::FAILED, $sms->fresh()->status);
    }

    public function test_mark_as_failed_with_reason(): void
    {
        $sms = $this->createSms();
        $sms->markAsFailed('Connection timeout');
        $fresh = $sms->fresh();
        $this->assertSame(SmsSendStatusEnum::FAILED, $fresh->status);
        $this->assertEquals('Connection timeout', $fresh->metadata['failure_reason']);
        $this->assertArrayHasKey('failed_at', $fresh->metadata);
    }

    public function test_mark_as_failed_preserves_existing_metadata(): void
    {
        $sms = $this->createSms(['metadata' => ['attempt' => 1]]);
        $sms->markAsFailed('Timeout');
        $fresh = $sms->fresh();
        $this->assertEquals(1, $fresh->metadata['attempt']);
        $this->assertEquals('Timeout', $fresh->metadata['failure_reason']);
    }

    public function test_mark_as_delivered(): void
    {
        $sms = $this->createSms(['status' => SmsSendStatusEnum::SENT]);
        $sms->markAsDelivered();
        $fresh = $sms->fresh();
        $this->assertSame(SmsSendStatusEnum::DELIVERED, $fresh->status);
        $this->assertArrayHasKey('delivered_at', $fresh->metadata);
    }
}
