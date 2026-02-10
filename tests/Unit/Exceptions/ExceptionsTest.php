<?php

namespace Karnoweb\SmsSender\Tests\Unit\Exceptions;

use Karnoweb\SmsSender\Exceptions\DriverConnectionException;
use Karnoweb\SmsSender\Exceptions\DriverNotAvailableException;
use Karnoweb\SmsSender\Exceptions\InvalidDriverConfigurationException;
use Karnoweb\SmsSender\Exceptions\SmsException;
use Karnoweb\SmsSender\Tests\TestCase;
use RuntimeException;

class ExceptionsTest extends TestCase
{
    public function test_sms_exception_extends_runtime_exception(): void
    {
        $exception = new SmsException('test');
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function test_invalid_driver_configuration_extends_sms_exception(): void
    {
        $exception = new InvalidDriverConfigurationException('test');
        $this->assertInstanceOf(SmsException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function test_driver_connection_extends_sms_exception(): void
    {
        $exception = new DriverConnectionException('test');
        $this->assertInstanceOf(SmsException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function test_driver_not_available_extends_sms_exception(): void
    {
        $exception = new DriverNotAvailableException('test');
        $this->assertInstanceOf(SmsException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function test_exception_carries_message(): void
    {
        $message = 'Driver class for kavenegar is not defined.';
        $exception = new InvalidDriverConfigurationException($message);
        $this->assertEquals($message, $exception->getMessage());
    }

    public function test_exception_carries_code(): void
    {
        $exception = new DriverConnectionException('timeout', 504);
        $this->assertEquals(504, $exception->getCode());
    }

    public function test_driver_connection_exception_chains_previous(): void
    {
        $httpError = new \Exception('cURL timeout', 28);
        $exception = new DriverConnectionException(
            message:  'Kavenegar API failed',
            code:     28,
            previous: $httpError,
        );
        $this->assertSame($httpError, $exception->getPrevious());
        $this->assertEquals(28, $exception->getCode());
    }

    public function test_driver_not_available_chains_last_failure(): void
    {
        $connectionError = new DriverConnectionException('Provider timeout');
        $exception = new DriverNotAvailableException(
            message:  'No SMS drivers are available.',
            previous: $connectionError,
        );
        $this->assertInstanceOf(DriverConnectionException::class, $exception->getPrevious());
    }

    public function test_all_exceptions_catchable_by_sms_exception(): void
    {
        $exceptions = [
            new InvalidDriverConfigurationException('config error'),
            new DriverConnectionException('connection error'),
            new DriverNotAvailableException('no driver'),
        ];

        foreach ($exceptions as $exception) {
            $caught = false;
            try {
                throw $exception;
            } catch (SmsException) {
                $caught = true;
            }
            $this->assertTrue($caught, sprintf('%s must be catchable by SmsException.', $exception::class));
        }
    }

    public function test_sms_exception_not_catchable_by_logic_exception(): void
    {
        $this->expectException(SmsException::class);
        throw new InvalidDriverConfigurationException('test');
    }
}
