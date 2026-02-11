<?php

namespace Karnoweb\SmsSender\Logging;

use Illuminate\Support\Facades\Log;

class SmsLogger
{
    public function isEnabled(): bool
    {
        return (bool) config('sms.logging.enabled', true);
    }

    /**
     * @param array<int, string> $recipients
     */
    public function success(string $driver, array $recipients, string $message): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->log(
            config('sms.logging.level.success', 'info'),
            __('sms-sender::messages.log_success'),
            [
                'driver'     => $driver,
                'recipients' => $recipients,
                'message'    => mb_strlen($message) > 50 ? mb_substr($message, 0, 50) . '...' : $message,
                'count'      => count($recipients),
            ]
        );
    }

    /**
     * @param array<int, string> $recipients
     */
    public function failure(string $driver, array $recipients, \Throwable $exception): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->log(
            config('sms.logging.level.failure', 'error'),
            __('sms-sender::messages.log_failure'),
            [
                'driver'     => $driver,
                'recipients' => $recipients,
                'error'      => $exception->getMessage(),
                'exception'  => get_class($exception),
            ]
        );
    }

    public function retry(string $driver, int $attempt, int $maxAttempts): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->log(
            'warning',
            __('sms-sender::messages.log_retry', ['attempt' => $attempt, 'max' => $maxAttempts]),
            ['driver' => $driver]
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context): void
    {
        $channel = config('sms.logging.channel');

        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

        $logger->{$level}('[SMS] ' . $message, $context);
    }
}
