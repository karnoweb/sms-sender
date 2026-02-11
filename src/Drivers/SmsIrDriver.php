<?php

declare(strict_types=1);

namespace Karnoweb\SmsSender\Drivers;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Karnoweb\SmsSender\Contracts\DeliveryReportFetcher;
use Karnoweb\SmsSender\Contracts\SmsDriver;
use Karnoweb\SmsSender\Exceptions\DriverConnectionException;
use Throwable;

/**
 * SMS.ir driver.
 *
 * @see https://sms.ir/rest-api
 */
class SmsIrDriver extends AbstractSmsDriver implements SmsDriver, DeliveryReportFetcher
{
    protected Client $client;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->client = new Client([
            'base_uri' => 'https://api.sms.ir/v1/',
            'timeout'  => 30.0,
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * @param array<int, string> $recipients
     * @return array{message_id: string, raw?: array}
     */
    public function send(array $recipients, string $message, ?string $from = null): array
    {
        if (empty($this->config['api_key'])) {
            throw new DriverConnectionException('SMS.ir API key not set.');
        }

        $sender = $this->sender($from);

        try {
            $response = $this->client->post('send/bulk', [
                'headers' => [
                    'X-API-KEY' => $this->config['api_key'],
                ],
                'json' => [
                    'lineNumber'   => $sender,
                    'messageText'  => $message,
                    'mobiles'      => array_values($recipients),
                    'sendDateTime' => null,
                ],
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 201) {
                throw new DriverConnectionException(
                    'SMS.ir API error: ' . $this->parseErrorResponse($data)
                );
            }

            if (isset($data['status']) && $data['status'] !== 1) {
                throw new DriverConnectionException(
                    'SMS.ir API error: ' . $this->getErrorMessage((int) $data['status'])
                );
            }

            $messageId = $data['data']['messageId'] ?? $data['data']['message_id'] ?? 'smsir-' . uniqid();

            return ['message_id' => (string) $messageId, 'raw' => $data];
        } catch (DriverConnectionException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('SMS.ir send failed', [
                'recipients' => $recipients,
                'exception'   => $e->getMessage(),
            ]);
            throw new DriverConnectionException(
                'Failed to connect to SMS.ir API: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * @return array{status: string, delivered_at?: string|null, raw?: mixed}
     */
    public function fetchDeliveryReport(string $providerMessageId): array
    {
        if (empty($this->config['api_key'])) {
            return ['status' => 'unknown', 'delivered_at' => null];
        }

        try {
            $response = $this->client->get('send/live/' . $providerMessageId, [
                'headers' => [
                    'X-API-KEY' => $this->config['api_key'],
                ],
                'timeout' => 10,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200) {
                return ['status' => 'unknown', 'delivered_at' => null];
            }

            if (isset($data['data']['deliveryState'])) {
                $status = $this->mapStatus((int) $data['data']['deliveryState']);

                return [
                    'status'       => $status,
                    'delivered_at' => $status === 'delivered' ? now()->toIso8601String() : null,
                    'raw'          => $data['data'],
                ];
            }

            return ['status' => 'unknown', 'delivered_at' => null];
        } catch (Throwable $e) {
            Log::warning('SMS.ir delivery report failed', [
                'message_id' => $providerMessageId,
                'exception'   => $e->getMessage(),
            ]);

            return ['status' => 'unknown', 'delivered_at' => null];
        }
    }

    private function mapStatus(int $code): string
    {
        return match ($code) {
            1 => 'delivered',
            3 => 'pending',
            5 => 'sent',
            2, 4, 6, 7 => 'failed',
            default => 'unknown',
        };
    }

    private function getErrorMessage(int $statusCode): string
    {
        $messages = [
            0   => 'System error',
            10  => 'Invalid API key',
            11  => 'API key disabled',
            12  => 'IP restriction',
            13  => 'Account inactive',
            14  => 'Account suspended',
            20  => 'Rate limit exceeded',
            101 => 'Invalid line number',
            102 => 'Insufficient credit',
            103 => 'Empty message',
            104 => 'Invalid mobile',
            105 => 'Too many mobiles',
            106 => 'Too many messages',
            107 => 'Empty mobiles list',
            108 => 'Empty messages list',
            109 => 'Invalid send time',
            110 => 'Mobiles and messages count mismatch',
            111 => 'Message not found',
            112 => 'Record not found',
            113 => 'Template not found',
            114 => 'Parameter too long',
            115 => 'Blacklisted mobile',
            116 => 'Empty parameter name',
            117 => 'Message not approved',
            118 => 'Too many messages',
            119 => 'Upgrade plan for custom template',
            123 => 'Line needs activation',
        ];

        return $messages[$statusCode] ?? 'Unknown error (code: ' . $statusCode . ')';
    }

    private function parseErrorResponse(?array $data): string
    {
        if ($data === null) {
            return 'Unknown error';
        }
        if (isset($data['status']) && is_numeric($data['status'])) {
            return $this->getErrorMessage((int) $data['status']);
        }
        if (isset($data['message'])) {
            return (string) $data['message'];
        }

        return 'Unknown error';
    }
}
