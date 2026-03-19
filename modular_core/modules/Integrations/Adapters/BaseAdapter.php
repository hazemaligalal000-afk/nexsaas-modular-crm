<?php
/**
 * Integrations/Adapters/BaseAdapter.php
 *
 * Abstract base for every telecom/messaging integration adapter.
 * All adapters must extend this class and implement the abstract methods.
 */

declare(strict_types=1);

namespace Integrations\Adapters;

abstract class BaseAdapter
{
    protected array  $config;
    protected string $tenantId;

    public function __construct(string $tenantId, array $encryptedConfig)
    {
        $this->tenantId = $tenantId;
        $this->config   = $this->decrypt($encryptedConfig);
    }

    // ── Abstract interface every adapter must implement ──────────────────────

    abstract public function sendSMS(string $to, string $body): array;
    abstract public function makeCall(string $to, string $from, string $callbackUrl): array;
    abstract public function sendWhatsApp(string $to, string $message, array $media = []): array;
    abstract public function verifyWebhook(array $headers, string $rawBody): bool;

    /**
     * Parse an inbound webhook payload into a normalised format:
     * [
     *   'type'     => 'call_started'|'call_ended'|'sms_received'|'whatsapp_received',
     *   'from'     => '+201XXXXXXXXX',
     *   'to'       => '+201XXXXXXXXX',
     *   'body'     => 'message text',
     *   'call_sid' => 'platform-call-id',
     *   'duration' => 120,
     *   'recording'=> 'https://...',
     *   'raw'      => $payload,
     * ]
     */
    abstract public function parseInboundWebhook(array $payload): array;

    // ── Shared HTTP helpers ──────────────────────────────────────────────────

    protected function post(string $url, array $payload, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => array_merge(
                ['Content-Type: application/json', 'Accept: application/json'],
                $this->buildHeaderLines($headers)
            ),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string)$body, true) ?? [];
        $decoded['_http_status'] = $code;
        return $decoded;
    }

    protected function get(string $url, array $params = [], array $headers = []): array
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array_merge(
                ['Accept: application/json'],
                $this->buildHeaderLines($headers)
            ),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return json_decode((string)$body, true) ?? [];
    }

    protected function basicAuth(): array
    {
        return ['Authorization' => 'Basic ' . base64_encode(
            ($this->config['account_sid'] ?? $this->config['auth_id'] ?? '') .
            ':' .
            ($this->config['auth_token'] ?? $this->config['auth_token'] ?? '')
        )];
    }

    protected function bearerAuth(string $key = 'api_key'): array
    {
        return ['Authorization' => 'Bearer ' . ($this->config[$key] ?? '')];
    }

    // ── Encryption helpers ───────────────────────────────────────────────────

    protected function decrypt(array $config): array
    {
        // In production: AES-256-CBC decrypt each credential field.
        // For now, pass-through (encryption handled at storage layer).
        return $config;
    }

    protected function encrypt(string $value): string
    {
        $key = $_ENV['ENCRYPTION_KEY'] ?? str_repeat('0', 32);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $enc);
    }

    // ── Phone normalisation helpers ──────────────────────────────────────────

    protected function normalizeEgyptianNumber(string $num): string
    {
        $num = preg_replace('/\D/', '', $num);
        if (str_starts_with($num, '20') && strlen($num) === 12) {
            $num = substr($num, 2);
        }
        return '0' . ltrim($num, '0');
    }

    protected function normalizeGCC(string $num, string $countryCode): string
    {
        $digits = preg_replace('/\D/', '', $num);
        if (!str_starts_with($digits, $countryCode)) {
            $digits = $countryCode . ltrim($digits, '0');
        }
        return '+' . $digits;
    }

    protected function detectLanguage(string $text): string
    {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $text) ? 'A' : 'E';
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function buildHeaderLines(array $headers): array
    {
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = "{$k}: {$v}";
        }
        return $lines;
    }
}
