<?php

namespace Karnoweb\SmsSender\Enums;

enum SmsSendStatusEnum: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING   => 'در انتظار ارسال',
            self::SENT      => 'ارسال شده',
            self::DELIVERED => 'تحویل داده شده',
            self::FAILED    => 'ناموفق',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::DELIVERED, self::FAILED => true,
            default => false,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function checkable(): array
    {
        return [
            self::PENDING,
            self::SENT,
        ];
    }
}
