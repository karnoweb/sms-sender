<?php

namespace Karnoweb\SmsSender\Tests\Unit\SmsManager;

use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Tests\TestCase;

class TemplateCompilerTest extends TestCase
{
    private SmsManager $manager;

    /** @var \ReflectionMethod */
    private \ReflectionMethod $compileMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->app->make(SmsManager::class);
        $this->compileMethod = new \ReflectionMethod(SmsManager::class, 'compileTemplate');
    }

    private function compile(string $template, array $inputs = []): string
    {
        return $this->compileMethod->invoke($this->manager, $template, $inputs);
    }

    public function test_empty_inputs_returns_template_unchanged(): void
    {
        $template = 'سلام {name}';
        $this->assertEquals($template, $this->compile($template));
        $this->assertEquals($template, $this->compile($template, []));
    }

    public function test_single_placeholder_replacement(): void
    {
        $result = $this->compile('کد ورود: {code}', ['code' => '1234']);
        $this->assertEquals('کد ورود: 1234', $result);
    }

    public function test_multiple_placeholder_replacement(): void
    {
        $result = $this->compile(
            'سلام {name}، کد شما: {code}',
            ['name' => 'علی', 'code' => '5678'],
        );
        $this->assertEquals('سلام علی، کد شما: 5678', $result);
    }

    public function test_placeholder_not_in_template_is_ignored(): void
    {
        $result = $this->compile('کد: {code}', ['code' => '1234', 'extra' => 'unused']);
        $this->assertEquals('کد: 1234', $result);
    }

    public function test_unmatched_placeholder_stays_in_output(): void
    {
        $result = $this->compile('سلام {name}، کد: {code}', ['code' => '1234']);
        $this->assertEquals('سلام {name}، کد: 1234', $result);
    }

    public function test_template_without_placeholders(): void
    {
        $result = $this->compile('پیام ثابت بدون متغیر', ['code' => '1234']);
        $this->assertEquals('پیام ثابت بدون متغیر', $result);
    }

    public function test_empty_template(): void
    {
        $result = $this->compile('', ['code' => '1234']);
        $this->assertEquals('', $result);
    }

    public function test_same_placeholder_used_twice(): void
    {
        $result = $this->compile('کد: {code} — تکرار: {code}', ['code' => '1234']);
        $this->assertEquals('کد: 1234 — تکرار: 1234', $result);
    }

    public function test_numeric_value_is_cast_to_string(): void
    {
        $result = $this->compile('عدد: {num}', ['num' => 42]);
        $this->assertEquals('عدد: 42', $result);
    }

    public function test_persian_placeholder_values(): void
    {
        $result = $this->compile('سلام {name}', ['name' => 'محمد']);
        $this->assertEquals('سلام محمد', $result);
    }

    public function test_persian_template_with_numbers(): void
    {
        $result = $this->compile('کد تأیید شما: {code}', ['code' => '۱۲۳۴']);
        $this->assertEquals('کد تأیید شما: ۱۲۳۴', $result);
    }
}
