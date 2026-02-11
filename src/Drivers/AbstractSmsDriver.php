<?php

namespace Karnoweb\SmsSender\Drivers;

use Karnoweb\SmsSender\Contracts\SmsDriver;

/**
 * Base driver with shared config. Concrete drivers implement send().
 */
abstract class AbstractSmsDriver implements SmsDriver
{
    /** @var array<string, mixed> */
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Resolve sender line: manager-provided $from or driver config.
     */
    protected function sender(?string $from): ?string
    {
        if ($from !== null && $from !== '') {
            return $from;
        }

        return $this->config['sender'] ?? $this->config['from'] ?? null;
    }
}
