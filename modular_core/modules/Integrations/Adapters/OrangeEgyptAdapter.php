<?php
/**
 * Integrations/Adapters/OrangeEgyptAdapter.php
 *
 * Orange Egypt (formerly Mobinil) — #2 operator, OAuth 2.0 REST API.
 */

declare(strict_types=1);

namespace Integrations\Adapters;

class OrangeEgyptAdapter extends BaseAdapter
{
    const AUTH_URL = 'https://api.orange.com/oauth/v3/token';
    const SMS_URL  = 'https://api.orange.com/smsmessaging/v1/outbound/{senderAddress}/requests';

    public function sendSMS(string $to, string $body): array
    {
        $token         = $this->getAccessToken();
        $senderAddress = urlencode('tel:+20' . $this->config['short_code']);
        $url           = str_replace('{senderAddress}', $senderAddress, self::SMS_URL);

        return $this->post($url, [
            'outboundSMSMessageRequest' => [
                'address'                => ['tel:+20' . ltrim($to, '0')],
                'senderAddress'          => 'tel:+20' . $this->config['short_code'],
                'outboundSMSTextMessage' => ['message' => $body],
                'receiptRequest'         => ['notifyURL' => $this->config['webhook_url'] ?? ''],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);
    }

    public function makeCall(string $to, string $from, string $callbackUrl): array
    {
        return ['status' => 'not_supported'];
    }

    public function sendWhatsApp(string $to, string $message, array $media = []): array
    {
        return ['status' => 'not_supported'];
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        return true;
    }

    public function parseInboundWebhook(array $payload): array
    {
        $req = $payload['inboundSMSMessageNotification']['inboundSMSMessage'] ?? [];
        return [
            'type'     => 'sms_received',
            'from'     => $req['senderAddress'] ?? '',
            'to'       => $req['destinationAddress'] ?? '',
            'body'     => $req['message'] ?? '',
            'call_sid' => $req['messageId'] ?? uniqid(),
            'duration' => 0,
            'recording'=> '',
            'raw'      => $payload,
        ];
    }

    private function getAccessToken(): string
    {
        $cacheKey = "orange_eg:token:{$this->tenantId}";
        // In production: check Redis cache first
        $response = $this->post(self::AUTH_URL, [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]);
        return $response['access_token'] ?? '';
    }
}
