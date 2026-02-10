<?php

namespace Karnoweb\SmsSender\Facades;

use Illuminate\Support\Facades\Facade;
use Karnoweb\SmsSender\SmsManager;

/**
 * Facade برای دسترسی ساده به SmsManager.
 *
 * @method static SmsManager message(string $message)
 * @method static SmsManager otp(\Karnoweb\SmsSender\Enums\SmsTemplateEnum $template)
 * @method static SmsManager input(string $key, string $value)
 * @method static SmsManager inputs(array $inputs)
 * @method static SmsManager number(string $phone)
 * @method static SmsManager numbers(array $phones)
 * @method static void send()
 * @method static array checkStatus()
 *
 * @see SmsManager
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsManager::class;
    }
}
