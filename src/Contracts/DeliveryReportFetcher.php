<?php

namespace Karnoweb\SmsSender\Contracts;

/**
 * Optional contract for fetching SMS delivery report.
 */
interface DeliveryReportFetcher
{
    /**
     * @param string $providerMessageId Provider message ID
     * @return array{status: string, delivered_at?: string|null}
     */
    public function fetchDeliveryReport(string $providerMessageId): array;
}
