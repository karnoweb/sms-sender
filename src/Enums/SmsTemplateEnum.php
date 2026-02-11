<?php

namespace Karnoweb\SmsSender\Enums;

enum SmsTemplateEnum: string
{
    case LOGIN_OTP = 'login_otp';
    case VERIFY_PHONE = 'verify_phone';
    case PASSWORD_RESET = 'password_reset';

    /**
     * Resolve template text: app config first, then package lang (if published).
     * Apps should inject templates via config('sms.templates') or use SmsManager::template($key, $body).
     */
    public function templateText(): string
    {
        $fromConfig = config('sms.templates.' . $this->value);
        if (is_string($fromConfig) && $fromConfig !== '') {
            return $fromConfig;
        }

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
