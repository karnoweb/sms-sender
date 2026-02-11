<?php

namespace Karnoweb\SmsSender\Validation;

use Karnoweb\SmsSender\Exceptions\InvalidMessageException;
use Karnoweb\SmsSender\Exceptions\InvalidPhoneNumberException;

class SmsValidator
{
    /** Iranian mobile pattern: 09xx, +989xx, 989xx, 00989xx */
    private const IRAN_MOBILE_PATTERN = '/^(?:(?:\+98|0098|98|0)9\d{9})$/';

    private int $maxMessageLength;

    public function __construct(?int $maxMessageLength = null)
    {
        $this->maxMessageLength = $maxMessageLength ?? (int) config('sms.validation.max_message_length', 500);
    }

    /**
     * @param array<int, string> $recipients
     */
    public function validate(array $recipients, string $message): void
    {
        $this->validateRecipients($recipients);
        $this->validateMessage($message);
    }

    /**
     * @param array<int, string> $recipients
     */
    public function validateRecipients(array $recipients): void
    {
        if (empty($recipients)) {
            throw InvalidPhoneNumberException::empty();
        }

        foreach ($recipients as $number) {
            $this->validatePhoneNumber($number);
        }
    }

    public function validatePhoneNumber(string $number): void
    {
        $cleaned = $this->cleanNumber($number);

        if (empty($cleaned)) {
            throw InvalidPhoneNumberException::empty();
        }

        if (! preg_match(self::IRAN_MOBILE_PATTERN, $cleaned)) {
            throw InvalidPhoneNumberException::invalidFormat($number);
        }
    }

    public function validateMessage(string $message): void
    {
        $trimmed = trim($message);

        if (empty($trimmed)) {
            throw InvalidMessageException::empty();
        }

        $length = mb_strlen($trimmed);
        if ($length > $this->maxMessageLength) {
            throw InvalidMessageException::tooLong($length, $this->maxMessageLength);
        }
    }

    public function normalizeNumber(string $number): string
    {
        $cleaned = $this->cleanNumber($number);

        $cleaned = preg_replace('/^(?:\+98|0098|98)/', '0', $cleaned);

        return $cleaned;
    }

    /**
     * @param array<int, string> $numbers
     * @return array<int, string>
     */
    public function normalizeNumbers(array $numbers): array
    {
        return array_map(fn (string $number) => $this->normalizeNumber($number), $numbers);
    }

    private function cleanNumber(string $number): string
    {
        return (string) preg_replace('/[\s\-\(\)]/', '', trim($number));
    }
}
