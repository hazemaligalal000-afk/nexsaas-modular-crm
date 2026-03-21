<?php
/**
 * ERP/InventoryService.php
 * 
 * BATCH INVENTORY — Stock & Valuation Engine
 */

declare(strict_types=1);

namespace Modules\ERP;

use Core\BaseService;

class InventoryService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Record a stock movement (IN/OUT)
     * Calculates: Weighted Average Cost (WAC)
     */
    public function recordMovement(string $tenantId, string $companyCode, string $itemCode, float $qty, float $unitCost, string $warehouseCode, string $type): array
    {
        // 1. Get current stock levels and unit price (Rule: FIFO/WAC)
        $sql = "SELECT current_qty, avg_unit_cost FROM erp_inventory_summary 
                WHERE tenant_id = ? AND company_code = ? AND item_code = ? AND warehouse_code = ?";
        
        $current = $this->db->GetRow($sql, [$tenantId, $companyCode, $itemCode, $warehouseCode]);

        if (!$current) {
            $current = ['current_qty' => 0, 'avg_unit_cost' => 0];
        }

        $newQty = $current['current_qty'] + $qty;
        $newAvgCost = $current['avg_unit_cost'];

        // 2. Recalculate WAC on stock-in (Rule 7.5)
        if ($qty > 0) {
            $totalValue = ($current['current_qty'] * $current['avg_unit_cost']) + ($qty * $unitCost);
            $newAvgCost = $newQty > 0 ? $totalValue / $newQty : $unitCost;
        }

        // 3. Upsert inventory summary
        $sql = "INSERT INTO erp_inventory_summary 
                (tenant_id, company_code, item_code, warehouse_code, current_qty, avg_unit_cost, last_movement_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON CONFLICT (tenant_id, company_code, item_code, warehouse_code) 
                DO UPDATE SET 
                    current_qty = EXCLUDED.current_qty,
                    avg_unit_cost = EXCLUDED.avg_unit_cost,
                    last_movement_at = NOW()";
        
        $this->db->Execute($sql, [$tenantId, $companyCode, $itemCode, $warehouseCode, $newQty, $newAvgCost]);

        // 4. Record detailed log
        $this->db->Execute(
            "INSERT INTO erp_inventory_movements (tenant_id, company_code, item_code, warehouse_code, qty, unit_cost, type, movement_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$tenantId, $companyCode, $itemCode, $warehouseCode, $qty, $unitCost, $type]
        );

        return [
            'item_code' => $itemCode,
            'new_qty' => $newQty,
            'avg_cost' => $newAvgCost
        ];
    }
}
