<?php
/**
 * Modules/Analytics/ApiController.php
 * Endpoint for Real-time Analytics Dashboard.
 */

namespace Modules\Analytics;

use Core\TenantEnforcer;

class ApiController {
    
    public function index() {
        $tenantId = TenantEnforcer::getTenantId();
        
        // This is where we query ClickHouse / TSDB / MySQL aggregated data
        
        return json_encode([
            'status' => 'success',
            'module' => 'Analytics',
            'data'   => [
                'kpis' => [
                    'mrr' => '$250,400',
                    'mrr_growth' => '+15%',
                    'nrra' => '112%', // Net Revenue Retention
                    'pipeline_value' => '$1,200,000',
                    'churn_status' => 'Low (2.4%)',
                    'expansion_revenue' => '$12,500'
                ],
                'investor_metrics' => [
                    'cac_payback_months' => 4.2,
                    'ltv_to_cac' => '5.8x',
                    'burn_multiple' => '0.85',
                    'rule_of_40' => '48%'
                ],
                'funnel_metrics' => [
                    ['stage' => 'Lead', 'count' => 500, 'value' => 0],
                    ['stage' => 'Qualified', 'count' => 300, 'value' => 300000],
                    ['stage' => 'Demo', 'count' => 150, 'value' => 750000],
                    ['stage' => 'Proposal', 'count' => 80, 'value' => 900000],
                    ['stage' => 'Closed Won', 'count' => 45, 'value' => 450000]
                ],
                'ai_insights' => [
                    "Expansion Revenue up 22% due to 'WhatsApp CRM' module upselling.",
                    "Rule of 40 score is 48% — Healthy Series A trajectory.",
                    "Rep 'John' has a 15% higher win rate when emails contain 'Enterprise SLA'."
                ]
            ]
        ]);
    }
}
