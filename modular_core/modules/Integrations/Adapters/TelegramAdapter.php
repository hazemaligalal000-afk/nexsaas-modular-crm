<?php
/**
 * Integrations/Adapters/TelegramAdapter.php
 *
 * Telegram Bot API — send/receive messages, inline keyboards.
 */

declare(strict_types=1);

namespace Integrations\Adapters;

class TelegramAdapter extends BaseAdapter
{
    const API_BASE = 'https://api.telegram.org/bot';

    public function sendSMS(string $to, string $body): array
    {
        return $this->sendMessage($to, $body);
    }

    public function makeCall(string $to, string $from, string $callbackUrl): array
    {
        return ['status' => 'not_supported'];
    }

    public function sendWhatsApp(string $to, string $message, array $media = []): array
    {
        return ['status' => 'not_supported'];
    }

    public function sendMessage(string $chatId, string $text, array $buttons = []): array
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
        if (!empty($buttons)) {
            $payload['reply_markup'] = ['inline_keyboard' => $buttons];
        }
        return $this->post(self::API_BASE . $this->config['bot_token'] . '/sendMessage', $payload);
    }

    public function setWebhook(): array
    {
        return $this->post(
            self::API_BASE . $this->config['bot_token'] . '/setWebhook',
            [
                'url'          => $this->config['webhook_url'],
                'secret_token' => $this->config['webhook_secret'] ?? '',
            ]
        );
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $secret = $this->config['webhook_secret'] ?? '';
        if ($secret === '') {
            return true;
        }
        $token = $headers['X-Telegram-Bot-Api-Secret-Token'] ?? '';
        return hash_equals($secret, $token);
    }

    public function parseInboundWebhook(array $payload): array
    {
        $msg = $payload['message'] ?? $payload['callback_query']['message'] ?? null;
        if (!$msg) {
            return [];
        }
        return [
            'type'     => 'telegram_received',
            'from'     => (string)($msg['from']['id'] ?? ''),
            'to'       => '',
            'body'     => $msg['text'] ?? $payload['callback_query']['data'] ?? '',
            'call_sid' => (string)($msg['message_id'] ?? uniqid()),
            'duration' => 0,
            'recording'=> '',
            'raw'      => $payload,
        ];
    }
}
