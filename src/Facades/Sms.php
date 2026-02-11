<?php

namespace Karnoweb\SmsSender\Facades;

use Illuminate\Support\Facades\Facade;
use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Response\SmsResponse;
use Karnoweb\SmsSender\Testing\SmsFake;

/**
 * Facade for SmsManager.
 *
 * @method static SmsManager message(string $message)
 * @method static SmsManager template(string $key, string $body)
 * @method static SmsManager otp(\Karnoweb\SmsSender\Enums\SmsTemplateEnum $template)
 * @method static SmsManager input(string $key, string $value)
 * @method static SmsManager inputs(array $inputs)
 * @method static SmsManager number(string $phone)
 * @method static SmsManager numbers(array $phones)
 * @method static SmsManager from(string $from)
 * @method static SmsManager driver(string $driver)
 * @method static SmsResponse send()
 * @method static void queue(?string $queueName = null)
 * @method static void later(int $delaySeconds, ?string $queueName = null)
 * @method static array checkStatus()
 *
 * @see SmsManager
 */
class Sms extends Facade
{
    /**
     * Enable fake mode (like Mail::fake).
     */
    public static function fake(): SmsFake
    {
        $fake = new SmsFake();
        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return SmsManager::class;
    }
}
