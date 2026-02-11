<?php

namespace Karnoweb\SmsSender\Response;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class SmsResponse implements Arrayable, Jsonable
{
    private bool $success;

    private string $driverName;

    private ?string $messageId;

    private ?string $errorMessage;

    /** @var array<string, mixed> */
    private array $rawResponse;

    /** @var array<int, string> */
    private array $recipients;

    private float $cost;

    /**
     * @param array<int, string> $recipients
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        bool $success,
        string $driverName,
        array $recipients = [],
        ?string $messageId = null,
        ?string $errorMessage = null,
        array $rawResponse = [],
        float $cost = 0.0
    ) {
        $this->success       = $success;
        $this->driverName    = $driverName;
        $this->recipients    = $recipients;
        $this->messageId     = $messageId;
        $this->errorMessage  = $errorMessage;
        $this->rawResponse   = $rawResponse;
        $this->cost          = $cost;
    }

    /**
     * @param array<int, string> $recipients
     * @param array<string, mixed> $rawResponse
     */
    public static function success(
        string $driverName,
        array $recipients,
        ?string $messageId = null,
        array $rawResponse = []
    ): self {
        return new self(
            success: true,
            driverName: $driverName,
            recipients: $recipients,
            messageId: $messageId,
            rawResponse: $rawResponse
        );
    }

    /**
     * @param array<int, string> $recipients
     * @param array<string, mixed> $rawResponse
     */
    public static function failure(
        string $driverName,
        array $recipients,
        string $errorMessage,
        array $rawResponse = []
    ): self {
        return new self(
            success: false,
            driverName: $driverName,
            recipients: $recipients,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse
        );
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function isFailed(): bool
    {
        return ! $this->success;
    }

    public function getDriverName(): string
    {
        return $this->driverName;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    /**
     * @return array<int, string>
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function getCost(): float
    {
        return $this->cost;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success'       => $this->success,
            'driver'        => $this->driverName,
            'message_id'    => $this->messageId,
            'recipients'    => $this->recipients,
            'error_message' => $this->errorMessage,
            'cost'          => $this->cost,
            'raw_response'  => $this->rawResponse,
        ];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_UNESCAPED_UNICODE);
    }

    public function __toString(): string
    {
        $status = $this->success ? '✓' : '✗';

        return "[{$status}] Driver: {$this->driverName}, MessageId: " . ($this->messageId ?? 'null');
    }
}
