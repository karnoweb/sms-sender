<?php

namespace Karnoweb\SmsSender\Support;

use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Contracts\SmsUsageHandler;
use Karnoweb\SmsSender\Exceptions\DriverNotAvailableException;
use Karnoweb\SmsSender\Models\Sms;

/**
 * Default usage handler: enabled, daily_limit, monthly_limit from config/sms.php.
 */
class DefaultUsageHandler implements SmsUsageHandler
{
    public function ensureUsable(string $driverName, SmsDriver $driver): void
    {
        $usageConfig = $this->getUsageConfig($driverName);

        if ($usageConfig === null) {
            return;
        }

        $this->checkEnabled($driverName, $usageConfig);
        $this->checkDailyLimit($driverName, $usageConfig);
        $this->checkMonthlyLimit($driverName, $usageConfig);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getUsageConfig(string $driverName): ?array
    {
        $config = config("sms.drivers.{$driverName}.usage");

        if (! is_array($config)) {
            return null;
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $usageConfig
     */
    protected function checkEnabled(string $driverName, array $usageConfig): void
    {
        $enabled = $usageConfig['enabled'] ?? true;

        if ($enabled === false) {
            throw new DriverNotAvailableException(
                "SMS driver [{$driverName}] is disabled.",
            );
        }
    }

    /**
     * @param array<string, mixed> $usageConfig
     */
    protected function checkDailyLimit(string $driverName, array $usageConfig): void
    {
        $dailyLimit = $usageConfig['daily_limit'] ?? null;

        if (empty($dailyLimit)) {
            return;
        }

        $todayCount = $this->getTodayCount($driverName);

        if ($todayCount >= $dailyLimit) {
            throw new DriverNotAvailableException(
                "SMS driver [{$driverName}] daily limit reached ({$todayCount}/{$dailyLimit}).",
            );
        }
    }

    /**
     * @param array<string, mixed> $usageConfig
     */
    protected function checkMonthlyLimit(string $driverName, array $usageConfig): void
    {
        $monthlyLimit = $usageConfig['monthly_limit'] ?? null;

        if (empty($monthlyLimit)) {
            return;
        }

        $monthCount = $this->getMonthCount($driverName);

        if ($monthCount >= $monthlyLimit) {
            throw new DriverNotAvailableException(
                "SMS driver [{$driverName}] monthly limit reached ({$monthCount}/{$monthlyLimit}).",
            );
        }
    }

    protected function getTodayCount(string $driverName): int
    {
        /** @var class-string<Sms> $modelClass */
        $modelClass = config('sms.model', Sms::class);

        return $modelClass::query()
            ->forDriver($driverName)
            ->whereDate('created_at', today())
            ->count();
    }

    protected function getMonthCount(string $driverName): int
    {
        /** @var class-string<Sms> $modelClass */
        $modelClass = config('sms.model', Sms::class);

        return $modelClass::query()
            ->forDriver($driverName)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}
