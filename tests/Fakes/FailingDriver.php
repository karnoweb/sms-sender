<?php

namespace Karnoweb\SmsSender\Tests\Fakes;

use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Exceptions\DriverConnectionException;

class FailingDriver implements SmsDriver
{
    public function __construct(protected readonly array $config = []) {}

    /**
     * @param array<int, string> $recipients
     * @return array<string, mixed>
     */
    public function send(array $recipients, string $message, ?string $from = null): array
    {
        throw new DriverConnectionException('Simulated connection failure');
    }
}
