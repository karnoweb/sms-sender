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

    private ?string $message;

    private ?string $from;

    private ?string $driver;

    private bool $isOtpMode;

    private ?string $providerTemplate;

    /** @var array<string, string> */
    private array $inputs;

    /**
     * @param array<int, string> $recipients
     * @param array<string, string> $inputs
     */
    public function __construct(
        array $recipients,
        ?string $message = null,
        ?string $from = null,
        ?string $driver = null,
        bool $isOtpMode = false,
        ?string $providerTemplate = null,
        array $inputs = [],
    ) {
        $this->recipients       = $recipients;
        $this->message          = $message;
        $this->from             = $from;
        $this->driver           = $driver;
        $this->isOtpMode        = $isOtpMode;
        $this->providerTemplate = $providerTemplate;
        $this->inputs           = $inputs;
        $this->tries            = (int) config('sms.queue.tries', 3);
        $this->backoff          = (int) config('sms.queue.retry_delay', 10);
    }

    public function handle(): void
    {
        $sms = app(SmsManager::class);

        if ($this->isOtpMode) {
            $sms->otp((string) $this->providerTemplate)->inputs($this->inputs);

            if (count($this->recipients) === 1) {
                $sms->number($this->recipients[0]);
            } else {
                foreach ($this->recipients as $recipient) {
                    $sms->number($recipient);
                }
            }
        } else {
            $sms->message((string) $this->message)->numbers($this->recipients);
        }

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
