<?php

namespace Karnoweb\SmsSender\Contracts;

/**
 * قرارداد اصلی درایورهای SMS.
 *
 * هر درایور ارسال پیامک باید این اینترفیس را پیاده‌سازی کند.
 * سازنده باید یک پارامتر array $config بپذیرد.
 *
 * @throws \Karnoweb\SmsSender\Exceptions\DriverConnectionException در صورت خطای ارتباطی
 */
interface SmsDriver
{
    /**
     * ارسال یک پیامک به شماره‌ی مشخص.
     *
     * @param string $phone   شماره‌ی موبایل گیرنده
     * @param string $message متن نهایی پیامک
     */
    public function send(string $phone, string $message): void;
}
