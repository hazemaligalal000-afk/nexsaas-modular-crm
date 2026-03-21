<?php
/**
 * Intelligence/StockPredictionService.php
 * 
 * CORE → ADVANCED: Predictive Inventory Replenishment & Stock-Out Alerting
 */

declare(strict_types=1);

namespace Modules\Intelligence;

use Core\BaseService;

class StockPredictionService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Predict stock-out date for an item based on historical sales velocity
     * Rule: Linear regression baseline
     */
    public function predictStockOut(string $tenantId, string $companyCode, string $itemCode): array
    {
        // 1. Fetch sales velocity (Last 30 days)
        $sql = "SELECT SUM(ABS(qty)) / 30 as avg_velocity 
                FROM erp_inventory_movements 
                WHERE tenant_id = ? AND company_code = ? AND item_code = ? 
                  AND qty < 0 AND movement_at >= NOW() - INTERVAL '30 days'";
        
        $velocity = (float)$this->db->GetOne($sql, [$tenantId, $companyCode, $itemCode]);

        // 2. Fetch current stock
        $currentQty = (float)$this->db->GetOne(
            "SELECT current_qty FROM erp_inventory_summary WHERE tenant_id = ? AND item_code = ?",
            [$tenantId, $itemCode]
        );

        // 3. Automated Prediction
        $daysRemaining = $velocity > 0 ? $currentQty / $velocity : 999;
        $predictionDate = date('Y-m-d', strtotime('+' . (int)$daysRemaining . ' days'));

        return [
            'item_code' => $itemCode,
            'current_stock' => $currentQty,
            'daily_velocity' => round($velocity, 2),
            'days_remaining' => (int)$daysRemaining,
            'predicted_exhaustion_date' => $predictionDate,
            'risk_level' => $daysRemaining < 7 ? 'Critical' : ($daysRemaining < 14 ? 'Warning' : 'Healthy')
        ];
    }
}
