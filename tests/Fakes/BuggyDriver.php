<?php

namespace Karnoweb\SmsSender\Tests\Fakes;

use Karnoweb\SmsSender\Contracts\SmsDriver;

class BuggyDriver implements SmsDriver
{
    public function __construct(protected readonly array $config = []) {}

    public function send(string $phone, string $message): void
    {
        throw new \RuntimeException('Unexpected bug in driver');
    }
}
