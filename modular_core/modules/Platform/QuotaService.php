<?php
/**
 * Platform/QuotaService.php
 * 
 * CORE → ADVANCED: Dynamic Plan Limits & Quota Enforcement
 */

declare(strict_types=1);

namespace Modules\Platform;

use Core\BaseService;

class QuotaService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Check if a tenant has remaining quota for a specific resource
     * Resources: 'users', 'leads', 'storage_mb', 'waba_messages'
     */
    public function checkQuota(string $tenantId, string $resource): bool
    {
        // 1. Fetch Plan Limits for the tenant
        $sql = "SELECT p.limits FROM billing_subscriptions s
                JOIN billing_plans p ON s.plan_id = p.id
                WHERE s.tenant_id = ? AND s.status = 'active'";
        
        $limits = $this->db->GetOne($sql, [$tenantId]);

        if (!$limits) return false;

        $quota = json_decode($limits, true)[$resource] ?? 0;

        // 2. Automated Current Usage Count (Custom Logic per resource)
        $currentUsage = match($resource) {
             'users' => (int)$this->db->GetOne("SELECT COUNT(*) FROM users WHERE tenant_id = ?", [$tenantId]),
             'leads' => (int)$this->db->GetOne("SELECT COUNT(*) FROM leads WHERE tenant_id = ?", [$tenantId]),
             'waba_messages' => (int)$this->db->GetOne("SELECT COUNT(*) FROM waba_logs WHERE tenant_id = ? AND status = 'sent'", [$tenantId]),
             default => 0
        };

        return $currentUsage < $quota;
    }

    /**
     * Get quota summary (Current vs. Total) for dashboard
     */
    public function getQuotaOverview(string $tenantId): array
    {
        return [
            'users' => ['used' => 5, 'total' => 10], // Simplified mock for baseline
            'leads' => ['used' => 840, 'total' => 1000],
            'waba' => ['used' => 200, 'total' => 500]
        ];
    }
}
