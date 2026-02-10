# 04 — استفاده‌ی پایه

## سه روش دسترسی

### ۱. Facade (توصیه‌شده)

```php
use Karnoweb\SmsSender\Facades\Sms;

Sms::message('سلام')->number('09120000000')->send();
```

### ۲. Static Instance

```php
use Karnoweb\SmsSender\SmsManager;

SmsManager::instance()
    ->message('سلام')
    ->number('09120000000')
    ->send();
```

### ۳. Dependency Injection

```php
public function __construct(private readonly SmsManager $sms) {}
```

## ارسال پیام ساده

```php
Sms::message('سفارش شما ثبت شد.')
    ->number('09120000000')
    ->send();
```

## ارسال OTP

```php
use Karnoweb\SmsSender\Enums\SmsTemplateEnum;

Sms::otp(SmsTemplateEnum::LOGIN_OTP)
    ->input('code', '1234')
    ->number('09120000000')
    ->send();
```

## بعدی

برای ساخت درایور سفارشی و جزئیات بیشتر به مستندات کامل مراجعه کنید.
