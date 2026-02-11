<?php

namespace Karnoweb\SmsSender\Exceptions;

use Illuminate\Support\Facades\Lang;

class InvalidPhoneNumberException extends SmsException
{
    public static function invalidFormat(string $number): self
    {
        return new self(Lang::get('sms-sender::messages.invalid_phone_format', ['number' => $number]));
    }

    public static function empty(): self
    {
        return new self(Lang::get('sms-sender::messages.invalid_phone_empty'));
    }
}