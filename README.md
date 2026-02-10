# 📱 SMS Sender for Laravel

A fluent SMS manager for Laravel with multi-driver support and automatic failover.

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)]()
[![Laravel](https://img.shields.io/badge/Laravel-11.x%20|%2012.x-red)]()
[![License](https://img.shields.io/badge/License-MIT-green)]()

---

## ✨ Features

- 🔗 **Fluent API** — Chainable, readable interface
- 🔄 **Auto Failover** — Automatic switch between SMS providers
- 📝 **Logging** — Every attempt logged to database
- 📊 **Delivery Reports** — Check message delivery status
- 🔌 **Extensible** — Add custom drivers with a single interface
- ⚙️ **Usage Control** — Daily/monthly limits per driver

## 🚀 Quick Start

```bash
composer require karnoweb/sms-sender
php artisan vendor:publish --tag=sms-config
php artisan vendor:publish --tag=sms-migrations
php artisan migrate
```

```php
use Karnoweb\SmsSender\Facades\Sms;
use Karnoweb\SmsSender\Enums\SmsTemplateEnum;

// Simple message
Sms::message('Hello World')
    ->number('09120000000')
    ->send();

// OTP
Sms::otp(SmsTemplateEnum::LOGIN_OTP)
    ->input('code', '1234')
    ->number('09120000000')
    ->send();

// Multiple recipients
Sms::message('Announcement')
    ->numbers(['09121111111', '09122222222'])
    ->send();

// Check delivery status
$results = Sms::number('09120000000')->checkStatus();
```

## 📖 Documentation

Full documentation is available in the [`docs/`](docs/00-index.md) directory.

| # | Topic |
|---|-------|
| 01 | [Overview](docs/01-overview.md) |
| 02 | [Installation](docs/02-installation.md) |
| 03 | [Configuration](docs/03-configuration.md) |
| 04 | [Basic Usage](docs/04-basic-usage.md) |

## 🧪 Testing

```bash
./vendor/bin/phpunit
```

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.
