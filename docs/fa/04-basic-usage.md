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

## ارسال با تمپلیت محلی (غیر OTP)

متن تمپلیت را از اپ خودتان بدهید؛ پکیج متن را compile می‌کند و با `sms/send` ارسال می‌کند:

```php
Sms::template('order_shipped', 'سفارش {order_id} ارسال شد.')
    ->input('order_id', '1234')
    ->number('09120000000')
    ->send();
```

یا از طریق تنظیم `config('sms.templates')` در اپ.

## OTP / Lookup (تمپلیت پنل provider)

**v2.0:** `otp()` دیگر متن را محلی compile نمی‌کند. اپ فقط **نام template در پنل provider** (مثلاً کاوه‌نگار) و **inputs** را می‌فرستد؛ متن واقعی از پنل provider ساخته می‌شود.

```php
Sms::otp(config('sms.lookups.login_otp', 'login'))
    ->inputs(['token' => '1234'])
    ->number('09120000000')
    ->send();
```

`lookup()` معادل `otp()` است:

```php
Sms::lookup('login')
    ->inputs(['token' => $code, 'token2' => $name])
    ->number($mobile)
    ->send();
```

### تفاوت روش‌ها

| متد | ارسال واقعی | متن در DB/ادمین |
|-----|-------------|-----------------|
| `message()` | متن خام → `sms/send` | همان متن |
| `template()` | compile محلی → `sms/send` | متن compile‌شده |
| `otp()` / `lookup()` | provider lookup (مثلاً `verify/lookup.json`) | `config('sms.templates')` فقط برای نمایش |

### Config

```php
// config/sms.php

'templates' => [
    // فقط نمایش در پنل ادمین / لاگ — NOT used for sending
    'login_otp' => 'کد ورود شما: {token}. این کد تا ۱۰ دقیقه دیگر منقضی می‌شود.',
],

'lookups' => [
    // کلید اپ => نام template در پنل کاوه‌نگار
    'login_otp' => env('KAVENEGAR_LOOKUP_LOGIN', 'login'),
],
```

### محدودیت‌های OTP

- فقط **یک** گیرنده: `number()` — `numbers()` مجاز نیست
- ترکیب با `message()` یا `template()` مجاز نیست
- پارامترها فقط با `inputs()` / `input()` — کلیدها مطابق API provider (`token`, `token2`, `token3`, ...)

## بعدی

برای ساخت درایور سفارشی و جزئیات بیشتر به مستندات کامل مراجعه کنید.
