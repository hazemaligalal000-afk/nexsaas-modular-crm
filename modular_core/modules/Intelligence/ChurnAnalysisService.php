<?php
/**
 * Intelligence/ChurnAnalysisService.php
 * 
 * CORE → ADVANCED: Predictive Customer Churn Analysis (P2)
 */

declare(strict_types=1);

namespace Modules\Intelligence;

use Core\BaseService;

class ChurnAnalysisService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Analyze tenant churn risk based on activity logs and subscription status
     * Rule: Low activity + expired trial = High Risk
     */
    public function getChurnRiskOverview(): array
    {
        // 1. Fetch tenants with last activity vs. current date
        $sql = "SELECT tenant_id, company_name, 
                       (SELECT last_activity_at FROM user_session_logs WHERE tenant_id = ? ORDER BY last_activity_at DESC LIMIT 1) as last_seen,
                       (SELECT status FROM billing_subscriptions WHERE tenant_id = ? LIMIT 1) as sub_status
                FROM tenants 
                WHERE is_active = TRUE";
        
        $tenants = $this->db->GetAll($sql);

        $riskScores = [];

        foreach ($tenants as $t) {
             $lastSeen = $t['last_seen'] ? strtotime($t['last_seen']) : 0;
             $daysInactive = (time() - $lastSeen) / 86400; // In days

             $risk = 0;
             $reasons = [];

             // Risk Point: Inactivity
             if ($daysInactive > 14) {
                 $risk += 40;
                 $reasons[] = "Inactive for > 14 days ({$daysInactive} days)";
             } elseif ($daysInactive > 7) {
                 $risk += 20;
                 $reasons[] = "Inactive for > 7 days ({$daysInactive} days)";
             }

             // Risk Point: Trial Status
             if ($t['sub_status'] === 'trialing' && $daysInactive > 3) {
                 $risk += 30;
                 $reasons[] = "Trialing with no recent activity";
             }

             // Final categorization
             $tier = $risk >= 70 ? 'Critical' : ($risk >= 40 ? 'At Risk' : 'Healthy');

             $riskScores[] = [
                'tenant_id' => $t['tenant_id'],
                'company_name' => $t['company_name'],
                'risk_score' => $risk,
                'tier' => $tier,
                'reasons' => $reasons,
                'days_inactive' => (int)$daysInactive
             ];
        }

        return [
            'total_monitored' => count($tenants),
            'critical_risk_count' => count(array_filter($riskScores, fn($r) => $r['tier'] === 'Critical')),
            'at_risk_count' => count(array_filter($riskScores, fn($r) => $r['tier'] === 'At Risk')),
            'health_score' => count($tenants) > 0 ? (count(array_filter($riskScores, fn($r) => $r['tier'] === 'Healthy')) / count($tenants)) * 100 : 0,
            'details' => $riskScores
        ];
    }
}
