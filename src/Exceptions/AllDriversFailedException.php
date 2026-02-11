<?php

namespace Karnoweb\SmsSender\Exceptions;

use Illuminate\Support\Facades\Lang;

class AllDriversFailedException extends SmsException
{
    /** @var array<string, \Throwable> */
    private array $driverErrors;

    /**
     * @param array<string, \Throwable> $driverErrors
     */
    public function __construct(array $driverErrors)
    {
        $this->driverErrors = $driverErrors;

        $summary = collect($driverErrors)
            ->map(fn (\Throwable $e, string $driver) => "  - {$driver}: {$e->getMessage()}")
            ->implode("\n");

        parent::__construct(Lang::get('sms-sender::messages.all_drivers_failed', ['summary' => $summary]));
    }

    /**
     * @return array<string, \Throwable>
     */
    public function getDriverErrors(): array
    {
        return $this->driverErrors;
    }
}
