<?php
/**
 * WhiteLabel/SubdomainService.php
 * 
 * CORE → ADVANCED: Dynamic Multi-Tenant Subdomain Provisioning
 */

declare(strict_types=1);

namespace Modules\WhiteLabel;

use Core\BaseService;

class SubdomainService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Map a tenant UUID to a friendly subdomain or custom host
     * e.g. 'cairo-trading.nexsaas.com' -> tenant_uuid
     */
    public function resolveTenant(string $host): ?string
    {
        $sql = "SELECT tenant_id FROM tenant_custom_domains 
                WHERE host = ? AND status = 'active'";
        
        return $this->db->GetOne($sql, [$host]);
    }

    /**
     * Register a new subdomain for a tenant
     * Used by: Onboarding & Subscription Upgrade
     */
    public function provisionSubdomain(string $tenantId, string $subdomain): array
    {
        $host = $subdomain . '.nexsaas.com';

        // 1. Logic: Update DNS records or Proxy config (API Call to Nginx/Cloudflare)
        // $this->cloudfare->addCname($subdomain, 'app.nexsaas.com');

        // 2. Persistent mapping for host resolution
        $this->db->Execute(
            "INSERT INTO tenant_custom_domains (tenant_id, host, status, created_at)
             VALUES (?, ?, 'active', NOW())
             ON CONFLICT (tenant_id) DO UPDATE SET host = EXCLUDED.host, status = 'active'",
            [$tenantId, $host]
        );

        return [
            'tenant_id' => $tenantId,
            'assigned_host' => $host,
            'status' => 'provisioned',
            'ssl_status' => 'auto-issuing' // Rule: Let's Encrypt automated cert issuance
        ];
    }
}
