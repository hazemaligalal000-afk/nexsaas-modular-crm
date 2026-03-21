<?php
/**
 * ModularCore/Modules/Platform/Integrations/ZapierAdapter.php
 * Zapier/Make REST Webhook & OAuth Adapter - Requirement 10.3
 */

namespace ModularCore\Modules\Platform\Integrations;

class ZapierAdapter {
    private $tenantId;
    
    public function __construct(int $tenantId) {
        $this->tenantId = $tenantId;
    }

    /**
     * Registers a new Zapier/Make subscription webhook
     */
    public function subscribe(string $targetUrl, string $event) {
        $pdo = \Core\Database::getCentralConnection();
        $stmt = $pdo->prepare("INSERT INTO webhook_subscriptions (tenant_id, target_url, event_trigger, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$this->tenantId, $targetUrl, $event]);
        
        return $pdo->lastInsertId();
    }

    /**
     * Unregisters a subscription webhook
     */
    public function unsubscribe(int $subscriptionId) {
        $pdo = \Core\Database::getCentralConnection();
        $stmt = $pdo->prepare("DELETE FROM webhook_subscriptions WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$subscriptionId, $this->tenantId]);
    }

    /**
     * Dispatches payload to all listening Zapier Webhooks synchronously (or via Celery wrapper later)
     */
    public static function dispatchEvent(int $tenantId, string $event, array $payload) {
        $pdo = \Core\Database::getCentralConnection();
        $stmt = $pdo->prepare("SELECT target_url FROM webhook_subscriptions WHERE tenant_id = ? AND event_trigger = ?");
        $stmt->execute([$tenantId, $event]);
        
        $webhooks = $stmt->fetchAll();
        if (empty($webhooks)) return;

        $jsonPayload = json_encode([
            'event' => $event,
            'timestamp' => time(),
            'data' => $payload
        ]);

        foreach ($webhooks as $hook) {
            // Initiate parallel outgoing cURL requests
            $ch = curl_init($hook['target_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-NexSaaS-Signature: ' . hash_hmac('sha256', $jsonPayload, getenv('ZAPIER_SIGNING_SECRET'))]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            
            // Execute non-blocking in a highly scalable architecture; here blocked briefly for MVP.
            // Ideally, push to Redis Queue for async processing.
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
