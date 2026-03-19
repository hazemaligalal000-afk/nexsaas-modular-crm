<?php
/**
 * Attribution/MetaLeadFormAdapter.php
 *
 * Meta Lead Ads webhook verification, lead data fetch, and CAPI conversion events.
 */

declare(strict_types=1);

namespace Integrations\Attribution;

use Integrations\Adapters\BaseAdapter;

class MetaLeadFormAdapter extends BaseAdapter
{
    private const GRAPH_URL = 'https://graph.facebook.com/v19.0';

    // ── BaseAdapter stubs ────────────────────────────────────────────────────

    public function sendSMS(string $to, string $body): array { return []; }
    public function makeCall(string $to, string $from, string $callbackUrl): array { return []; }
    public function sendWhatsApp(string $to, string $message, array $media = []): array { return []; }

    /**
     * Verify Meta webhook: return hub.challenge on GET, HMAC on POST.
     */
    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        if (empty($rawBody)) {
            // GET verification — challenge handled by controller
            return true;
        }

        $sig = $headers['x-hub-signature-256'] ?? $headers['X-Hub-Signature-256'] ?? '';
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $this->config['app_secret'] ?? '');
        return hash_equals($expected, $sig);
    }

    /**
     * Parse Meta Lead Ads webhook payload.
     * Returns array of normalised lead events.
     */
    public function parseInboundWebhook(array $payload): array
    {
        $events = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') !== 'leadgen') {
                    continue;
                }
                $value = $change['value'] ?? [];
                $events[] = [
                    'type'            => 'lead_form',
                    'platform'        => 'meta',
                    'platform_lead_id'=> $value['leadgen_id'] ?? null,
                    'platform_form_id'=> $value['form_id']    ?? null,
                    'ad_id'           => $value['ad_id']      ?? null,
                    'ad_set_id'       => $value['adset_id']   ?? null,
                    'ad_platform_id'  => $value['campaign_id'] ?? null,
                    'page_id'         => $entry['id']         ?? null,
                    'raw'             => $value,
                    'is_test'         => ($value['is_organic'] ?? false),
                ];
            }
        }

        return $events;
    }

    /**
     * Fetch full lead data from Graph API.
     */
    public function fetchLeadData(string $leadgenId): array
    {
        $token = $this->config['access_token'] ?? '';
        return $this->get(
            self::GRAPH_URL . "/{$leadgenId}",
            ['access_token' => $token, 'fields' => 'field_data,created_time,ad_id,form_id,campaign_id,adset_id']
        );
    }

    /**
     * Send a conversion event via Meta CAPI.
     */
    public function sendConversionEvent(array $eventData): array
    {
        $pixelId = $this->config['pixel_id'] ?? '';
        $token   = $this->config['access_token'] ?? '';

        $payload = [
            'data' => [[
                'event_name'       => $eventData['event_name'] ?? 'Lead',
                'event_time'       => $eventData['event_time'] ?? time(),
                'event_id'         => $eventData['event_id']   ?? uniqid('ev_'),
                'action_source'    => 'website',
                'user_data'        => $this->hashUserData($eventData['user_data'] ?? []),
                'custom_data'      => $eventData['custom_data'] ?? [],
            ]],
            'access_token' => $token,
            'test_event_code' => $this->config['test_event_code'] ?? null,
        ];

        // Remove null test_event_code
        if ($payload['test_event_code'] === null) {
            unset($payload['test_event_code']);
        }

        return $this->post(
            self::GRAPH_URL . "/{$pixelId}/events",
            $payload
        );
    }

    /**
     * Hash PII fields for CAPI (SHA-256, lowercase, trimmed).
     */
    private function hashUserData(array $userData): array
    {
        $hashed = [];
        $hashFields = ['em', 'ph', 'fn', 'ln', 'ct', 'st', 'zp', 'country'];

        foreach ($userData as $key => $value) {
            if (in_array($key, $hashFields, true) && !empty($value)) {
                $hashed[$key] = hash('sha256', strtolower(trim((string)$value)));
            } else {
                $hashed[$key] = $value;
            }
        }

        return $hashed;
    }
}
