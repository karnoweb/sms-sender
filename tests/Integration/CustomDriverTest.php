<?php

namespace Karnoweb\SmsSender\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Facades\Sms;
use Karnoweb\SmsSender\Models\Sms as SmsModel;
use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Tests\TestCase;

class FakeKavenegarDriver implements SmsDriver, DeliveryReportFetcher
{
    /** @var array<int, array<string, string>> */
    public static array $sent = [];

    /** @var array<string, array{status: string}> */
    public static array $reports = [];

    private static int $counter = 0;

    public function __construct(protected readonly array $config = [])
    {
        if (empty($this->config['api_key'])) {
            throw new \Karnoweb\SmsSender\Exceptions\InvalidDriverConfigurationException(
                'Kavenegar requires api_key in credentials.',
            );
        }
    }

    public static function reset(): void
    {
        static::$sent    = [];
        static::$reports = [];
        static::$counter = 0;
    }

    /**
     * @param array<int, string> $recipients
     * @return array{message_id: string}
     */
    public function send(array $recipients, string $message, ?string $from = null): array
    {
        $msgId = 'kav_' . (++static::$counter);
        foreach ($recipients as $phone) {
            static::$sent[] = [
                'phone'               => $phone,
                'message'             => $message,
                'provider_message_id' => $msgId,
            ];
        }

        return ['message_id' => $msgId];
    }

    public function fetchDeliveryReport(string $providerMessageId): array
    {
        return static::$reports[$providerMessageId] ?? ['status' => 'unknown'];
    }

    public static function lastProviderMessageId(): ?string
    {
        return empty(static::$sent) ? null : end(static::$sent)['provider_message_id'];
    }
}

class FakeSimpleDriver implements SmsDriver
{
    public static array $sent = [];

    public function __construct(protected readonly array $config = []) {}

    public static function reset(): void
    {
        static::$sent = [];
    }

    /**
     * @param array<int, string> $recipients
     * @return array{message_id: string}
     */
    public function send(array $recipients, string $message, ?string $from = null): array
    {
        foreach ($recipients as $phone) {
            static::$sent[] = ['phone' => $phone, 'message' => $message];
        }

        return ['message_id' => 'simple-' . uniqid()];
    }
}

class CustomDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
        FakeKavenegarDriver::reset();
        FakeSimpleDriver::reset();
    }

    private function rebuildManager(): void
    {
        $this->app->forgetInstance(SmsManager::class);
        Sms::clearResolvedInstances();
    }

    public function test_custom_driver_can_be_registered_via_config(): void
    {
        config([
            'sms.default' => 'kavenegar',
            'sms.drivers.kavenegar' => [
                'class'       => FakeKavenegarDriver::class,
                'credentials' => [
                    'api_key' => 'test_api_key_123',
                    'sender'  => '10008663',
                ],
            ],
        ]);
        $this->rebuildManager();

        Sms::message('تست کاوه‌نگار')
            ->number('09120000000')
            ->send();

        $this->assertCount(1, FakeKavenegarDriver::$sent);
        $this->assertEquals('09120000000', FakeKavenegarDriver::$sent[0]['phone']);
        $this->assertDatabaseHas('sms_messages', [
            'driver'  => 'kavenegar',
            'status'  => SmsSendStatusEnum::SENT->value,
        ]);
    }

    public function test_driver_without_delivery_report_is_skipped(): void
    {
        config([
            'sms.default' => 'simple',
            'sms.drivers.simple' => [
                'class'       => FakeSimpleDriver::class,
                'credentials' => [],
            ],
        ]);
        $this->rebuildManager();

        Sms::message('تست ساده')->number('09120000000')->send();
        $record = SmsModel::first();
        $record->update(['provider_message_id' => 'some_id']);

        $results = Sms::number('09120000000')->checkStatus();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['skipped']);
        $this->assertStringContainsString('does not support', $results[0]['reason']);
    }
}
