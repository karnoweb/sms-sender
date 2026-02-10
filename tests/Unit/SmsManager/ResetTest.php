<?php

namespace Karnoweb\SmsSender\Tests\Unit\SmsManager;

use Karnoweb\SmsSender\Enums\SmsTemplateEnum;
use Karnoweb\SmsSender\SmsManager;
use Karnoweb\SmsSender\Tests\TestCase;

class ResetTest extends TestCase
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

    private function callReset(): void
    {
        $method = new \ReflectionMethod(SmsManager::class, 'reset');
        $method->invoke($this->manager);
    }

    public function test_reset_clears_all_builder_state(): void
    {
        $this->manager
            ->otp(SmsTemplateEnum::LOGIN_OTP)
            ->input('code', '1234')
            ->number('09120000000')
            ->numbers(['09130000000']);

        $this->assertNotEmpty($this->getProperty('toNumbers'));
        $this->assertNotNull($this->getProperty('templateText'));
        $this->assertNotNull($this->getProperty('templateName'));
        $this->assertNotEmpty($this->getProperty('inputs'));

        $this->callReset();

        $this->assertEmpty($this->getProperty('toNumbers'));
        $this->assertNull($this->getProperty('messageText'));
        $this->assertNull($this->getProperty('templateText'));
        $this->assertNull($this->getProperty('templateName'));
        $this->assertEmpty($this->getProperty('inputs'));
    }

    public function test_reset_clears_message_text(): void
    {
        $this->manager->message('سلام');
        $this->callReset();
        $this->assertNull($this->getProperty('messageText'));
    }

    public function test_reset_allows_clean_reuse(): void
    {
        $this->manager->message('اول')->number('09120000000');
        $this->callReset();
        $this->manager->message('دوم')->number('09130000000');
        $this->assertEquals('دوم', $this->getProperty('messageText'));
        $this->assertEquals(['09130000000'], $this->getProperty('toNumbers'));
    }
}
