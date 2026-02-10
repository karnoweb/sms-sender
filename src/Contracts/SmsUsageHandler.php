<?php

namespace Karnoweb\SmsSender\Contracts;

/**
 * قرارداد کنترل مصرف و دسترسی درایورهای SMS.
 *
 * @throws \Karnoweb\SmsSender\Exceptions\DriverNotAvailableException اگر درایور قابل استفاده نباشد
 */
interface SmsUsageHandler
{
    /**
     * بررسی اینکه آیا درایور مشخص‌شده قابل استفاده هست یا خیر.
     *
     * @param string    $driverName نام درایور
     * @param SmsDriver $driver     نمونه‌ی درایور
     */
    public function ensureUsable(string $driverName, SmsDriver $driver): void;
}
