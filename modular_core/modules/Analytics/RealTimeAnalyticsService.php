<?php
/**
 * Analytics/RealTimeAnalyticsService.php
 * 
 * CORE → ADVANCED: Live Dashboard Metrics
 */

declare(strict_types=1);

namespace Modules\Analytics;

use Core\BaseService;

class RealTimeAnalyticsService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get real-time stats for the main dashboard (SaaS wide)
     */
    public function getLiveStats(string $tenantId): array
    {
        // 1. Live Revenue (Monthly Recurring Revenue - MRR)
        $sql = "SELECT SUM(amount) FROM billing_subscriptions 
                WHERE tenant_id = ? AND status = 'active' AND is_recurring = TRUE";
        $mrr = $this->db->GetOne($sql, [$tenantId]);

        // 2. Lead Conversion (Last 30 Days)
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status='converted' THEN 1 ELSE 0 END) as converted
                FROM leads 
                WHERE tenant_id = ? AND created_at >= NOW() - INTERVAL '30 days'";
        $leads = $this->db->GetRow($sql, [$tenantId]);

        // 3. User Engagement (Active Users - Last 24 Hours)
        $sql = "SELECT COUNT(DISTINCT user_id) FROM user_session_logs 
                WHERE tenant_id = ? AND last_activity_at >= NOW() - INTERVAL '24 hours'";
        $activeUsers = $this->db->GetOne($sql, [$tenantId]);

        return [
            'mrr' => round($mrr ?? 0, 2),
            'conversion_rate' => $leads['total'] > 0 ? round(($leads['converted'] / $leads['total']) * 100, 2) : 0,
            'active_users_24h' => (int)($activeUsers ?? 0),
            'market_health' => $leads['conversion_rate'] > 15 ? 'Bullish' : 'Neutral'
        ];
    }
}
