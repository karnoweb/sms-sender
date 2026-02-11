<?php

namespace Karnoweb\SmsSender\Enums;

enum SmsTemplateEnum: string
{
    case LOGIN_OTP = 'login_otp';
    case VERIFY_PHONE = 'verify_phone';
    case PASSWORD_RESET = 'password_reset';

    public function templateText(): string
    {
        return (string) __("sms-sender::templates.{$this->value}");
    }

    /**
     * @return array<int, string>
     */
    public function placeholders(): array
    {
        preg_match_all('/\{(\w+)}/', $this->templateText(), $matches);

        return $matches[1] ?? [];
    }
}
