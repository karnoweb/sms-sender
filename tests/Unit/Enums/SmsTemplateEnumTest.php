<?php

namespace Karnoweb\SmsSender\Tests\Unit\Enums;

use Karnoweb\SmsSender\Enums\SmsTemplateEnum;
use Karnoweb\SmsSender\Tests\TestCase;

class SmsTemplateEnumTest extends TestCase
{
    public function test_all_cases_have_string_values(): void
    {
        foreach (SmsTemplateEnum::cases() as $case) {
            $this->assertIsString($case->value);
            $this->assertNotEmpty($case->value);
        }
    }

    public function test_all_templates_contain_placeholders(): void
    {
        foreach (SmsTemplateEnum::cases() as $case) {
            $this->assertMatchesRegularExpression('/\{\w+}/', $case->value);
        }
    }

    public function test_login_otp_has_code_placeholder(): void
    {
        $this->assertContains('code', SmsTemplateEnum::LOGIN_OTP->placeholders());
    }

    public function test_verify_phone_has_code_placeholder(): void
    {
        $this->assertContains('code', SmsTemplateEnum::VERIFY_PHONE->placeholders());
    }

    public function test_password_reset_has_code_placeholder(): void
    {
        $this->assertContains('code', SmsTemplateEnum::PASSWORD_RESET->placeholders());
    }

    public function test_placeholders_returns_array_of_strings(): void
    {
        foreach (SmsTemplateEnum::cases() as $case) {
            $placeholders = $case->placeholders();
            $this->assertIsArray($placeholders);
            foreach ($placeholders as $placeholder) {
                $this->assertIsString($placeholder);
            }
        }
    }

    public function test_can_create_from_value(): void
    {
        $template = SmsTemplateEnum::from('کد ورود شما: {code}');
        $this->assertSame(SmsTemplateEnum::LOGIN_OTP, $template);
    }

    public function test_try_from_returns_null_for_invalid(): void
    {
        $template = SmsTemplateEnum::tryFrom('invalid template');
        $this->assertNull($template);
    }
}
