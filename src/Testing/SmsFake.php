<?php

namespace Karnoweb\SmsSender\Testing;

use Karnoweb\SmsSender\Enums\SmsTemplateEnum;
use Illuminate\Support\Collection;
use Karnoweb\SmsSender\Response\SmsResponse;
use PHPUnit\Framework\Assert;

class SmsFake
{
    /** @var Collection<int, array<string, mixed>> */
    private Collection $sentMessages;

    /** @var Collection<int, array<string, mixed>> */
    private Collection $queuedMessages;

    private bool $shouldFail = false;

    /** @var array<int, string> */
    private array $recipients = [];

    private string $message = '';

    private ?string $templateBody = null;

    /** @var array<string, string> */
    private array $inputs = [];

    public function __construct()
    {
        $this->sentMessages   = collect();
        $this->queuedMessages = collect();
    }

    public function send(): SmsResponse
    {
        $message = $this->resolveMessage();
        if ($this->shouldFail) {
            $this->sentMessages->push([
                'recipients' => $this->recipients,
                'message'    => $message,
                'driver'     => 'fake',
                'status'     => 'failed',
            ]);

            return SmsResponse::failure('fake', $this->recipients, 'Fake failure for testing');
        }

        $this->sentMessages->push([
            'recipients' => $this->recipients,
            'message'    => $message,
            'driver'     => 'fake',
            'status'     => 'sent',
        ]);

        return SmsResponse::success(
            driverName: 'fake',
            recipients: $this->recipients,
            messageId: 'fake-' . uniqid()
        );
    }

    private function resolveMessage(): string
    {
        if ($this->message !== '') {
            return $this->message;
        }
        if ($this->templateBody !== null) {
            $text = $this->templateBody;
            foreach ($this->inputs as $key => $value) {
                $text = str_replace('{' . $key . '}', (string) $value, $text);
            }
            return $text;
        }
        return '';
    }

    public function queue(?string $queueName = null): void
    {
        $this->queuedMessages->push([
            'recipients' => $this->recipients,
            'message'    => $this->resolveMessage(),
            'queue'      => $queueName,
        ]);
    }

    public function later(int $delaySeconds, ?string $queueName = null): void
    {
        $this->queuedMessages->push([
            'recipients' => $this->recipients,
            'message'    => $this->resolveMessage(),
            'queue'      => $queueName,
            'delay'      => $delaySeconds,
        ]);
    }

    public function shouldFail(bool $fail = true): self
    {
        $this->shouldFail = $fail;

        return $this;
    }

    public function assertSent(string $recipient, ?string $message = null): void
    {
        $found = $this->sentMessages->contains(function ($item) use ($recipient, $message) {
            $recipientMatch = in_array($recipient, $item['recipients'], true);
            $messageMatch  = $message !== null ? $item['message'] === $message : true;

            return $recipientMatch && $messageMatch;
        });

        Assert::assertTrue($found, __('sms-sender::messages.assert_sent', ['recipient' => $recipient]));
    }

    public function assertNotSent(string $recipient): void
    {
        $found = $this->sentMessages->contains(function ($item) use ($recipient) {
            return in_array($recipient, $item['recipients'], true);
        });

        Assert::assertFalse($found, __('sms-sender::messages.assert_not_sent', ['recipient' => $recipient]));
    }

    public function assertNothingSent(): void
    {
        Assert::assertCount(0, $this->sentMessages, __('sms-sender::messages.assert_nothing_sent'));
    }

    public function assertSentCount(int $expectedCount): void
    {
        $actualCount = $this->sentMessages->count();
        Assert::assertEquals(
            $expectedCount,
            $actualCount,
            __('sms-sender::messages.assert_sent_count', ['actual' => $actualCount, 'expected' => $expectedCount])
        );
    }

    public function assertQueued(string $recipient): void
    {
        $found = $this->queuedMessages->contains(function ($item) use ($recipient) {
            return in_array($recipient, $item['recipients'], true);
        });

        Assert::assertTrue($found, __('sms-sender::messages.assert_queued', ['recipient' => $recipient]));
    }

    public function assertNothingQueued(): void
    {
        Assert::assertCount(0, $this->queuedMessages, __('sms-sender::messages.assert_nothing_queued'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getSentMessages(): Collection
    {
        return $this->sentMessages;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getQueuedMessages(): Collection
    {
        return $this->queuedMessages;
    }

    public function reset(): void
    {
        $this->sentMessages   = collect();
        $this->queuedMessages = collect();
        $this->shouldFail     = false;
        $this->recipients     = [];
        $this->message        = '';
        $this->templateBody   = null;
        $this->inputs         = [];
    }

    public function numbers(array $numbers): self
    {
        $this->recipients = array_values($numbers);

        return $this;
    }

    public function number(string $phone): self
    {
        $this->recipients[] = $phone;

        return $this;
    }

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function from(string $from): self
    {
        return $this;
    }

    public function driver(string $driver): self
    {
        return $this;
    }

    public function template(string $key, string $body): self
    {
        $this->templateBody = $body;
        return $this;
    }

    public function otp(\Karnoweb\SmsSender\Enums\SmsTemplateEnum $template): self
    {
        $this->templateBody = $template->templateText();
        return $this;
    }

    public function input(string $key, string $value): self
    {
        $this->inputs[$key] = $value;
        return $this;
    }

    public function inputs(array $inputs): self
    {
        $this->inputs = array_merge($this->inputs, $inputs);
        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function checkStatus(): array
    {
        return [];
    }
}
