<?php

namespace Karnoweb\SmsSender\Contracts;

/**
 * Contract for SMS driver usage/availability control.
 *
 * @throws \Karnoweb\SmsSender\Exceptions\DriverNotAvailableException when driver is not usable
 */
interface SmsUsageHandler
{
    /**
     * @param string $driverName
     * @param SmsDriver $driver
     */
    public function ensureUsable(string $driverName, SmsDriver $driver): void;
}
