<?php
/**
 * Intelligence/CLVService.php
 * 
 * CORE → ADVANCED: Predictive Customer Lifetime Value (CLV) Engine
 */

declare(strict_types=1);

namespace Modules\Intelligence;

use Core\BaseService;

class CLVService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Forecast the Customer Lifetime Value (CLV) for a partner
     * Formula: (Avg Sale * Frequency) * Lifespan
     */
    public function calculatePredictedCLV(int $partnerId): array
    {
        // 1. Fetch historical sales data (from Invoicing/Accounting)
        $sql = "SELECT AVG(amount) as avg_sale, COUNT(*) as total_orders, 
                       MIN(created_at) as first_seen, MAX(created_at) as last_seen
                FROM invoices 
                WHERE partner_id = ? AND status = 'paid' AND deleted_at IS NULL";
        
        $stats = $this->db->GetRow($sql, [$partnerId]);

        if (!$stats || $stats['total_orders'] === 0) return ['clv' => 0];

        // 2. Automated Lifecycle Prediction
        $lifespanMonths = (strtotime($stats['last_seen']) - strtotime($stats['first_seen'])) / 2592000;
        $orderFrequency = $stats['total_orders'] / max($lifespanMonths, 1);

        // 3. Automated CLV Forecast (Next 24 Months)
        $predictedCLV = $stats['avg_sale'] * $orderFrequency * 24;

        return [
            'partner_id' => $partnerId,
            'historical_revenue' => round($stats['avg_sale'] * $stats['total_orders'], 2),
            'monthly_velocity' => round($stats['avg_sale'] * $orderFrequency, 2),
            'predicted_24m_clv' => round($predictedCLV, 2),
            'tier' => $predictedCLV > 10000 ? 'Platinum' : ($predictedCLV > 5000 ? 'Gold' : 'Standard')
        ];
    }
}
