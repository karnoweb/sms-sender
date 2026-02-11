<?php

namespace Karnoweb\SmsSender\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Karnoweb\SmsSender\Response\SmsResponse;

class SmsSent
{
    use Dispatchable, SerializesModels;

    public SmsResponse $response;

    /** @var array<int, string> */
    public array $recipients;

    public string $message;

    public string $driver;

    /**
     * @param array<int, string> $recipients
     */
    public function __construct(SmsResponse $response, array $recipients, string $message, string $driver)
    {
        $this->response   = $response;
        $this->recipients = $recipients;
        $this->message    = $message;
        $this->driver     = $driver;
    }
}
