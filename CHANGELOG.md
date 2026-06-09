# Changelog

All notable changes to this project will be documented in this file.

## [2.0.0] - 2026-06-10

### Breaking Changes

- **`otp()` now sends via provider lookup**, not local template compilation + `sms/send`.
  - Before: `Sms::otp(SmsTemplateEnum::LOGIN_OTP)->input('code', '1234')->...`
  - After: `Sms::otp('login')->inputs(['token' => '1234'])->number($mobile)->send()`
- `otp()` accepts a **provider template name** (`string`), not `SmsTemplateEnum`.
- OTP/lookup supports **exactly one recipient** via `number()` — `numbers()` throws.
- `otp()` cannot be combined with `message()` or `template()`.

### Added

- `LookupCapable` contract for provider-template verification sends.
- `KavenegarDriver::lookup()` — calls `verify/lookup.json` ([Kavenegar REST docs](https://kavenegar.com/rest.html#sms-lookup)).
- `NullDriver::lookup()` for development/tests.
- `SmsManager::lookup()` as an alias for `otp()`.
- `config('sms.lookups')` — map app keys to provider template names.
- `SmsFake::assertLookupSent()` for testing OTP/lookup sends.
- Display message resolution from `config('sms.templates')` for admin/logs only.

### Changed

- `config('sms.templates')` is **display-only** when using `otp()`/`lookup()`; the provider builds the actual SMS text.
- `SendSmsJob` serializes OTP state (`providerTemplate`, `inputs`, `isOtpMode`).
- Failover for OTP skips drivers that do not implement `LookupCapable`.

### Migration

```php
// config/sms.php
'templates' => [
    'login_otp' => 'کد ورود شما: {token}. ...',
],
'lookups' => [
    'login_otp' => env('KAVENEGAR_LOOKUP_LOGIN', 'login'),
],

// application code
Sms::otp((string) config('sms.lookups.login_otp', 'login'))
    ->inputs(['token' => $code])
    ->number($mobile)
    ->send();
```

## [1.x]

Initial releases with fluent SMS API, multi-driver failover, and local template compilation for `otp()`.
