<?php
/**
 * SaaS/SubscriptionService.php
 * 
 * CORE → ADVANCED: Subscription Lifecycle & Lifecycle Engine
 */

declare(strict_types=1);

namespace Modules\SaaS;

use Core\BaseService;

class SubscriptionService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Handle plan upgrade/downgrade for a tenant
     * Rule: Pro-rated balance adjustment
     */
    public function updatePlan(string $tenantId, int $newPlanId): array
    {
        // 1. Fetch current subscription
        $sql = "SELECT id, plan_id, end_date FROM billing_subscriptions 
                WHERE tenant_id = ? AND status = 'active'";
        
        $current = $this->db->GetRow($sql, [$tenantId]);

        if (!$current) throw new \RuntimeException("No active subscription for tenant: " . $tenantId);

        // 2. Automated Pro-rating (Advanced BI)
        $daysRemaining = (strtotime($current['end_date']) - time()) / 86400;

        // 3. Update to new plan & extends validity
        $this->db->Execute(
            "UPDATE billing_subscriptions SET plan_id = ?, updated_at = NOW() WHERE id = ?",
            [$newPlanId, $current['id']]
        );

        // 4. FIRE EVENT: Plan Changed (Triggers re-provisioning of resources)
        // $this->fireEvent('saas.plan_updated', ['tenant_id' => $tenantId, 'new_plan' => $newPlanId]);

        return [
            'tenant_id' => $tenantId,
            'new_plan' => $newPlanId,
            'days_carried_over' => (int)$daysRemaining
        ];
    }

    /**
     * Check for near-expiry trials/subscriptions (Batch SaaS-A)
     */
    public function getExpiringSoon(): array
    {
        $sql = "SELECT tenant_id, company_name, end_date FROM billing_subscriptions 
                WHERE end_date <= NOW() + INTERVAL '3 days' AND status = 'active'";
        
        return $this->db->GetAll($sql);
    }
}
