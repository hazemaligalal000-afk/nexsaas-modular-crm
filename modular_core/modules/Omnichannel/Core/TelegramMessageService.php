<?php
/**
 * ModularCore/Modules/Omnichannel/Core/TelegramMessageService.php
 * Telegram Bot API Integration - Requirement (Week 3)
 */

namespace ModularCore\Modules\Omnichannel\Core;

use Core\Database;

class TelegramMessageService {
    private $botToken;

    public function __construct() {
        $this->botToken = getenv('TELEGRAM_BOT_TOKEN');
    }

    public function handleWebhook($payload) {
        $data = json_decode($payload, true);
        if (!$data || !isset($data['message'])) return false;

        $msg = $data['message'];
        $chatId = $msg['chat']['id'];
        $text = $msg['text'] ?? '';
        $senderName = $msg['from']['first_name'] ?? 'Unknown';

        // Map Telegram Chat ID to a Tenant's Contact Lead (or create one)
        // Note: For multi-tenant isolation, we rely on Webhook Path segmentation (e.g., /api/telegram/webhook/{tenant_id})
        // Assuming Tenant ID is resolved via Router matching
        $tenantId = \Core\TenantEnforcer::getTenantId();
        if (!$tenantId) return false;

        $pdo = Database::getTenantConnection();
        $stmt = $pdo->prepare("SELECT lead_id FROM leads WHERE telegram_chat_id = ?");
        $stmt->execute([$chatId]);
        $lead = $stmt->fetch();

        if (!$lead) {
            // Auto Capture Lead
            $stmt = $pdo->prepare("INSERT INTO leads (tenant_id, first_name, source, telegram_chat_id) VALUES (?, ?, 'telegram', ?)");
            $stmt->execute([$tenantId, $senderName, $chatId]);
            $leadId = $pdo->lastInsertId();
        } else {
            $leadId = $lead['lead_id'];
        }

        // Store Message 
        $stmt = $pdo->prepare("INSERT INTO conversations (tenant_id, lead_id, channel, direction, message) VALUES (?, ?, 'telegram', 'inbound', ?)");
        $stmt->execute([$tenantId, $leadId, $text]);

        // Push Real-time update to Unified Inbox via Pusher
        if (class_exists('\\ModularCore\\Modules\\Platform\\Integrations\\PusherService')) {
            \ModularCore\Modules\Platform\Integrations\PusherService::trigger(
                "private-tenant-{$tenantId}", 
                'new-message', 
                ['lead_id' => $leadId, 'text' => $text, 'channel' => 'telegram']
            );
        }

        return true;
    }

    public function sendMessage($chatId, $text) {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'chat_id' => $chatId,
            'text' => $text
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
}
