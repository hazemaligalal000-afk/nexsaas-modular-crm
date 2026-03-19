<?php
/**
 * CRM/Inbox/Channels/WhatsAppChannel.php
 *
 * WhatsApp channel handler using the Meta (Facebook) Cloud API.
 * Inbound messages arrive via Meta webhook (POST to /api/inbox/webhook/whatsapp).
 * Outbound messages are sent via the Meta Messages API.
 *
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace CRM\Inbox\Channels;

class WhatsAppChannel
{
    private array $config;

    /**
     * @param array $config  Keys:
     *   - phone_number_id  (string)  Meta WhatsApp Business phone number ID
     *   - access_token     (string)  Meta permanent or system user access token
     *   - verify_token     (string)  Webhook verification token (set in Meta dashboard)
     *   - api_version      (string)  Graph API version, e.g. 'v18.0'
     *   - api_base_url     (string)  Override for testing
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'api_version'  => 'v18.0',
            'api_base_url' => 'https://graph.facebook.com',
        ], $config);
    }

    // -------------------------------------------------------------------------
    // Inbound — webhook payload parsing
    // -------------------------------------------------------------------------

    /**
     * Parse a Meta WhatsApp inbound webhook payload.
     *
     * Meta sends a nested JSON structure; this method extracts the first message
     * from the first entry/change.
     *
     * @param  array $webhookPayload  Decoded JSON body from Meta webhook POST
     * @return array|null  Normalised message or null if no message found
     *
     * @throws \InvalidArgumentException on malformed payload
     */
    public function parseInboundWebhook(array $webhookPayload): ?array
    {
        $entry   = $webhookPayload['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value   = $changes['value'] ?? null;

        if ($value === null) {
            return null;
        }

        $messages = $value['messages'] ?? [];
        if (empty($messages)) {
            return null; // Could be a status update, not a message
        }

        $msg  = $messages[0];
        $from = $msg['from'] ?? null; // E.164 phone number without '+'

        if (empty($from)) {
            throw new \InvalidArgumentException('WhatsAppChannel: missing from field in webhook message.');
        }

        // Normalise phone to E.164 with leading +
        $senderPhone = '+' . ltrim($from, '+');

        $body = match ($msg['type'] ?? 'text') {
            'text'  => $msg['text']['body'] ?? '',
            'image' => '[Image attachment]',
            'audio' => '[Audio attachment]',
            'video' => '[Video attachment]',
            'document' => '[Document: ' . ($msg['document']['filename'] ?? 'file') . ']',
            default => '[Unsupported message type: ' . ($msg['type'] ?? 'unknown') . ']',
        };

        $contacts = $value['contacts'][0] ?? [];
        $name     = $contacts['profile']['name'] ?? null;

        return [
            'sender_phone' => $senderPhone,
            'sender_email' => null,
            'body'         => $body,
            'external_id'  => $msg['id'] ?? null,
            'metadata'     => [
                'wa_id'        => $from,
                'display_name' => $name,
                'type'         => $msg['type'] ?? 'text',
                'timestamp'    => $msg['timestamp'] ?? null,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Outbound — Meta Messages API
    // -------------------------------------------------------------------------

    /**
     * Send an outbound WhatsApp text message via Meta Cloud API.
     *
     * @param  array  $conversation  Conversation row
     * @param  string $body          Message text
     *
     * @throws \RuntimeException on API error or missing configuration
     */
    public function send(array $conversation, string $body): void
    {
        $to = $conversation['metadata']['wa_id']
            ?? ltrim($conversation['metadata']['sender_phone'] ?? '', '+')
            ?? null;

        if (empty($to)) {
            throw new \RuntimeException('WhatsAppChannel::send: no recipient WhatsApp ID available.');
        }

        $phoneNumberId = $this->config['phone_number_id'] ?? '';
        $accessToken   = $this->config['access_token'] ?? '';

        if ($phoneNumberId === '' || $accessToken === '') {
            throw new \RuntimeException('WhatsAppChannel: Meta API credentials (phone_number_id, access_token) are not configured.');
        }

        $version = $this->config['api_version'];
        $baseUrl = $this->config['api_base_url'];
        $url     = "{$baseUrl}/{$version}/{$phoneNumberId}/messages";

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $body],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$accessToken}",
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new \RuntimeException("WhatsAppChannel::send: cURL error: {$curlError}");
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $decoded = json_decode((string) $response, true);
            $message = $decoded['error']['message'] ?? $response;
            throw new \RuntimeException("WhatsAppChannel::send: Meta API error (HTTP {$httpStatus}): {$message}");
        }
    }

    // -------------------------------------------------------------------------
    // Webhook verification (GET challenge)
    // -------------------------------------------------------------------------

    /**
     * Verify a Meta webhook subscription challenge.
     *
     * @param  string $mode      hub.mode query param
     * @param  string $token     hub.verify_token query param
     * @param  string $challenge hub.challenge query param
     * @return string|null       The challenge string to echo back, or null on failure
     */
    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        if ($mode === 'subscribe' && hash_equals($this->config['verify_token'] ?? '', $token)) {
            return $challenge;
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Payload signature validation
    // -------------------------------------------------------------------------

    /**
     * Validate the X-Hub-Signature-256 header from Meta.
     *
     * @param  string $rawBody    Raw request body string
     * @param  string $signature  X-Hub-Signature-256 header value (sha256=...)
     * @return bool
     */
    public function validateSignature(string $rawBody, string $signature): bool
    {
        $appSecret = $this->config['app_secret'] ?? '';
        if ($appSecret === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        return hash_equals($expected, $signature);
    }
}
