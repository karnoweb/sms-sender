<?php

namespace Karnoweb\SmsSender\Retry;

use Karnoweb\SmsSender\Logging\SmsLogger;

class RetryHandler
{
    private int $maxAttempts;

    private int $baseDelay;

    private int $multiplier;

    private SmsLogger $logger;

    public function __construct(?SmsLogger $logger = null)
    {
        $this->maxAttempts = (int) config('sms.retry.attempts', 3);
        $this->baseDelay   = (int) config('sms.retry.delay', 1000);
        $this->multiplier  = (int) config('sms.retry.multiplier', 2);
        $this->logger      = $logger ?? new SmsLogger();
    }

    /**
     * Execute a callable with retry.
     *
     * @param  callable(): mixed $callback
     * @return mixed
     * @throws \Throwable Last exception if all attempts fail
     */
    public function execute(string $driverName, callable $callback): mixed
    {
        if (! config('sms.retry.enabled', true)) {
            return $callback();
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $lastException = $e;

                $this->logger->retry($driverName, $attempt, $this->maxAttempts);

                if ($attempt < $this->maxAttempts) {
                    $this->sleep($attempt);
                }
            }
        }

        throw $lastException;
    }

    private function sleep(int $attempt): void
    {
        $delay = $this->baseDelay * (int) pow($this->multiplier, $attempt - 1);
        usleep($delay * 1000);
    }
}
