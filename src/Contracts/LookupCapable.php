<?php

declare(strict_types=1);

namespace Karnoweb\SmsSender\Contracts;

/**
 * Drivers that send OTP/verification via provider-defined templates (e.g. Kavenegar verify/lookup).
 */
interface LookupCapable
{
    /**
     * @param array<string, string> $inputs e.g. ['token' => '482910', 'token2' => '...']
     * @return array{message_id: string, raw?: array}
     */
    public function lookup(
        string $receptor,
        string $template,
        array $inputs = [],
        ?string $type = 'sms',
    ): array;
}
