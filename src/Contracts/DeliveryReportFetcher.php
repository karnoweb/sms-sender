<?php

namespace Karnoweb\SmsSender\Contracts;

/**
 * قرارداد دریافت وضعیت تحویل پیامک (Delivery Report).
 * اینترفیس اختیاری — فقط درایورهایی که قابلیت دارند آن را پیاده‌سازی می‌کنند.
 */
interface DeliveryReportFetcher
{
    /**
     * دریافت وضعیت تحویل یک پیامک از سرویس‌دهنده.
     *
     * @param string $providerMessageId شناسه‌ی پیامک در سرویس‌دهنده
     * @return array{status: string, delivered_at?: string|null}
     */
    public function fetchDeliveryReport(string $providerMessageId): array;
}
