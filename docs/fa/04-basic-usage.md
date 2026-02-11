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

## ارسال با تمپلیت (تزریق از اپ)

متن تمپلیت را از اپ خودتان بدهید:

```php
Sms::template('login_otp', 'کد ورود شما: {code}')
    ->input('code', '1234')
    ->number('09120000000')
    ->send();
```

یا از طریق تنظیم `config('sms.templates')` در اپ.

## ارسال OTP با Enum

```php
use Karnoweb\SmsSender\Enums\SmsTemplateEnum;

Sms::otp(SmsTemplateEnum::LOGIN_OTP)
    ->input('code', '1234')
    ->number('09120000000')
    ->send();
```

برای کار با Enum، متن تمپلیت را در `config('sms.templates')` یا از طریق انتشار lang پکیج تنظیم کنید.

## بعدی

برای ساخت درایور سفارشی و جزئیات بیشتر به مستندات کامل مراجعه کنید.
