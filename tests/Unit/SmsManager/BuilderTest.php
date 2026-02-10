<?php

namespace Karnoweb\SmsSender\Tests\Unit\SmsManager;

use Karnoweb\SmsSender\Enums\SmsTemplateEnum;
use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Tests\TestCase;

class BuilderTest extends TestCase
{
    private SmsManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->app->make(SmsManager::class);
    }

    private function getProperty(string $name): mixed
    {
        $reflection = new \ReflectionProperty(SmsManager::class, $name);

        return $reflection->getValue($this->manager);
    }

    public function test_message_sets_message_text(): void
    {
        $result = $this->manager->message('سلام دنیا');
        $this->assertSame($this->manager, $result);
        $this->assertEquals('سلام دنیا', $this->getProperty('messageText'));
    }

    public function test_message_overwrites_previous_message(): void
    {
        $this->manager->message('اول')->message('دوم');
        $this->assertEquals('دوم', $this->getProperty('messageText'));
    }

    public function test_otp_sets_template_text_from_enum(): void
    {
        $result = $this->manager->otp(SmsTemplateEnum::LOGIN_OTP);
        $this->assertSame($this->manager, $result);
        $this->assertEquals(SmsTemplateEnum::LOGIN_OTP->value, $this->getProperty('templateText'));
    }

    public function test_otp_sets_template_name(): void
    {
        $this->manager->otp(SmsTemplateEnum::LOGIN_OTP);
        $this->assertEquals('LOGIN_OTP', $this->getProperty('templateName'));
    }

    public function test_input_adds_single_key_value(): void
    {
        $result = $this->manager->input('code', '1234');
        $this->assertSame($this->manager, $result);
        $this->assertEquals(['code' => '1234'], $this->getProperty('inputs'));
    }

    public function test_input_can_be_called_multiple_times(): void
    {
        $this->manager->input('code', '1234')->input('name', 'علی');
        $this->assertEquals(['code' => '1234', 'name' => 'علی'], $this->getProperty('inputs'));
    }

    public function test_input_overwrites_same_key(): void
    {
        $this->manager->input('code', '1234')->input('code', '5678');
        $this->assertEquals(['code' => '5678'], $this->getProperty('inputs'));
    }

    public function test_inputs_merges_array(): void
    {
        $result = $this->manager->inputs(['code' => '1234', 'name' => 'علی']);
        $this->assertSame($this->manager, $result);
        $this->assertEquals(['code' => '1234', 'name' => 'علی'], $this->getProperty('inputs'));
    }

    public function test_inputs_merges_with_existing(): void
    {
        $this->manager->input('code', '1234')->inputs(['name' => 'علی', 'code' => '5678']);
        $this->assertEquals(['code' => '5678', 'name' => 'علی'], $this->getProperty('inputs'));
    }

    public function test_number_adds_single_phone(): void
    {
        $result = $this->manager->number('09120000000');
        $this->assertSame($this->manager, $result);
        $this->assertEquals(['09120000000'], $this->getProperty('toNumbers'));
    }

    public function test_number_can_be_called_multiple_times(): void
    {
        $this->manager->number('09120000000')->number('09130000000');
        $this->assertEquals(['09120000000', '09130000000'], $this->getProperty('toNumbers'));
    }

    public function test_numbers_adds_array_of_phones(): void
    {
        $result = $this->manager->numbers(['09120000000', '09130000000']);
        $this->assertSame($this->manager, $result);
        $this->assertEquals(['09120000000', '09130000000'], $this->getProperty('toNumbers'));
    }

    public function test_numbers_casts_to_string(): void
    {
        $this->manager->numbers([9120000000, 9130000000]);
        $numbers = $this->getProperty('toNumbers');
        foreach ($numbers as $number) {
            $this->assertIsString($number);
        }
    }

    public function test_numbers_appends_to_existing(): void
    {
        $this->manager->number('09120000000')->numbers(['09130000000', '09140000000']);
        $this->assertCount(3, $this->getProperty('toNumbers'));
    }

    public function test_full_fluent_chain_returns_same_instance(): void
    {
        $result = $this->manager
            ->otp(SmsTemplateEnum::LOGIN_OTP)
            ->input('code', '1234')
            ->number('09120000000');
        $this->assertSame($this->manager, $result);
    }
}
