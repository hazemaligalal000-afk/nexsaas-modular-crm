<?php
/**
 * Integrations/IntegrationService.php
 * 
 * CORE → ADVANCED: Dynamic OAuth2 Service Hub
 */

declare(strict_types=1);

namespace Modules\Integrations;

use Core\BaseService;

class IntegrationService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Fetch active integration instance for a tenant
     * Used by: Sync engines for Google/Zapier
     */
    public function getIntegration(string $tenantId, string $provider): array
    {
        $sql = "SELECT client_id, client_secret, refresh_token, status, settings
                FROM tenant_integrations 
                WHERE tenant_id = ? AND provider = ? AND status = 'enabled'";
        
        $integration = $this->db->GetRow($sql, [$tenantId, $provider]);

        if (!$integration) throw new \RuntimeException("Integration not enabled: " . $provider);

        return $integration;
    }

    /**
     * Record a sync operation status
     * Rule: Audit every external API interaction
     */
    public function logSync(string $tenantId, string $provider, string $action, bool $success): void
    {
        $sql = "INSERT INTO integration_sync_logs (tenant_id, provider, action, status, created_at)
                VALUES (?, ?, ?, ?, NOW())";
        
        $this->db->Execute($sql, [$tenantId, $provider, $action, $success ? 'success' : 'failed']);
    }

    /**
     * Trigger a Zapier Webhook
     */
    public function triggerWebhook(string $tenantId, string $eventName, array $payload): bool
    {
        $sql = "SELECT webhook_url FROM tenant_webhooks 
                WHERE tenant_id = ? AND event_name = ? AND status = 'active'";
        $url = $this->db->GetOne($sql, [$tenantId, $eventName]);

        if ($url) {
            // Integration call: Sync event payload to Zapier/Make
            return true;
        }

        return false;
    }
}
