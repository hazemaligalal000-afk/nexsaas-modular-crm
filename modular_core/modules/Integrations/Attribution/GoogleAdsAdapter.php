<?php
/**
 * Integrations/Attribution/GoogleAdsAdapter.php
 *
 * Google Ads Lead Form webhook handler + Enhanced Conversions API.
 */

declare(strict_types=1);

namespace Integrations\Attribution;

class GoogleAdsAdapter
{
    private string $tenantId;
    private array $config;

    public function __construct(string $tenantId, array $config)
    {
        $this->tenantId = $tenantId;
        $this->config   = $config;
    }

    /**
     * Handle Google Ads Lead Form webhook.
     * 
     * Webhook payload structure:
     * {
     *   "gcl_id": "gclid_value",
     *   "campaign_id": "123456",
     *   "ad_group_id": "789012",
     *   "creative_id": "345678",
     *   "user_column_data": [
     *     {"column_id": "FIRST_NAME", "string_value": "John"},
     *     {"column_id": "LAST_NAME", "string_value": "Doe"},
     *     {"column_id": "EMAIL", "string_value": "john@example.com"},
     *     {"column_id": "PHONE_NUMBER", "string_value": "+201234567890"}
     *   ]
     * }
     */
    public function handleWebhook(array $payload): array
    {
        $leadData = $this->parseLeadData($payload);
        
        return [
            'tenant_id'   => $this->tenantId,
            'platform'    => 'google',
            'lead_data'   => $leadData,
            'raw_payload' => $payload,
        ];
    }

    /**
     * Parse Google Lead Form data into normalized format.
     */
    private function parseLeadData(array $payload): array
    {
        $data = [
            'gclid'       => $payload['gcl_id'] ?? null,
            'campaign_id' => $payload['campaign_id'] ?? null,
            'ad_group_id' => $payload['ad_group_id'] ?? null,
            'creative_id' => $payload['creative_id'] ?? null,
            'utm_source'  => 'google',
            'utm_medium'  => 'cpc',
        ];

        // Parse user column data
        foreach ($payload['user_column_data'] ?? [] as $column) {
            $columnId = $column['column_id'] ?? '';
            $value    = $column['string_value'] ?? '';

            switch ($columnId) {
                case 'FIRST_NAME':
                    $data['first_name'] = $value;
                    break;
                case 'LAST_NAME':
                    $data['last_name'] = $value;
                    break;
                case 'EMAIL':
                    $data['email'] = strtolower(trim($value));
                    break;
                case 'PHONE_NUMBER':
                    $data['phone'] = $value;
                    break;
                case 'COMPANY_NAME':
                    $data['company'] = $value;
                    break;
                case 'CITY':
                    $data['city'] = $value;
                    break;
                case 'COUNTRY':
                    $data['country'] = $value;
                    break;
            }
        }

        // Load campaign metadata from Google Ads API
        if (!empty($data['campaign_id'])) {
            $campaignData = $this->getCampaignMetadata($data['campaign_id']);
            $data['campaign_name'] = $campaignData['name'] ?? null;
            $data['ad_name']       = $campaignData['ad_name'] ?? null;
        }

        return $data;
    }

    /**
     * Send Enhanced Conversion to Google Ads.
     * 
     * Enhanced Conversions allow sending hashed user data (email, phone)
     * back to Google to improve conversion tracking accuracy.
     */
    public function sendEnhancedConversion(array $leadData, int $attributionId): bool
    {
        if (empty($this->config['conversion_action_id'])) {
            error_log("GoogleAdsAdapter: conversion_action_id not configured");
            return false;
        }

        $conversionActionId = $this->config['conversion_action_id'];
        $customerId         = $this->config['customer_id'] ?? '';

        // Build conversion payload
        $conversion = [
            'conversion_action' => "customers/{$customerId}/conversionActions/{$conversionActionId}",
            'conversion_date_time' => (new \DateTimeImmutable())->format('Y-m-d H:i:sP'),
            'conversion_value' => 1.0,
            'currency_code' => 'USD',
            'gclid' => $leadData['gclid'] ?? null,
        ];

        // Add user identifiers (hashed)
        $userIdentifiers = [];
        if (!empty($leadData['email'])) {
            $userIdentifiers[] = [
                'hashed_email' => hash('sha256', strtolower(trim($leadData['email'])))
            ];
        }
        if (!empty($leadData['phone'])) {
            $userIdentifiers[] = [
                'hashed_phone_number' => hash('sha256', preg_replace('/\D/', '', $leadData['phone']))
            ];
        }

        if (!empty($userIdentifiers)) {
            $conversion['user_identifiers'] = $userIdentifiers;
        }

        // Send to Google Ads API
        return $this->uploadConversion($customerId, $conversion);
    }

    /**
     * Upload conversion to Google Ads API.
     */
    private function uploadConversion(string $customerId, array $conversion): bool
    {
        $url = "https://googleads.googleapis.com/v14/customers/{$customerId}:uploadClickConversions";

        $payload = [
            'conversions' => [$conversion],
            'partial_failure' => true,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'developer-token' => $this->config['developer_token'] ?? '',
            'login-customer-id' => $customerId,
        ];

        $response = $this->post($url, $payload, $headers);

        if (isset($response['results']) && count($response['results']) > 0) {
            error_log("GoogleAdsAdapter: Enhanced Conversion uploaded successfully");
            return true;
        }

        error_log("GoogleAdsAdapter: Enhanced Conversion upload failed: " . json_encode($response));
        return false;
    }

    /**
     * Get campaign metadata from Google Ads API.
     */
    private function getCampaignMetadata(string $campaignId): array
    {
        // Simplified - in production query Google Ads API
        return [
            'name' => "Campaign {$campaignId}",
            'ad_name' => "Ad {$campaignId}",
        ];
    }

    /**
     * Get OAuth 2.0 access token (refresh if needed).
     */
    private function getAccessToken(): string
    {
        // Simplified - in production implement OAuth 2.0 refresh flow
        return $this->config['access_token'] ?? '';
    }

    /**
     * HTTP POST helper.
     */
    private function post(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $this->buildHeaderLines($headers),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string)$body, true) ?? [];
        $decoded['_http_status'] = $code;
        return $decoded;
    }

    private function buildHeaderLines(array $headers): array
    {
        $lines = ['Content-Type: application/json', 'Accept: application/json'];
        foreach ($headers as $k => $v) {
            $lines[] = "{$k}: {$v}";
        }
        return $lines;
    }
}
