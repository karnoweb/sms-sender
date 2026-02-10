<?php

namespace Karnoweb\SmsSender\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Exceptions\DriverConnectionException;
use Karnoweb\SmsSender\Exceptions\DriverNotAvailableException;
use Karnoweb\SmsSender\Exceptions\InvalidDriverConfigurationException;
use Karnoweb\SmsSender\Facades\Sms;
use Karnoweb\SmsSender\Tests\Fakes\BuggyDriver;
use Karnoweb\SmsSender\Tests\Fakes\FailingDriver;
use Karnoweb\SmsSender\Tests\Fakes\RecordingDriver;
use Karnoweb\SmsSender\Tests\TestCase;

class FailoverTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
        RecordingDriver::reset();
    }

    public function test_first_driver_success_no_failover(): void
    {
        config([
            'sms.default'  => 'recording',
            'sms.failover' => ['failing'],
            'sms.drivers.recording' => [
                'class'       => RecordingDriver::class,
                'credentials' => [],
            ],
            'sms.drivers.failing' => [
                'class'       => FailingDriver::class,
                'credentials' => [],
            ],
        ]);

        Sms::message('تست')->number('09120000000')->send();

        $this->assertCount(1, RecordingDriver::$sent);
        $this->assertEquals('09120000000', RecordingDriver::$sent[0]['phone']);
        $this->assertDatabaseHas('sms_messages', [
            'driver' => 'recording',
            'status' => SmsSendStatusEnum::SENT->value,
        ]);
    }

    public function test_failover_to_second_driver_on_connection_error(): void
    {
        config([
            'sms.default'  => 'failing',
            'sms.failover' => ['recording'],
            'sms.drivers.failing' => [
                'class'       => FailingDriver::class,
                'credentials' => [],
            ],
            'sms.drivers.recording' => [
                'class'       => RecordingDriver::class,
                'credentials' => [],
            ],
        ]);

        Sms::message('تست failover')->number('09120000000')->send();

        $this->assertCount(1, RecordingDriver::$sent);
        $this->assertDatabaseHas('sms_messages', [
            'driver' => 'failing',
            'status' => SmsSendStatusEnum::FAILED->value,
        ]);
        $this->assertDatabaseHas('sms_messages', [
            'driver' => 'recording',
            'status' => SmsSendStatusEnum::SENT->value,
        ]);
    }

    public function test_all_drivers_fail_throws_not_available(): void
    {
        config([
            'sms.default'  => 'failing1',
            'sms.failover' => ['failing2'],
            'sms.drivers.failing1' => [
                'class'       => FailingDriver::class,
                'credentials' => [],
            ],
            'sms.drivers.failing2' => [
                'class'       => FailingDriver::class,
                'credentials' => [],
            ],
        ]);

        $this->expectException(DriverNotAvailableException::class);
        $this->expectExceptionMessage('No SMS drivers are available');
        Sms::message('تست شکست')->number('09120000000')->send();
    }

    public function test_not_available_exception_chains_last_error(): void
    {
        config([
            'sms.default'  => 'failing',
            'sms.failover' => [],
            'sms.drivers.failing' => [
                'class'       => FailingDriver::class,
                'credentials' => [],
            ],
        ]);

        try {
            Sms::message('تست')->number('09120000000')->send();
            $this->fail('Expected DriverNotAvailableException');
        } catch (DriverNotAvailableException $e) {
            $this->assertInstanceOf(DriverConnectionException::class, $e->getPrevious());
        }
    }

    public function test_unexpected_exception_bubbles_up_without_failover(): void
    {
        config([
            'sms.default'  => 'buggy',
            'sms.failover' => ['recording'],
            'sms.drivers.buggy' => [
                'class'       => BuggyDriver::class,
                'credentials' => [],
            ],
            'sms.drivers.recording' => [
                'class'       => RecordingDriver::class,
                'credentials' => [],
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected bug');
        Sms::message('تست باگ')->number('09120000000')->send();
    }

    public function test_unexpected_exception_does_not_trigger_second_driver(): void
    {
        config([
            'sms.default'  => 'buggy',
            'sms.failover' => ['recording'],
            'sms.drivers.buggy' => [
                'class'       => BuggyDriver::class,
                'credentials' => [],
            ],
            'sms.drivers.recording' => [
                'class'       => RecordingDriver::class,
                'credentials' => [],
            ],
        ]);

        try {
            Sms::message('تست')->number('09120000000')->send();
        } catch (\RuntimeException) {
        }

        $this->assertEmpty(RecordingDriver::$sent);
    }

    public function test_invalid_config_triggers_failover(): void
    {
        config([
            'sms.default'  => 'broken',
            'sms.failover' => ['recording'],
            'sms.drivers.recording' => [
                'class'       => RecordingDriver::class,
                'credentials' => [],
            ],
        ]);

        Sms::message('تست config خراب')->number('09120000000')->send();
        $this->assertCount(1, RecordingDriver::$sent);
    }

    public function test_failover_resends_all_numbers_with_new_driver(): void
    {
        config([
            'sms.default'  => 'failing',
            'sms.failover' => ['recording'],
            'sms.drivers.failing' => [
                'class'       => FailingDriver::class,
                'credentials' => [],
            ],
            'sms.drivers.recording' => [
                'class'       => RecordingDriver::class,
                'credentials' => [],
            ],
        ]);

        Sms::message('تست')
            ->numbers(['09120000000', '09130000000'])
            ->send();

        $this->assertCount(2, RecordingDriver::$sent);
    }

    public function test_state_resets_after_failover_exception(): void
    {
        config([
            'sms.default'  => 'failing',
            'sms.failover' => [],
            'sms.drivers.failing' => [
                'class'       => FailingDriver::class,
                'credentials' => [],
            ],
        ]);

        try {
            Sms::message('تست')->number('09120000000')->send();
        } catch (DriverNotAvailableException) {
        }

        $this->expectException(InvalidDriverConfigurationException::class);
        Sms::send();
    }
}
