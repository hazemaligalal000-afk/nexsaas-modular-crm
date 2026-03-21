<?php
/**
 * Analytics/UserPerformanceService.php
 * 
 * CORE → ADVANCED: Gamified Agent Performance & Productivity BI
 */

declare(strict_types=1);

namespace Modules\Analytics;

use Core\BaseService;

class UserPerformanceService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Calculate individual user performance score for the current month
     * Score = (Closed Deals * 10) + (Leads Created * 2) + (Tickets Resolved * 5)
     */
    public function getPerformanceLeaderboard(string $tenantId): array
    {
        // 1. Fetch user data with activity counts
        $sql = "SELECT u.id, u.name, 
                       (SELECT COUNT(*) FROM crm_deals d WHERE d.created_by = u.id AND d.stage = 'closed_won' AND d.tenant_id = ?) as won_deals,
                       (SELECT COUNT(*) FROM leads l WHERE l.created_by = u.id AND l.tenant_id = ?) as leads_created,
                       (SELECT COUNT(*) FROM support_tickets t WHERE t.assigned_to = u.id AND t.status = 'closed' AND t.tenant_id = ?) as closed_tickets
                FROM users u 
                WHERE u.tenant_id = ? AND u.is_active = TRUE";
        
        $users = $this->db->GetAll($sql, [$tenantId, $tenantId, $tenantId, $tenantId]);

        $leaderboard = [];

        foreach ($users as $u) {
             $score = ($u['won_deals'] * 10) + ($u['leads_created'] * 2) + ($u['closed_tickets'] * 5);
             
             $leaderboard[] = [
                'user_id' => $u['id'],
                'name' => $u['name'],
                'score' => $score,
                'metrics' => [
                   'won_deals' => $u['won_deals'],
                   'leads_created' => $u['leads_created'],
                   'closed_tickets' => $u['closed_tickets']
                ],
                'badge' => $score > 100 ? 'Expert' : ($score > 50 ? 'Intermediate' : 'Novice')
             ];
        }

        // 2. Sort by highest score
        usort($leaderboard, fn($a, $b) => $b['score'] <=> $a['score']);

        return $leaderboard;
    }
}
