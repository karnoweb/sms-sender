<?php

namespace Karnoweb\SmsSender\Models;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\SmsSender\Enums\SmsSendStatusEnum;

/**
 * Model for sent SMS log.
 *
 * @property int $id
 * @property string $driver
 * @property string|null $template
 * @property array|null $inputs
 * @property string $phone
 * @property string $message
 * @property string|null $provider_message_id
 * @property SmsSendStatusEnum $status
 * @property array|null $metadata
 */
class Sms extends Model
{
    public function getTable(): string
    {
        return config('sms.table', 'sms_messages');
    }

    protected $fillable = [
        'driver',
        'template',
        'inputs',
        'phone',
        'message',
        'provider_message_id',
        'status',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inputs'   => 'array',
            'metadata' => 'array',
            'status'   => SmsSendStatusEnum::class,
        ];
    }

    public function scopeCheckable($query)
    {
        return $query->whereIn('status', SmsSendStatusEnum::checkable());
    }

    public function scopeForDriver($query, string $driverName)
    {
        return $query->where('driver', $driverName);
    }

    public function scopeForPhone($query, string $phone)
    {
        return $query->where('phone', $phone);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function hasProviderMessageId(): bool
    {
        return ! empty($this->provider_message_id);
    }

    public function markAsSent(?string $providerMessageId = null): bool
    {
        $data = ['status' => SmsSendStatusEnum::SENT];
        if ($providerMessageId !== null) {
            $data['provider_message_id'] = $providerMessageId;
        }
        return $this->update($data);
    }

    public function markAsFailed(?string $reason = null): bool
    {
        $data = ['status' => SmsSendStatusEnum::FAILED];
        if ($reason !== null) {
            $data['metadata'] = array_merge($this->metadata ?? [], [
                'failure_reason' => $reason,
                'failed_at'      => now()->toDateTimeString(),
            ]);
        }
        return $this->update($data);
    }

    public function markAsDelivered(): bool
    {
        return $this->update([
            'status'   => SmsSendStatusEnum::DELIVERED,
            'metadata' => array_merge($this->metadata ?? [], [
                'delivered_at' => now()->toDateTimeString(),
            ]),
        ]);
    }
}
