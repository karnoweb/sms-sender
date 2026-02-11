<?php

namespace Karnoweb\SmsSender\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SmsSending
{
    use Dispatchable;

    /** @var array<int, string> */
    public array $recipients;

    public string $message;

    public string $driver;

    public bool $cancelled = false;

    /**
     * @param array<int, string> $recipients
     */
    public function __construct(array $recipients, string $message, string $driver)
    {
        $this->recipients = $recipients;
        $this->message    = $message;
        $this->driver     = $driver;
    }

    /** Cancel send from listener. */
    public function cancel(): void
    {
        $this->cancelled = true;
    }
}
