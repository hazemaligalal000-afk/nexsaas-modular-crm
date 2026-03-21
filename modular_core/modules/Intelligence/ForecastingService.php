<?php
/**
 * Intelligence/ForecastingService.php
 * 
 * CORE → ADVANCED: Predictive Revenue Forecasting (P2)
 */

declare(strict_types=1);

namespace Modules\Intelligence;

use Core\BaseService;

class ForecastingService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Forecast revenue for the next 3 months based on current deals
     * Uses Weighted Aging (Rule: Probability by stage)
     */
    public function getRevenueForecast(string $tenantId, string $companyCode): array
    {
        // 1. Fetch current deals with stage probabilities
        $sql = "SELECT stage, SUM(amount) as total_value, COUNT(*) as deal_count
                FROM crm_deals 
                WHERE tenant_id = ? AND company_code = ? 
                  AND stage NOT IN ('closed_won', 'closed_lost') 
                  AND deleted_at IS NULL
                GROUP BY stage";
        
        $deals = $this->db->GetAll($sql, [$tenantId, $companyCode]);

        $probabilities = [
            'prospecting' => 0.1,  // 10%
            'qualification' => 0.2, // 20%
            'proposal' => 0.5,      // 50%
            'negotiation' => 0.8    // 80%
        ];

        $forecastValue = 0;
        $details = [];

        foreach ($deals as $d) {
             $prob = $probabilities[$d['stage']] ?? 0;
             $weightedValue = $d['total_value'] * $prob;
             $forecastValue += $weightedValue;

             $details[] = [
                'stage' => $d['stage'],
                'gross_value' => $d['total_value'],
                'probability' => $prob,
                'weighted_forecast' => $weightedValue
             ];
        }

        // 2. Add Average MRR from SaaS subscriptions
        $mrr = $this->db->GetOne(
            "SELECT SUM(amount) FROM billing_subscriptions WHERE tenant_id = ? AND status = 'active'", 
            [$tenantId]
        );

        return [
            'forecast_period' => 'Next 90 Days',
            'weighted_deal_forecast' => round($forecastValue, 2),
            'committed_mrr' => round($mrr ?? 0, 2),
            'projected_cashflow' => round($forecastValue + (($mrr ?? 0) * 3), 2),
            'confidence' => $forecastValue > 0 ? 'Medium' : 'Low',
            'details' => $details
        ];
    }
}
