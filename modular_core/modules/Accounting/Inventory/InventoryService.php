<?php
namespace Modules\Accounting\Inventory;

use Core\BaseService;

/**
 * Inventory Service: Handle stock management & valuation.
 * Requirement 19.3: FIFO/LIFO/Weighted Average methods.
 */
class InventoryService extends BaseService {
    
    public function calculateValuation(string $tenantId, string $itemId, string $method = 'FIFO') {
        $sql = "SELECT * FROM inventory_transactions WHERE tenant_id = ? AND item_id = ? ORDER BY transaction_date ASC";
        $txs = $this->db->GetAll($sql, [$tenantId, $itemId]);
        
        switch ($method) {
            case 'LIFO':
                usort($txs, fn($a, $b) => strcmp($b['transaction_date'], $a['transaction_date']));
                break;
            case 'WAV':
                return $this->weightedAverage($txs);
            default: // FIFO
                // Already sorted ASC
                break;
        }

        $valuation = 0.0;
        $remainingStock = 0;
        foreach ($txs as $tx) {
            if ($tx['type'] === 'IN') {
                $valuation += ($tx['quantity'] * $tx['unit_cost']);
                $remainingStock += $tx['quantity'];
            } else {
                $costPerUnit = $remainingStock > 0 ? $valuation / $remainingStock : 0;
                $valuation -= ($tx['quantity'] * $costPerUnit);
                $remainingStock -= $tx['quantity'];
            }
        }
        
        return ['valuation' => $valuation, 'stock' => $remainingStock];
    }

    private function weightedAverage(array $txs) {
        $totalCost = 0.0;
        $totalQty = 0;
        foreach ($txs as $tx) {
            if ($tx['type'] === 'IN') {
                $totalCost += ($tx['quantity'] * $tx['unit_cost']);
                $totalQty += $tx['quantity'];
            }
        }
        return $totalQty > 0 ? $totalCost / $totalQty : 0;
    }
}
