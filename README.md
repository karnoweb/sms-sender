# 📱 SMS Sender for Laravel

A fluent SMS manager for Laravel with multi-driver support and automatic failover.

[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)]()
[![Laravel](https://img.shields.io/badge/Laravel-13.x-red)]()
[![License](https://img.shields.io/badge/License-MIT-green)]()

---

## ✨ Features

- 🔗 **Fluent API** — Chainable, readable interface
- 🔄 **Auto Failover** — Automatic switch between SMS providers
- 📝 **Logging** — Every attempt logged to database
- 📊 **Delivery Reports** — Check message delivery status
- 🔌 **Extensible** — Add custom drivers with a single interface
- ⚙️ **Usage Control** — Daily/monthly limits per driver
- 🔐 **OTP/Lookup** — Provider-template verification (Kavenegar verify/lookup)

## 🚀 Quick Start

```bash
# Laravel 13
composer require karnoweb/sms-sender:^13.0

# Laravel 11–12
composer require karnoweb/sms-sender:^2.0
```
php artisan vendor:publish --tag=sms-config
php artisan vendor:publish --tag=sms-migrations
php artisan migrate
```

```php
use Karnoweb\SmsSender\Facades\Sms;

// Simple message
Sms::message('Hello World')
    ->number('09120000000')
    ->send();

// OTP via provider template (Kavenegar verify/lookup)
Sms::otp(config('sms.lookups.login_otp', 'login'))
    ->inputs(['token' => '1234'])
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
| 01 | [Overview](docs/fa/01-overview.md) |
| 02 | [Installation](docs/fa/02-installation.md) |
| 03 | [Configuration](docs/fa/03-configuration.md) |
| 04 | [Basic Usage](docs/fa/04-basic-usage.md) |

See [CHANGELOG.md](CHANGELOG.md) for v2.0 breaking changes.

## 🧪 Testing

```bash
./vendor/bin/phpunit
```

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.
