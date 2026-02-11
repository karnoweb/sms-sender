<?php

namespace Karnoweb\SmsSender\Support;

use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Contracts\SmsUsageHandler;

/**
 * Null usage handler — always allows (no limits).
 */
class NullUsageHandler implements SmsUsageHandler
{
    public function ensureUsable(string $driverName, SmsDriver $driver): void
    {
    }
}
