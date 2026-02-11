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
 * Kavenegar SMS driver.
 *
 * @see https://kavenegar.com/rest.html
 */
class KavenegarDriver extends AbstractSmsDriver implements SmsDriver, DeliveryReportFetcher
{
    protected Client $client;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->client = new Client([
            'base_uri' => 'https://api.kavenegar.com/v1/',
            'timeout'  => 30.0,
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);
    }

    /**
     * @param array<int, string> $recipients
     * @return array{message_id: string, raw?: array}
     */
    public function send(array $recipients, string $message, ?string $from = null): array
    {
        if (empty($this->config['token'])) {
            throw new DriverConnectionException('Kavenegar token not set.');
        }

        $sender = $this->sender($from);

        if (count($recipients) === 1) {
            return $this->sendSingle($recipients[0], $message, $sender);
        }

        return $this->sendBatch($recipients, $message, $sender);
    }

    /**
     * @return array{message_id: string, raw?: array}
     */
    private function sendSingle(string $phoneNumber, string $message, ?string $sender): array
    {
        $url = $this->config['token'] . '/sms/send.json';

        try {
            $response = $this->client->post($url, [
                'form_params' => [
                    'receptor' => $phoneNumber,
                    'message'  => $message,
                    'sender'   => $sender,
                ],
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200) {
                throw new DriverConnectionException(
                    'Kavenegar API error: ' . $this->parseErrorResponse($data)
                );
            }

            if (isset($data['return']['status']) && $data['return']['status'] !== 200) {
                throw new DriverConnectionException(
                    'Kavenegar API error: ' . ($data['return']['message'] ?? 'Unknown error')
                );
            }

            $messageId = $data['entries'][0]['messageid'] ?? 'kavenegar-' . uniqid();

            return ['message_id' => (string) $messageId, 'raw' => $data];
        } catch (DriverConnectionException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Kavenegar send failed', [
                'phone'      => $phoneNumber,
                'exception'  => $e->getMessage(),
            ]);
            throw new DriverConnectionException(
                'Failed to connect to Kavenegar API: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * @param array<int, string> $phoneNumbers
     * @return array{message_id: string, raw?: array}
     */
    private function sendBatch(array $phoneNumbers, string $message, ?string $sender): array
    {
        $url = $this->config['token'] . '/sms/sendarray.json';

        try {
            $response = $this->client->post($url, [
                'form_params' => [
                    'receptor' => $phoneNumbers,
                    'message'  => array_fill(0, count($phoneNumbers), $message),
                    'sender'   => array_fill(0, count($phoneNumbers), $sender ?? ''),
                ],
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200) {
                throw new DriverConnectionException(
                    'Kavenegar API error: ' . $this->parseErrorResponse($data)
                );
            }

            if (isset($data['return']['status']) && $data['return']['status'] !== 200) {
                throw new DriverConnectionException(
                    'Kavenegar API error: ' . ($data['return']['message'] ?? 'Unknown error')
                );
            }

            $first = $data['entries'][0]['messageid'] ?? null;
            $messageId = $first !== null ? (string) $first : 'kavenegar-batch-' . uniqid();

            return ['message_id' => $messageId, 'raw' => $data];
        } catch (DriverConnectionException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Kavenegar sendBatch failed', [
                'recipients' => $phoneNumbers,
                'exception'  => $e->getMessage(),
            ]);
            throw new DriverConnectionException(
                'Failed to connect to Kavenegar API: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * @return array{status: string, delivered_at?: string|null, raw?: mixed}
     */
    public function fetchDeliveryReport(string $providerMessageId): array
    {
        if (empty($this->config['token'])) {
            return ['status' => 'unknown', 'delivered_at' => null];
        }

        try {
            $url = $this->config['token'] . '/sms/select.json';
            $response = $this->client->post($url, [
                'form_params' => ['messageid' => $providerMessageId],
                'timeout'     => 10,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200) {
                return ['status' => 'unknown', 'delivered_at' => null];
            }

            if (isset($data['entries'][0])) {
                $entry  = $data['entries'][0];
                $status = $this->mapStatus((int) ($entry['status'] ?? 0));

                return [
                    'status'       => $status,
                    'delivered_at' => $status === 'delivered' ? now()->toIso8601String() : null,
                    'raw'          => $entry,
                ];
            }

            return ['status' => 'unknown', 'delivered_at' => null];
        } catch (Throwable $e) {
            Log::warning('Kavenegar delivery report failed', [
                'message_id' => $providerMessageId,
                'exception'  => $e->getMessage(),
            ]);

            return ['status' => 'unknown', 'delivered_at' => null];
        }
    }

    private function mapStatus(int $code): string
    {
        return match ($code) {
            1, 2 => 'pending',
            4, 5 => 'sent',
            10   => 'delivered',
            default => 'failed',
        };
    }

    private function parseErrorResponse(?array $data): string
    {
        if ($data === null) {
            return 'Unknown error';
        }
        if (isset($data['return']['message'])) {
            return (string) $data['return']['message'];
        }
        if (isset($data['message'])) {
            return (string) $data['message'];
        }

        return 'Unknown error';
    }
}
