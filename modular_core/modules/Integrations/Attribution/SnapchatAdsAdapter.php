<?php
/**
 * Attribution/SnapchatAdsAdapter.php
 *
 * Snapchat Lead Ads webhook + Conversions API.
 */

declare(strict_types=1);

namespace Integrations\Attribution;

use Integrations\Adapters\BaseAdapter;

class SnapchatAdsAdapter extends BaseAdapter
{
    private const CAPI_URL = 'https://tr.snapchat.com/v2/conversion';

    public function sendSMS(string $to, string $body): array { return []; }
    public function makeCall(string $to, string $from, string $callbackUrl): array { return []; }
    public function sendWhatsApp(string $to, string $message, array $media = []): array { return []; }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $sig      = $headers['x-snap-signature'] ?? '';
        $expected = hash_hmac('sha256', $rawBody, $this->config['app_secret'] ?? '');
        return empty($sig) || hash_equals($expected, $sig);
    }

    public function parseInboundWebhook(array $payload): array
    {
        $events = [];
        foreach ($payload['leads'] ?? [$payload] as $lead) {
            $events[] = [
                'type'            => 'lead_form',
                'platform'        => 'snapchat',
                'sccid'           => $lead['click_id']  ?? null,
                'platform_lead_id'=> $lead['lead_id']   ?? null,
                'platform_form_id'=> $lead['form_id']   ?? null,
                'ad_id'           => $lead['ad_id']     ?? null,
                'ad_set_id'       => $lead['ad_squad_id'] ?? null,
                'ad_platform_id'  => $lead['campaign_id'] ?? null,
                'user_data'       => $lead['answers']   ?? [],
                'raw'             => $lead,
                'is_test'         => false,
            ];
        }
        return $events;
    }

    /**
     * Send conversion event via Snapchat CAPI.
     */
    public function sendConversionEvent(array $eventData): array
    {
        $pixelId = $this->config['pixel_id']     ?? '';
        $token   = $this->config['access_token'] ?? '';

        $payload = [
            'pixel_id'   => $pixelId,
            'test_mode'  => false,
            'data'       => [[
                'event_type'       => $eventData['event_name'] ?? 'SIGN_UP',
                'event_conversion_type' => 'WEB',
                'timestamp'        => (string)(($eventData['event_time'] ?? time()) * 1000),
                'event_tag'        => $eventData['event_id'] ?? uniqid('sc_'),
                'hashed_email'     => hash('sha256', strtolower(trim($eventData['email'] ?? ''))),
                'hashed_phone_number' => hash('sha256', preg_replace('/\D/', '', $eventData['phone'] ?? '')),
                'click_id'         => $eventData['sccid'] ?? '',
                'price'            => $eventData['value'] ?? 0,
                'currency'         => $eventData['currency'] ?? 'EGP',
            ]],
        ];

        return $this->post(
            self::CAPI_URL,
            $payload,
            ['Authorization' => "Bearer {$token}"]
        );
    }
}
