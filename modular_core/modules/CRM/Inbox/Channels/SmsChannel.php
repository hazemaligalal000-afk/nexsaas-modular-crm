<?php
/**
 * CRM/Inbox/Channels/SmsChannel.php
 *
 * SMS channel handler using Twilio REST API.
 * Inbound messages arrive via Twilio webhook (POST to /api/inbox/webhook/sms).
 * Outbound messages are sent via the Twilio Messages API.
 *
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace CRM\Inbox\Channels;

class SmsChannel
{
    private array $config;

    /**
     * @param array $config  Keys:
     *   - account_sid  (string)  Twilio Account SID
     *   - auth_token   (string)  Twilio Auth Token
     *   - from_number  (string)  Twilio phone number (E.164 format, e.g. +15551234567)
     *   - api_base_url (string)  Override for testing (default: https://api.twilio.com)
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'api_base_url' => 'https://api.twilio.com',
        ], $config);
    }

    // -------------------------------------------------------------------------
    // Inbound — webhook payload parsing
    // -------------------------------------------------------------------------

    /**
     * Parse a Twilio inbound SMS webhook payload into a normalised message array.
     *
     * @param  array $webhookPayload  $_POST data from Twilio webhook
     * @return array  Normalised: ['sender_phone', 'sender_email', 'body', 'external_id', 'metadata']
     *
     * @throws \InvalidArgumentException if required fields are missing
     */
    public function parseInboundWebhook(array $webhookPayload): array
    {
        $from = trim($webhookPayload['From'] ?? '');
        $body = trim($webhookPayload['Body'] ?? '');

        if ($from === '') {
            throw new \InvalidArgumentException('SmsChannel: missing From field in webhook payload.');
        }
        if ($body === '') {
            throw new \InvalidArgumentException('SmsChannel: missing Body field in webhook payload.');
        }

        return [
            'sender_phone' => $from,
            'sender_email' => null,
            'body'         => $body,
            'external_id'  => $webhookPayload['MessageSid'] ?? null,
            'metadata'     => [
                'to'          => $webhookPayload['To'] ?? null,
                'num_media'   => (int) ($webhookPayload['NumMedia'] ?? 0),
                'account_sid' => $webhookPayload['AccountSid'] ?? null,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Outbound — Twilio Messages API
    // -------------------------------------------------------------------------

    /**
     * Send an outbound SMS via Twilio.
     *
     * @param  array  $conversation  Conversation row; must have metadata.sender_phone or a resolvable phone
     * @param  string $body          SMS text (max 1600 chars; Twilio handles segmentation)
     *
     * @throws \RuntimeException on API error or missing configuration
     */
    public function send(array $conversation, string $body): void
    {
        $to = $conversation['metadata']['sender_phone']
            ?? $conversation['sender_phone']
            ?? null;

        if (empty($to)) {
            throw new \RuntimeException('SmsChannel::send: no recipient phone number available.');
        }

        $accountSid = $this->config['account_sid'] ?? '';
        $authToken  = $this->config['auth_token'] ?? '';
        $from       = $this->config['from_number'] ?? '';

        if ($accountSid === '' || $authToken === '' || $from === '') {
            throw new \RuntimeException('SmsChannel: Twilio credentials (account_sid, auth_token, from_number) are not configured.');
        }

        $url     = "{$this->config['api_base_url']}/2010-04-01/Accounts/{$accountSid}/Messages.json";
        $payload = http_build_query(['To' => $to, 'From' => $from, 'Body' => $body]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_USERPWD        => "{$accountSid}:{$authToken}",
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new \RuntimeException("SmsChannel::send: cURL error: {$curlError}");
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $decoded = json_decode((string) $response, true);
            $message = $decoded['message'] ?? $response;
            throw new \RuntimeException("SmsChannel::send: Twilio API error (HTTP {$httpStatus}): {$message}");
        }
    }

    // -------------------------------------------------------------------------
    // Webhook signature validation
    // -------------------------------------------------------------------------

    /**
     * Validate a Twilio webhook request signature.
     *
     * @param  string $url        Full URL of the webhook endpoint
     * @param  array  $params     POST parameters from the request
     * @param  string $signature  X-Twilio-Signature header value
     * @return bool
     */
    public function validateSignature(string $url, array $params, string $signature): bool
    {
        $authToken = $this->config['auth_token'] ?? '';
        if ($authToken === '') {
            return false;
        }

        // Sort params alphabetically and append key+value pairs to URL
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));
        return hash_equals($expected, $signature);
    }
}
