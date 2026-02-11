<?php

namespace Karnoweb\SmsSender\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Enums\SmsTemplateEnum;
use Karnoweb\SmsSender\Exceptions\InvalidDriverConfigurationException;
use Karnoweb\SmsSender\Exceptions\InvalidPhoneNumberException;
use Karnoweb\SmsSender\Facades\Sms;
use Karnoweb\SmsSender\Models\Sms as SmsModel;
use Karnoweb\SmsSender\Tests\TestCase;

class SendTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_send_without_recipients_throws_exception(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('empty');
        Sms::message('سلام')->send();
    }

    public function test_send_without_message_throws_exception(): void
    {
        $this->expectException(InvalidDriverConfigurationException::class);
        $this->expectExceptionMessage('No message or template provided');
        Sms::number('09120000000')->send();
    }

    public function test_send_without_anything_throws_exception(): void
    {
        $this->expectException(InvalidDriverConfigurationException::class);
        Sms::send();
    }

    public function test_send_simple_message(): void
    {
        Sms::message('سلام دنیا')
            ->number('09120000000')
            ->send();

        $this->assertDatabaseCount('sms_messages', 1);
        $this->assertDatabaseHas('sms_messages', [
            'phone'   => '09120000000',
            'message' => 'سلام دنیا',
            'driver'  => 'null',
            'status'  => SmsSendStatusEnum::SENT->value,
        ]);
    }

    public function test_send_to_multiple_numbers(): void
    {
        Sms::message('اطلاع‌رسانی')
            ->numbers(['09120000000', '09130000000', '09140000000'])
            ->send();

        $this->assertDatabaseCount('sms_messages', 3);
    }

    public function test_duplicate_numbers_are_sent_once(): void
    {
        Sms::message('تست')
            ->number('09120000000')
            ->number('09120000000')
            ->numbers(['09120000000'])
            ->send();

        $this->assertDatabaseCount('sms_messages', 1);
    }

    public function test_send_otp_with_template(): void
    {
        Sms::otp(SmsTemplateEnum::LOGIN_OTP)
            ->input('code', '1234')
            ->number('09120000000')
            ->send();

        $this->assertDatabaseHas('sms_messages', [
            'phone'    => '09120000000',
            'message'  => 'Your login code: 1234',
            'template' => 'LOGIN_OTP',
            'status'   => SmsSendStatusEnum::SENT->value,
        ]);
    }

    public function test_otp_template_inputs_are_stored(): void
    {
        Sms::otp(SmsTemplateEnum::LOGIN_OTP)
            ->input('code', '5678')
            ->number('09120000000')
            ->send();

        $record = SmsModel::first();
        $this->assertEquals(['code' => '5678'], $record->inputs);
    }

    public function test_message_takes_priority_over_template(): void
    {
        Sms::otp(SmsTemplateEnum::LOGIN_OTP)
            ->input('code', '1234')
            ->message('پیام ساده')
            ->number('09120000000')
            ->send();

        $this->assertDatabaseHas('sms_messages', [
            'message' => 'پیام ساده',
        ]);
    }

    public function test_state_is_reset_after_send(): void
    {
        Sms::message('اول')
            ->number('09120000000')
            ->send();

        $this->expectException(InvalidDriverConfigurationException::class);
        Sms::send();
    }

    public function test_state_is_reset_even_after_exception(): void
    {
        try {
            Sms::message('تست')->send();
        } catch (InvalidPhoneNumberException) {
        }

        $this->expectException(InvalidDriverConfigurationException::class);
        $this->expectExceptionMessage('No message or template provided');
        Sms::number('09120000000')->send();
    }

    public function test_sequential_sends_are_independent(): void
    {
        Sms::message('پیام اول')
            ->number('09120000000')
            ->send();

        Sms::message('پیام دوم')
            ->number('09130000000')
            ->send();

        $this->assertDatabaseCount('sms_messages', 2);
        $this->assertDatabaseHas('sms_messages', [
            'phone'   => '09120000000',
            'message' => 'پیام اول',
        ]);
        $this->assertDatabaseHas('sms_messages', [
            'phone'   => '09130000000',
            'message' => 'پیام دوم',
        ]);
    }

    public function test_facade_sends_successfully(): void
    {
        Sms::message('تست Facade')
            ->number('09120000000')
            ->send();

        $this->assertDatabaseHas('sms_messages', [
            'message' => 'تست Facade',
        ]);
    }

    public function test_record_stores_driver_name(): void
    {
        Sms::message('تست')->number('09120000000')->send();
        $record = SmsModel::first();
        $this->assertEquals('null', $record->driver);
    }

    public function test_record_without_template_has_null_template(): void
    {
        Sms::message('ساده')->number('09120000000')->send();
        $record = SmsModel::first();
        $this->assertNull($record->template);
        $this->assertNull($record->inputs);
    }
}
