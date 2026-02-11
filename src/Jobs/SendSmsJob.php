<?php

namespace Karnoweb\SmsSender\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Karnoweb\SmsSender\Logging\SmsLogger;
use Karnoweb\SmsSender\SmsManager;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $backoff;

    /** @var array<int, string> */
    private array $recipients;

    private string $message;

    private ?string $from;

    private ?string $driver;

    /**
     * @param array<int, string> $recipients
     */
    public function __construct(
        array $recipients,
        string $message,
        ?string $from = null,
        ?string $driver = null
    ) {
        $this->recipients = $recipients;
        $this->message    = $message;
        $this->from       = $from;
        $this->driver     = $driver;
        $this->tries      = (int) config('sms.queue.tries', 3);
        $this->backoff    = (int) config('sms.queue.retry_delay', 10);
    }

    public function handle(): void
    {
        $sms = app(SmsManager::class)
            ->numbers($this->recipients)
            ->message($this->message);

        if ($this->from !== null) {
            $sms->from($this->from);
        }

        if ($this->driver !== null) {
            $sms->driver($this->driver);
        }

        $sms->send();
    }

    public function failed(\Throwable $exception): void
    {
        $logger = new SmsLogger();
        $logger->failure(
            $this->driver ?? config('sms.default'),
            $this->recipients,
            $exception
        );
    }
}
