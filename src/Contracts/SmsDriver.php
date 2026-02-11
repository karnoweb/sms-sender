<?php

namespace Karnoweb\SmsSender\Contracts;

/**
 * Main SMS driver contract. Each driver must implement this interface.
 * Constructor must accept array $config.
 *
 * @throws \Karnoweb\SmsSender\Exceptions\DriverConnectionException on connection error
 */
interface SmsDriver
{
    /**
     * Send SMS to one or more recipients.
     *
     * @param array<int, string> $recipients Recipient phone numbers
     * @param string $message Final message text
     * @param string|null $from Sender number (optional, driver-dependent)
     * @return array<string, mixed> Raw API response (at least message_id on success)
     */
    public function send(array $recipients, string $message, ?string $from = null): array;
}
