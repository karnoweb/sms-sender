<?php

namespace Karnoweb\SmsSender\Tests\Fakes;

use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Exceptions\DriverConnectionException;

class FailingDeliveryReportDriver implements SmsDriver, DeliveryReportFetcher
{
    public function __construct(protected readonly array $config = []) {}

    public function send(string $phone, string $message): void
    {
        // ارسال موفق
    }

    public function fetchDeliveryReport(string $providerMessageId): array
    {
        throw new DriverConnectionException('Delivery report API is unavailable.');
    }
}
