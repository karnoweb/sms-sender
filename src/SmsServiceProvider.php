<?php

namespace Karnoweb\SmsSender;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    /**
     * ثبت سرویس‌ها در Container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/sms.php',
            'sms',
        );

        $this->app->singleton(SmsManager::class, function (Container $app): SmsManager {
            return new SmsManager($app);
        });
    }

    /**
     * عملیات Boot پکیج.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    /**
     * تعریف فایل‌های قابل publish.
     */
    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/sms.php' => config_path('sms.php'),
        ], 'sms-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'sms-migrations');
    }
}
