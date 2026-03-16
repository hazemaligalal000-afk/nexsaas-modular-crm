<?php
/**
 * Modules/Omnichannel/Providers/TelegramProvider.php
 * Handles Telegram Bot API integration.
 */

namespace Modules\Omnichannel\Providers;

class TelegramProvider {
    private $botToken;

    public function __construct($botToken) {
        $this->botToken = $botToken;
    }

    /**
     * Send a message to a specific chat ID.
     */
    public function sendMessage($chatId, $text) {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        return $this->request($url, $data);
    }

    /**
     * Handle incoming webhook updates from Telegram.
     */
    public function handleWebhook($update) {
        $message = $update['message'] ?? null;
        if (!$message) return;

        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $from = $message['from'];

        // 1. Sync conversation to activity timeline
        // 2. Auto-create Lead if phone is available
        // 3. Trigger Marketing Automation workflows
        
        return [
            'external_id' => $chatId,
            'sender' => $from['username'] ?? $from['first_name'],
            'message' => $text
        ];
    }

    private function request($url, $data) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
}
