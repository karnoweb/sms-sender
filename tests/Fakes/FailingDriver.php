<?php

namespace Karnoweb\SmsSender\Tests\Fakes;

use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Exceptions\DriverConnectionException;

class FailingDriver implements SmsDriver
{
    public function __construct(protected readonly array $config = []) {}

    public function send(string $phone, string $message): void
    {
        throw new DriverConnectionException('Simulated connection failure');
    }
}
