<?php

namespace Karnoweb\SmsSender\Enums;

enum SmsTemplateEnum: string
{
    case LOGIN_OTP = 'کد ورود شما: {code}';
    case VERIFY_PHONE = 'کد تأیید شماره: {code}';
    case PASSWORD_RESET = 'کد بازیابی رمز عبور: {code}';

    /**
     * @return array<int, string>
     */
    public function placeholders(): array
    {
        preg_match_all('/\{(\w+)}/', $this->value, $matches);

        return $matches[1] ?? [];
    }
}
