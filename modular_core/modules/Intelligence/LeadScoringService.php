<?php
/**
 * Intelligence/LeadScoringService.php
 * 
 * CORE → ADVANCED: AI Lead Scoring
 */

declare(strict_types=1);

namespace Modules\Intelligence;

use Core\BaseService;

class LeadScoringService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Calculate score for a lead based on behavioral and demographic data
     * Returns: score (0-100), tier (low, medium, high), reasons[]
     */
    public function calculateScore(int $leadId): array
    {
        // 1. Fetch Lead Data
        $sql = "SELECT source, industry, budget, country, employee_count, 
                       (SELECT COUNT(*) FROM activity_log WHERE entity_type='lead' AND entity_id=?) as interactions
                FROM leads 
                WHERE id = ?";
        
        $lead = $this->db->GetRow($sql, [$leadId, $leadId]);

        if (!$lead) throw new \RuntimeException("Lead not found: " . $leadId);

        $score = 0;
        $reasons = [];

        // Score: Industry (SaaS/Tech is high)
        if (in_array($lead['industry'], ['SaaS', 'Tech', 'Fintech'])) {
            $score += 20;
            $reasons[] = "High-priority industry: " . $lead['industry'];
        }

        // Score: Budget
        if ($lead['budget'] > 50000) {
            $score += 30;
            $reasons[] = "Enterprise-level budget: >$50k";
        } elseif ($lead['budget'] > 10000) {
            $score += 15;
            $reasons[] = "Mid-tier budget: >$10k";
        }

        // Score: Interactions
        if ($lead['interactions'] > 10) {
            $score += 25;
            $reasons[] = "High engagement: " . $lead['interactions'] . " interactions";
        }

        // Tier classification
        $tier = $score >= 70 ? 'High' : ($score >= 40 ? 'Medium' : 'Low');

        // Update lead with AI score
        $this->db->Execute(
            "UPDATE leads SET ai_score = ?, ai_tier = ?, ai_updated_at = NOW() WHERE id = ?",
            [$score, $tier, $leadId]
        );

        return [
            'score' => $score,
            'tier' => $tier,
            'reasons' => $reasons
        ];
    }
}
