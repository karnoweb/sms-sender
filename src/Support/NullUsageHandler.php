<?php

namespace Karnoweb\SmsSender\Support;

use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Contracts\SmsUsageHandler;

/**
 * پیاده‌سازی پیش‌فرض UsageHandler — همیشه اجازه می‌دهد.
 */
class NullUsageHandler implements SmsUsageHandler
{
    public function ensureUsable(string $driverName, SmsDriver $driver): void
    {
        // عمداً خالی — بدون محدودیت
    }
}
