<?php
/**
 * Attribution/TikTokAdsAdapter.php
 *
 * TikTok Lead Generation webhook + Events API (CAPI).
 */

declare(strict_types=1);

namespace Integrations\Attribution;

use Integrations\Adapters\BaseAdapter;

class TikTokAdsAdapter extends BaseAdapter
{
    private const EVENTS_URL = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    public function sendSMS(string $to, string $body): array { return []; }
    public function makeCall(string $to, string $from, string $callbackUrl): array { return []; }
    public function sendWhatsApp(string $to, string $message, array $media = []): array { return []; }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $sig      = $headers['x-tiktok-signature'] ?? '';
        $expected = hash_hmac('sha256', $rawBody, $this->config['app_secret'] ?? '');
        return empty($sig) || hash_equals($expected, $sig);
    }

    public function parseInboundWebhook(array $payload): array
    {
        $events = [];
        foreach ($payload['data'] ?? [$payload] as $item) {
            $events[] = [
                'type'            => 'lead_form',
                'platform'        => 'tiktok',
                'ttclid'          => $item['click_id'] ?? null,
                'platform_lead_id'=> $item['lead_id']  ?? null,
                'platform_form_id'=> $item['form_id']  ?? null,
                'ad_id'           => $item['ad_id']    ?? null,
                'ad_set_id'       => $item['adgroup_id'] ?? null,
                'ad_platform_id'  => $item['campaign_id'] ?? null,
                'user_data'       => $item['fields']   ?? [],
                'raw'             => $item,
                'is_test'         => ($item['test_event'] ?? false),
            ];
        }
        return $events;
    }

    /**
     * Send event via TikTok Events API.
     */
    public function sendConversionEvent(array $eventData): array
    {
        $pixelCode = $this->config['pixel_code'] ?? '';
        $token     = $this->config['access_token'] ?? '';

        $payload = [
            'pixel_code' => $pixelCode,
            'event'      => $eventData['event_name'] ?? 'SubmitForm',
            'event_id'   => $eventData['event_id']   ?? uniqid('tt_'),
            'timestamp'  => (string)($eventData['event_time'] ?? time()),
            'context'    => [
                'user' => [
                    'email'        => hash('sha256', strtolower(trim($eventData['email'] ?? ''))),
                    'phone_number' => hash('sha256', preg_replace('/\D/', '', $eventData['phone'] ?? '')),
                ],
                'ad' => [
                    'callback' => $eventData['ttclid'] ?? '',
                ],
            ],
            'properties' => [
                'currency' => $eventData['currency'] ?? 'EGP',
                'value'    => $eventData['value']    ?? 0,
            ],
        ];

        return $this->post(
            self::EVENTS_URL,
            $payload,
            ['Access-Token' => $token]
        );
    }
}
