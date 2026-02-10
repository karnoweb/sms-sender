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

    public function send(string $phone, string $message): void
    {
        static::$sent[] = [
            'phone'   => $phone,
            'message' => $message,
        ];
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
