<?php
/**
 * Modules/Omnichannel/WhatsAppController.php
 * Receives Inbound WhatsApp Webhooks from Meta.
 */

namespace Modules\Omnichannel;

use Core\Queue\QueueManager;

class WhatsAppController {
    
    /**
     * POST /api/webhooks/whatsapp
     */
    public function receive($data) {
        // 1. Validate Meta HMAC Signature (skipped for demo)
        
        // 2. Push to the background queue immediately for scale.
        // We don't want to block the Meta webhook server.
        QueueManager::push('omnichannel_messages', [
            'provider' => 'whatsapp',
            'raw_payload' => $data
        ]);

        // 3. Respond with 200 OK fast.
        return json_encode(['success' => true]);
    }
}
