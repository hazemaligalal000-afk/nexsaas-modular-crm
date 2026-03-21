<?php
/**
 * ModularCore/Modules/Omnichannel/Core/LiveChatService.php
 * Handles direct-on-site client chat. Requirement Week 3.3
 */

namespace ModularCore\Modules\Omnichannel\Core;

use Core\Database;
use ModularCore\Modules\Platform\Integrations\PusherService;

class LiveChatService {

    /**
     * Start a new chat session from the widget
     */
    public function initiateChat($tenantId, $sessionId, $clientEmail = null, $clientName = null) {
        $pdo = Database::getTenantConnection();
        $stmt = $pdo->prepare("SELECT lead_id FROM leads WHERE livechat_session_id = ?");
        $stmt->execute([$sessionId]);
        $lead = $stmt->fetch();

        if (!$lead) {
            // Requirement 3.85 - Auto-capture
            $stmt = $pdo->prepare("INSERT INTO leads (tenant_id, first_name, email, source, livechat_session_id) VALUES (?, ?, ?, 'livechat', ?)");
            $stmt->execute([$tenantId, $clientName ?? 'Visitor', $clientEmail, $sessionId]);
            $leadId = $pdo->lastInsertId();
        } else {
            $leadId = $lead['lead_id'];
        }

        return $leadId;
    }

    /**
     * Handle inbound text from the widget
     */
    public function receiveMessage($tenantId, $sessionId, $text) {
        $leadId = $this->initiateChat($tenantId, $sessionId);

        $pdo = Database::getTenantConnection();
        $stmt = $pdo->prepare("INSERT INTO conversations (tenant_id, lead_id, channel, direction, message, created_at) VALUES (?, ?, 'livechat', 'inbound', ?, NOW())");
        $stmt->execute([$tenantId, $leadId, $text]);

        // Push to Unified Inbox
        PusherService::trigger("private-tenant-{$tenantId}", 'new-message', [
            'lead_id' => $leadId,
            'text' => $text,
            'channel' => 'livechat',
            'timestamp' => time()
        ]);

        return true;
    }

    /**
     * Handle Agent typing notification (Collision Detection)
     */
    public function notifyClientTyping($tenantId, $sessionId, $agentName) {
        // Send Pusher event back to the client-facing channel
        PusherService::trigger("client-chat-{$sessionId}", 'agent-typing', [
            'agent' => $agentName
        ]);
    }
}
