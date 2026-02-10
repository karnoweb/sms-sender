<?php

namespace Karnoweb\SmsSender\Tests\Fakes;

use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Contracts\SmsDriver;

class DeliveryReportDriver implements SmsDriver, DeliveryReportFetcher
{
    /** @var array<int, array{phone: string, message: string, provider_message_id: string}> */
    public static array $sent = [];

    /** @var array<string, array{status: string}> */
    public static array $deliveryReports = [];

    private static int $counter = 0;

    public function __construct(protected readonly array $config = []) {}

    public static function reset(): void
    {
        static::$sent            = [];
        static::$deliveryReports = [];
        static::$counter         = 0;
    }

    public function send(string $phone, string $message): void
    {
        $providerMessageId = 'msg_' . (++static::$counter);

        static::$sent[] = [
            'phone'               => $phone,
            'message'             => $message,
            'provider_message_id' => $providerMessageId,
        ];
    }

    public function fetchDeliveryReport(string $providerMessageId): array
    {
        if (isset(static::$deliveryReports[$providerMessageId])) {
            return static::$deliveryReports[$providerMessageId];
        }

        return ['status' => 'unknown'];
    }

    public static function lastProviderMessageId(): ?string
    {
        if (empty(static::$sent)) {
            return null;
        }

        return end(static::$sent)['provider_message_id'];
    }
}
