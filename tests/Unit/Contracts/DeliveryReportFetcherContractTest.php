<?php

namespace Karnoweb\SmsSender\Tests\Unit\Contracts;

use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Tests\TestCase;
use ReflectionMethod;

class DeliveryReportFetcherContractTest extends TestCase
{
    public function test_interface_exists(): void
    {
        $this->assertTrue(interface_exists(DeliveryReportFetcher::class));
    }

    public function test_has_fetch_delivery_report_method(): void
    {
        $this->assertTrue(method_exists(DeliveryReportFetcher::class, 'fetchDeliveryReport'));
    }

    public function test_fetch_delivery_report_signature(): void
    {
        $method = new ReflectionMethod(DeliveryReportFetcher::class, 'fetchDeliveryReport');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('providerMessageId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()?->getName());
        $this->assertEquals('array', $method->getReturnType()?->getName());
    }

    public function test_interface_is_independent_from_sms_driver(): void
    {
        $reflection = new \ReflectionClass(DeliveryReportFetcher::class);
        $this->assertEmpty($reflection->getInterfaceNames());
    }
}
