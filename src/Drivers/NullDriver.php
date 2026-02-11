<?php

namespace Karnoweb\SmsSender\Drivers;

use Karnoweb\SmsSender\Contracts\SmsDriver;

/**
 * Null driver (Null Object Pattern). No SMS is sent in development or tests.
 */
class NullDriver implements SmsDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected readonly array $config = [],
    ) {}

    /**
     * @param array<int, string> $recipients
     * @return array{message_id: string}
     */
    public function send(array $recipients, string $message, ?string $from = null): array
    {
        return ['message_id' => 'null-' . uniqid()];
    }
}
