<?php

namespace Karnoweb\SmsSender\Tests\Fakes;

use Karnoweb\SmsSender\Contracts\SmsDriver;

class RecordingDriver implements SmsDriver
{
    /** @var array<int, array{phone: string, message: string}> */
    public static array $sent = [];

    /** @var array<string, mixed> */
    public static array $receivedConfig = [];

    public function __construct(protected readonly array $config = [])
    {
        static::$receivedConfig = $config;
    }

    public static function reset(): void
    {
        static::$sent           = [];
        static::$receivedConfig = [];
    }

    /**
     * @param array<int, string> $recipients
     * @return array{message_id: string}
     */
    public function send(array $recipients, string $message, ?string $from = null): array
    {
        foreach ($recipients as $phone) {
            static::$sent[] = [
                'phone'   => $phone,
                'message' => $message,
            ];
        }

        return ['message_id' => 'rec-' . uniqid()];
    }

    public static function hasSentTo(string $phone): bool
    {
        foreach (static::$sent as $item) {
            if ($item['phone'] === $phone) {
                return true;
            }
        }

        return false;
    }

    public static function hasSentMessage(string $message): bool
    {
        foreach (static::$sent as $item) {
            if ($item['message'] === $message) {
                return true;
            }
        }

        return false;
    }
}
