# 02 — نصب

## نصب با Composer

```bash
composer require karnoweb/sms-sender
```

پکیج از **Auto-Discovery** لاراول پشتیبانی می‌کند.

## انتشار فایل‌ها

```bash
php artisan vendor:publish --tag=sms-config
php artisan vendor:publish --tag=sms-migrations
php artisan migrate
```

## تنظیم متغیر محیطی

در فایل `.env`:

```dotenv
SMS_DRIVER=null
```

مقدار `null` برای محیط توسعه مناسب است و هیچ پیامکی ارسال نمی‌کند.

## بعدی

→ [پیکربندی](03-configuration.md)
