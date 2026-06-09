<?php

namespace Karnoweb\SmsSender\Tests\Fakes;

use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Contracts\LookupCapable;
use Karnoweb\SmsSender\Contracts\SmsDriver;

class DeliveryReportDriver implements SmsDriver, DeliveryReportFetcher, LookupCapable
{
    /** @var array<int, array{phone: string, message: string, provider_message_id: string}> */
    public static array $sent = [];

    /** @var array<int, array{phone: string, template: string, inputs: array<string, string>, provider_message_id: string}> */
    public static array $lookups = [];

    /** @var array<string, array{status: string}> */
    public static array $deliveryReports = [];

    private static int $counter = 0;

    public function __construct(protected readonly array $config = []) {}

    public static function reset(): void
    {
        static::$sent            = [];
        static::$lookups         = [];
        static::$deliveryReports = [];
        static::$counter         = 0;
    }

    /**
     * @param array<int, string> $recipients
     * @return array{message_id: string}
     */
    public function send(array $recipients, string $message, ?string $from = null): array
    {
        $providerMessageId = 'msg_' . (++static::$counter);

        foreach ($recipients as $phone) {
            static::$sent[] = [
                'phone'               => $phone,
                'message'             => $message,
                'provider_message_id' => $providerMessageId,
            ];
        }

        return ['message_id' => $providerMessageId];
    }

    /**
     * @param array<string, string> $inputs
     * @return array{message_id: string}
     */
    public function lookup(
        string $receptor,
        string $template,
        array $inputs = [],
        ?string $type = 'sms',
    ): array {
        $providerMessageId = 'msg_' . (++static::$counter);

        static::$lookups[] = [
            'phone'               => $receptor,
            'template'            => $template,
            'inputs'              => $inputs,
            'provider_message_id' => $providerMessageId,
        ];

        return ['message_id' => $providerMessageId];
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
        if (! empty(static::$lookups)) {
            return end(static::$lookups)['provider_message_id'];
        }

        if (empty(static::$sent)) {
            return null;
        }

        return end(static::$sent)['provider_message_id'];
    }
}
