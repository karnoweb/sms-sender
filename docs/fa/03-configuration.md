# 03 — پیکربندی

فایل `config/sms.php` تمام تنظیمات پکیج را در بر دارد.

## ساختار

- **default** — نام درایور پیش‌فرض
- **failover** — آرایه‌ی مرتب درایورهای جایگزین
- **drivers** — تعریف هر درایور با `class` و `credentials`
- **templates** — متن **نمایشی** برای admin/لاگ (در OTP از provider ارسال نمی‌شود)
- **lookups** — نگاشت کلید اپ → نام template در پنل provider (برای `otp()`)
- **model** — کلاس مدل لاگ
- **table** — نام جدول دیتابیس
- **usage_handler** — کلاس کنترل مصرف (اختیاری)

## OTP / Lookup

```php
'templates' => [
    'login_otp' => 'کد ورود شما: {token}. ...',
],

'lookups' => [
    'login_otp' => env('KAVENEGAR_LOOKUP_LOGIN', 'login'),
],
```

```php
Sms::otp(config('sms.lookups.login_otp'))
    ->inputs(['token' => $code])
    ->number($mobile)
    ->send();
```

## بعدی

→ [استفاده‌ی پایه](04-basic-usage.md)
