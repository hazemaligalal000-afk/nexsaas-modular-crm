<?php
/**
 * Integrations/Adapters/WhatsAppMetaAdapter.php
 *
 * WhatsApp Business API via Meta Cloud API.
 * Critical for Egypt and GCC — WhatsApp is the #1 messaging app.
 */

declare(strict_types=1);

namespace Integrations\Adapters;

class WhatsAppMetaAdapter extends BaseAdapter
{
    const API_BASE = 'https://graph.facebook.com/v18.0/';

    public function sendSMS(string $to, string $body): array
    {
        return $this->sendWhatsApp($to, $body);
    }

    public function makeCall(string $to, string $from, string $callbackUrl): array
    {
        return ['status' => 'not_supported'];
    }

    public function sendWhatsApp(string $to, string $message, array $media = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $message, 'preview_url' => false],
        ];
        return $this->post(
            self::API_BASE . $this->config['phone_number_id'] . '/messages',
            $payload,
            ['Authorization' => 'Bearer ' . $this->config['access_token']]
        );
    }

    public function sendTemplate(string $to, string $templateName, string $langCode, array $components): array
    {
        return $this->post(
            self::API_BASE . $this->config['phone_number_id'] . '/messages',
            [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'template',
                'template'          => [
                    'name'       => $templateName,
                    'language'   => ['code' => $langCode],
                    'components' => $components,
                ],
            ],
            ['Authorization' => 'Bearer ' . $this->config['access_token']]
        );
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        // GET verification
        $mode      = $_GET['hub_mode']         ?? '';
        $token     = $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub_challenge']    ?? '';
        if ($mode === 'subscribe' && $token === $this->config['verify_token']) {
            echo $challenge;
            return true;
        }
        // POST signature verification
        $signature = $headers['X-Hub-Signature-256'] ?? '';
        $computed  = 'sha256=' . hash_hmac('sha256', $rawBody, $this->config['app_secret'] ?? '');
        return hash_equals($computed, $signature);
    }

    public function parseInboundWebhook(array $payload): array
    {
        $entry = $payload['entry'][0]['changes'][0]['value'] ?? [];
        $msg   = $entry['messages'][0] ?? null;
        if (!$msg) {
            return [];
        }
        return [
            'type'      => 'whatsapp_received',
            'from'      => $msg['from'],
            'to'        => $entry['metadata']['phone_number_id'] ?? '',
            'body'      => $msg['text']['body'] ?? '',
            'media'     => $msg['image']['id'] ?? $msg['document']['id'] ?? null,
            'call_sid'  => $msg['id'],
            'duration'  => 0,
            'recording' => '',
            'raw'       => $payload,
        ];
    }
}
