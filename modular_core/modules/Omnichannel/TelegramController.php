<?php
/**
 * Modules/Omnichannel/TelegramController.php
 * Receives inbound Telegram Bot messages via Webhook.
 */

namespace Modules\Omnichannel;

use Core\Queue\QueueManager;

class TelegramController {

    /**
     * POST /api/webhooks/telegram
     * Telegram Bot API sends updates here.
     */
    public function receive($data) {
        // 1. Extract the message from Telegram's Update object
        $message = $data['message'] ?? $data['edited_message'] ?? null;

        if (!$message) {
            return json_encode(['success' => true, 'note' => 'No message in update']);
        }

        $chatId   = $message['chat']['id'] ?? null;
        $text     = $message['text'] ?? '';
        $from     = $message['from'] ?? [];
        $username = $from['username'] ?? 'unknown';

        // 2. Push to the background queue for processing
        QueueManager::push('omnichannel_messages', [
            'provider'   => 'telegram',
            'chat_id'    => $chatId,
            'username'   => $username,
            'first_name' => $from['first_name'] ?? '',
            'text'       => $text,
            'raw'        => $data
        ]);

        // 3. Respond immediately to Telegram servers
        return json_encode(['success' => true]);
    }
}
