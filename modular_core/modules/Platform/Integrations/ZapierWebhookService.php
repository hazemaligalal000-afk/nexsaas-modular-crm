<?php
/**
 * Platform/Integrations/ZapierWebhookService.php
 * 
 * Secure Webhook delivery system for Zapier & Make.com (Requirement 10.3)
 */

namespace NexSaaS\Platform\Integrations;

class ZapierWebhookService
{
    private $adb;

    public function __construct($adb) {
        $this->adb = $adb;
    }

    /**
     * Dispatch an event to tenant's Zapier hooks
     */
    public function dispatch(int $tenantId, string $event, array $payload): void
    {
        // Fetch hooks for this event
        $query = "SELECT webhook_url FROM saas_webhooks WHERE tenant_id = ? AND event_name = ? AND active = 1";
        $result = $this->adb->pquery($query, [$tenantId, $event]);

        while ($row = $this->adb->fetch_array($result)) {
            $this->sendPayload($row['webhook_url'], $payload);
        }
    }

    /**
     * Send payload to Zapier
     */
    private function sendPayload(string $url, array $payload): void
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'event_id' => bin2Hex(random_bytes(8)),
            'timestamp' => time(),
            'data' => $payload
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        // Error handling stub
        curl_exec($ch);
        curl_close($ch);
    }
}
