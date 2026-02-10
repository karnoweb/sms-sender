<?php

namespace Karnoweb\SmsSender\Tests\Unit\SmsManager;

use Karnoweb\SmsSender\Exceptions\InvalidDriverConfigurationException;
use Karnoweb\SmsSender\Facades\Sms;
use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Tests\TestCase;

class CheckStatusTest extends TestCase
{
    public function test_check_status_without_recipients_throws_exception(): void
    {
        $this->expectException(InvalidDriverConfigurationException::class);
        $this->expectExceptionMessage('No recipients');
        Sms::checkStatus();
    }

    public function test_state_resets_after_check_status(): void
    {
        try {
            Sms::number('09120000000')->checkStatus();
        } catch (\Throwable) {
        }
        $this->expectException(InvalidDriverConfigurationException::class);
        $this->expectExceptionMessage('No recipients');
        Sms::checkStatus();
    }

    public function test_state_resets_even_after_exception(): void
    {
        try {
            Sms::checkStatus();
        } catch (InvalidDriverConfigurationException) {
        }
        $this->expectException(InvalidDriverConfigurationException::class);
        Sms::checkStatus();
    }

    public function test_check_status_uses_same_number_api_as_send(): void
    {
        $manager = $this->app->make(SmsManager::class);
        $reflection = new \ReflectionProperty(SmsManager::class, 'toNumbers');
        $manager->number('09120000000')->numbers(['09130000000']);
        $this->assertEquals(
            ['09120000000', '09130000000'],
            $reflection->getValue($manager),
        );
    }
}
