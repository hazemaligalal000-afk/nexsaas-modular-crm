<?php
/**
 * Integrations/Adapters/UnifonicAdapter.php
 *
 * Unifonic — Saudi-born CPaaS covering all GCC + Egypt via one API.
 * Recommended for KSA and GCC-wide SMS/voice.
 */

declare(strict_types=1);

namespace Integrations\Adapters;

class UnifonicAdapter extends BaseAdapter
{
    const API_BASE  = 'https://api.unifonic.com/rest/';
    const SMS_PATH  = 'Messages/Send';
    const CALL_PATH = 'Voice/Call';

    public function sendSMS(string $to, string $body): array
    {
        return $this->post(self::API_BASE . self::SMS_PATH, [
            'AppSid'    => $this->config['app_sid'],
            'SenderID'  => $this->config['sender_id'],
            'Recipient' => $this->normalizeGCC($to, '966'),
            'Body'      => $body,
        ]);
    }

    public function makeCall(string $to, string $from, string $callbackUrl): array
    {
        return $this->post(self::API_BASE . self::CALL_PATH, [
            'AppSid'    => $this->config['app_sid'],
            'Recipient' => $this->normalizeGCC($to, '966'),
            'Content'   => $callbackUrl,
        ]);
    }

    public function sendWhatsApp(string $to, string $message, array $media = []): array
    {
        return ['status' => 'not_supported', 'message' => 'Use Infobip for WhatsApp in GCC'];
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        return true; // Unifonic uses IP allowlisting
    }

    public function parseInboundWebhook(array $payload): array
    {
        return [
            'type'     => 'sms_received',
            'from'     => $payload['Sender']    ?? '',
            'to'       => $payload['Recipient'] ?? '',
            'body'     => $payload['Body']      ?? '',
            'call_sid' => $payload['MessageID'] ?? uniqid(),
            'duration' => 0,
            'recording'=> '',
            'raw'      => $payload,
        ];
    }
}
