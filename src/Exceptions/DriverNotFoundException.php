<?php

namespace Karnoweb\SmsSender\Exceptions;

use Illuminate\Support\Facades\Lang;

class DriverNotFoundException extends SmsException
{
    public static function make(string $driverName): self
    {
        $available = implode(', ', array_keys(config('sms.drivers', [])));

        return new self(Lang::get('sms-sender::messages.driver_not_found', [
            'driver'    => $driverName,
            'available' => $available,
        ]));
    }
}