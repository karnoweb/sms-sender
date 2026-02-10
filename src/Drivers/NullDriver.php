<?php

namespace Karnoweb\SmsSender\Drivers;

use Karnoweb\SmsSender\Contracts\SmsDriver;

/**
 * درایور خالی (Null Object Pattern).
 * در محیط توسعه و تست هیچ پیامکی ارسال نمی‌شود.
 */
class NullDriver implements SmsDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected readonly array $config = [],
    ) {}

    public function send(string $phone, string $message): void
    {
        // عمداً خالی — Null Object Pattern
    }
}
