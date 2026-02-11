<?php

namespace Karnoweb\SmsSender\Tests\Fakes;

use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Exceptions\DriverConnectionException;

class FailingDeliveryReportDriver implements SmsDriver, DeliveryReportFetcher
{
    public function __construct(protected readonly array $config = []) {}

    /**
     * @param array<int, string> $recipients
     * @return array{message_id: string}
     */
    public function send(array $recipients, string $message, ?string $from = null): array
    {
        return ['message_id' => 'failing-report-' . uniqid()];
    }

    public function fetchDeliveryReport(string $providerMessageId): array
    {
        throw new DriverConnectionException('Delivery report API is unavailable.');
    }
}
