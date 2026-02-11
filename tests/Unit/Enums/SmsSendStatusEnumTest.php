<?php

namespace Karnoweb\SmsSender\Tests\Unit\Enums;

use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;
use Karnoweb\SmsSender\Tests\TestCase;

class SmsSendStatusEnumTest extends TestCase
{
    public function test_has_all_expected_cases(): void
    {
        $this->assertCount(4, SmsSendStatusEnum::cases());
    }

    public function test_case_values_are_lowercase_strings(): void
    {
        $this->assertEquals('pending', SmsSendStatusEnum::PENDING->value);
        $this->assertEquals('sent', SmsSendStatusEnum::SENT->value);
        $this->assertEquals('delivered', SmsSendStatusEnum::DELIVERED->value);
        $this->assertEquals('failed', SmsSendStatusEnum::FAILED->value);
    }

    public function test_enum_can_be_created_from_value(): void
    {
        $status = SmsSendStatusEnum::from('pending');
        $this->assertSame(SmsSendStatusEnum::PENDING, $status);
    }

    public function test_invalid_value_throws_error(): void
    {
        $this->expectException(\ValueError::class);
        SmsSendStatusEnum::from('invalid_status');
    }

    public function test_all_cases_have_labels(): void
    {
        foreach (SmsSendStatusEnum::cases() as $case) {
            $label = $case->label();
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
        }
    }

    public function test_specific_labels(): void
    {
        $this->assertEquals('Pending', SmsSendStatusEnum::PENDING->label());
        $this->assertEquals('Sent', SmsSendStatusEnum::SENT->label());
        $this->assertEquals('Delivered', SmsSendStatusEnum::DELIVERED->label());
        $this->assertEquals('Failed', SmsSendStatusEnum::FAILED->label());
    }

    public function test_terminal_states(): void
    {
        $this->assertTrue(SmsSendStatusEnum::DELIVERED->isTerminal());
        $this->assertTrue(SmsSendStatusEnum::FAILED->isTerminal());
    }

    public function test_non_terminal_states(): void
    {
        $this->assertFalse(SmsSendStatusEnum::PENDING->isTerminal());
        $this->assertFalse(SmsSendStatusEnum::SENT->isTerminal());
    }

    public function test_checkable_returns_non_terminal_states(): void
    {
        $checkable = SmsSendStatusEnum::checkable();
        $this->assertCount(2, $checkable);
        $this->assertContains(SmsSendStatusEnum::PENDING, $checkable);
        $this->assertContains(SmsSendStatusEnum::SENT, $checkable);
    }

    public function test_checkable_does_not_include_terminal_states(): void
    {
        $checkable = SmsSendStatusEnum::checkable();
        $this->assertNotContains(SmsSendStatusEnum::DELIVERED, $checkable);
        $this->assertNotContains(SmsSendStatusEnum::FAILED, $checkable);
    }
}
