<?php

namespace Karnoweb\SmsSender\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SmsFailed
{
    use Dispatchable, SerializesModels;

    /** @var array<int, string> */
    public array $recipients;

    public string $message;

    public \Throwable $exception;

    /** @var array<string, \Throwable> */
    public array $driverErrors;

    /**
     * @param array<int, string> $recipients
     * @param array<string, \Throwable> $driverErrors
     */
    public function __construct(
        array $recipients,
        string $message,
        \Throwable $exception,
        array $driverErrors = []
    ) {
        $this->recipients   = $recipients;
        $this->message      = $message;
        $this->exception    = $exception;
        $this->driverErrors = $driverErrors;
    }
}
