<?php

namespace Karnoweb\SmsSender;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
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

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'sms-sender');

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/sms.php' => config_path('sms.php'),
        ], 'sms-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'sms-migrations');

        $this->publishes([
            __DIR__ . '/../lang' => lang_path('vendor/sms-sender'),
        ], 'sms-lang');
    }
}
