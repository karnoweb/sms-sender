<?php

declare(strict_types=1);

namespace Karnoweb\SmsSender\Drivers;

use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Exceptions\DriverConnectionException;

/**
 * MelliPayamak (Melipayamak) SMS driver.
 *
 * Configure with credentials (username, password, sender). Implement your own
 * HTTP call to the provider API or extend this driver for production.
 *
 * @see https://melipayamak.com/
 */
class MelliPayamakDriver extends AbstractSmsDriver implements SmsDriver, DeliveryReportFetcher
{
    /**
     * @param array<int, string> $recipients
     * @return array{message_id: string}
     */
    public function send(array $recipients, string $message, ?string $from = null): array
    {
        if (empty($this->config['username']) || empty($this->config['password'])) {
            throw new DriverConnectionException('MelliPayamak credentials not set.');
        }

        $sender = $this->sender($from);

        // Stub: no actual API call. Override in your app or extend this driver
        // to integrate with the provider REST/SOAP API.
        foreach ($recipients as $phone) {
            $this->sendOne($phone, $message, $sender);
        }

        return ['message_id' => 'melipayamak-' . uniqid()];
    }

    /**
     * Override in subclass to perform real send.
     */
    protected function sendOne(string $phoneNumber, string $message, ?string $sender): void
    {
        // No-op. Implement HTTP request to MelliPayamak API in your driver subclass.
    }

    /**
     * @return array{status: string, delivered_at?: string|null}
     */
    public function fetchDeliveryReport(string $providerMessageId): array
    {
        return [
            'status'       => 'unknown',
            'delivered_at' => null,
        ];
    }
}
