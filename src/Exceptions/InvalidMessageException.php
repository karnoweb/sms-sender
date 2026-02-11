<?php

namespace Karnoweb\SmsSender\Exceptions;

use Illuminate\Support\Facades\Lang;

class InvalidMessageException extends SmsException
{
    public static function empty(): self
    {
        return new self(Lang::get('sms-sender::messages.invalid_message_empty'));
    }

    public static function tooLong(int $length, int $max): self
    {
        return new self(Lang::get('sms-sender::messages.invalid_message_too_long', [
            'length' => $length,
            'max'    => $max,
        ]));
    }
}
