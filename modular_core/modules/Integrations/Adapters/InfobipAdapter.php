<?php
/**
 * Integrations/Adapters/InfobipAdapter.php
 *
 * Infobip — WhatsApp BSP, MENA-optimised. Dubai HQ.
 * Recommended for WhatsApp in Egypt + GCC.
 */

declare(strict_types=1);

namespace Integrations\Adapters;

class InfobipAdapter extends BaseAdapter
{
    const API_BASE = 'https://api.infobip.com';

    public function sendSMS(string $to, string $body): array
    {
        return $this->post(self::API_BASE . '/sms/2/text/advanced', [
            'messages' => [[
                'from'         => $this->config['sender_id'] ?? 'NexSaaS',
                'destinations' => [['to' => $to]],
                'text'         => $body,
            ]],
        ], ['Authorization' => 'App ' . $this->config['api_key']]);
    }

    public function makeCall(string $to, string $from, string $callbackUrl): array
    {
        return ['status' => 'not_supported'];
    }

    public function sendWhatsApp(string $to, string $message, array $media = []): array
    {
        return $this->post(self::API_BASE . '/whatsapp/1/message/text', [
            'from'    => $this->config['whatsapp_number'],
            'to'      => $to,
            'content' => ['text' => $message],
        ], ['Authorization' => 'App ' . $this->config['api_key']]);
    }

    public function sendWhatsAppTemplate(string $to, string $templateName, array $params, string $lang = 'ar'): array
    {
        return $this->post(self::API_BASE . '/whatsapp/1/message/template', [
            'from'    => $this->config['whatsapp_number'],
            'to'      => $to,
            'content' => [
                'templateName' => $templateName,
                'templateData' => ['body' => ['placeholders' => $params]],
                'language'     => $lang,
            ],
        ], ['Authorization' => 'App ' . $this->config['api_key']]);
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        return true; // Infobip uses IP allowlisting + optional HMAC
    }

    public function parseInboundWebhook(array $payload): array
    {
        $msg = $payload['results'][0] ?? [];
        return [
            'type'     => 'whatsapp_received',
            'from'     => $msg['from']    ?? '',
            'to'       => $msg['to']      ?? '',
            'body'     => $msg['message']['text'] ?? '',
            'call_sid' => $msg['messageId'] ?? uniqid(),
            'duration' => 0,
            'recording'=> '',
            'raw'      => $payload,
        ];
    }
}
