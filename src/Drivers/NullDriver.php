<?php

namespace Karnoweb\SmsSender\Drivers;

use Karnoweb\SmsSender\Contracts\LookupCapable;
use Karnoweb\SmsSender\Contracts\SmsDriver;

/**
 * Null driver (Null Object Pattern). No SMS is sent in development or tests.
 */
class NullDriver implements SmsDriver, LookupCapable
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

    /**
     * @param array<string, string> $inputs
     * @return array{message_id: string, raw: array}
     */
    public function lookup(
        string $receptor,
        string $template,
        array $inputs = [],
        ?string $type = 'sms',
    ): array {
        return [
            'message_id' => 'null-lookup-' . uniqid(),
            'raw'        => [],
        ];
    }
}
