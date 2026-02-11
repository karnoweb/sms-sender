<?php

namespace Karnoweb\SmsSender\Exceptions;

use Illuminate\Support\Facades\Lang;

class SmsSendFailedException extends SmsException
{
    private string $driverName;

    /** @var array<string, mixed> */
    private array $apiResponse;

    /**
     * @param array<string, mixed> $apiResponse
     */
    public function __construct(
        string $driverName,
        string $message,
        array $apiResponse = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->driverName  = $driverName;
        $this->apiResponse = $apiResponse;

        $fullMessage = Lang::get('sms-sender::messages.send_failed', [
            'driver'  => $driverName,
            'message' => $message,
        ]);

        parent::__construct($fullMessage, $code, $previous);
    }

    public function getDriverName(): string
    {
        return $this->driverName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getApiResponse(): array
    {
        return $this->apiResponse;
    }
}
