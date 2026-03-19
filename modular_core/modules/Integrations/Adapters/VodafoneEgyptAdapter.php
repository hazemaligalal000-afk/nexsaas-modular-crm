<?php
/**
 * Integrations/Adapters/VodafoneEgyptAdapter.php
 *
 * Vodafone Egypt — SMS, USSD. Market leader, 55%+ share.
 */

declare(strict_types=1);

namespace Integrations\Adapters;

class VodafoneEgyptAdapter extends BaseAdapter
{
    const API_BASE = 'https://api.vodafone.com.eg/v1';

    public function sendSMS(string $to, string $body): array
    {
        return $this->post(self::API_BASE . '/messages/sms', [
            'app_key'     => $this->config['app_key'],
            'receiver'    => $this->normalizeEgyptianNumber($to),
            'sender_name' => $this->config['sender_name'],
            'message'     => $body,
            'lang'        => $this->detectLanguage($body),
        ]);
    }

    public function makeCall(string $to, string $from, string $callbackUrl): array
    {
        // Vodafone Egypt does not provide a direct voice API — use SIP trunk
        return ['status' => 'not_supported', 'message' => 'Use SIP trunk for voice calls'];
    }

    public function sendWhatsApp(string $to, string $message, array $media = []): array
    {
        return ['status' => 'not_supported', 'message' => 'Use e& or Infobip for WhatsApp'];
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $secret    = $this->config['webhook_secret'] ?? '';
        $signature = $headers['X-Vodafone-Signature'] ?? '';
        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    public function parseInboundWebhook(array $payload): array
    {
        // USSD callback: { msisdn, sessionId, type: Begin|Continue|End, input }
        if (isset($payload['sessionId'])) {
            return [
                'type'     => 'ussd_received',
                'from'     => $payload['msisdn'] ?? '',
                'to'       => '',
                'body'     => $payload['input'] ?? '',
                'call_sid' => $payload['sessionId'] ?? '',
                'duration' => 0,
                'recording'=> '',
                'raw'      => $payload,
            ];
        }

        return [
            'type'     => 'sms_received',
            'from'     => $payload['sender']  ?? '',
            'to'       => $payload['receiver'] ?? '',
            'body'     => $payload['message'] ?? '',
            'call_sid' => $payload['messageId'] ?? uniqid(),
            'duration' => 0,
            'recording'=> '',
            'raw'      => $payload,
        ];
    }
}
