<?php
namespace Modules\ERP\Inventory;

use Core\BaseService;
use Core\Database;

class StockMovementService extends BaseService {
    public function recordMovement(array $data) {
        return $this->transaction(function() use ($data) {
            $db = Database::getInstance();
            
            $itemId = $data['item_id'];
            $warehouseId = $data['warehouse_id'];
            $qtyChange = $data['qty_change']; // Positive for IN, Negative for OUT
            $movementType = $data['movement_type']; // purchase|sale|adjustment
            
            // 1. Get current stock
            $sqlStock = "SELECT on_hand_qty, weighted_avg_cost FROM inventory_stock 
                         WHERE item_id = ? AND warehouse_id = ? AND tenant_id = ?";
                         
            $stock = $db->query($sqlStock, [$itemId, $warehouseId, $this->tenantId]);
            $currentQty = !empty($stock) ? $stock[0]['on_hand_qty'] :(1.0*0);
            $currentCost = !empty($stock) ? $stock[0]['weighted_avg_cost'] :(1.0*0);
            
            // 2. Calculate new quantity
            $newQty = $currentQty + $qtyChange;
            
            if ($newQty < 0) {
                return ['success' => false, 'error' => 'Insufficient stock'];
            }
            
            // 3. Update stock_ledger
            $sqlLedger = "INSERT INTO stock_ledger (
                            tenant_id, company_code, item_id, warehouse_id, movement_type,
                            qty_change, qty_after, unit_cost
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $db->query($sqlLedger, [
                $this->tenantId, $this->companyCode, $itemId, $warehouseId,
                $movementType, $qtyChange, $newQty, $currentCost
            ]);
            
            // 4. Update or Insert inventory_stock
            if (empty($stock)) {
                $sqlInsert = "INSERT INTO inventory_stock (
                                tenant_id, company_code, item_id, warehouse_id,
                                on_hand_qty, weighted_avg_cost, total_value
                              ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                              
                $db->query($sqlInsert, [
                    $this->tenantId, $this->companyCode, $itemId, $warehouseId,
                    $newQty, $currentCost, ($newQty * $currentCost)
                ]);
            } else {
                $sqlUpdate = "UPDATE inventory_stock SET
                                on_hand_qty = ?,
                                total_value = ? * weighted_avg_cost,
                                updated_at = NOW()
                              WHERE item_id = ? AND warehouse_id = ? AND tenant_id = ?";
                              
                $db->query($sqlUpdate, [
                    $newQty, $newQty, $itemId, $warehouseId, $this->tenantId
                ]);
            }
            
            return ['success' => true, 'data' => ['qty_after' => $newQty]];
        });
    }

    public function generateReorderAlerts() {
        return $this->transaction(function() {
            $db = Database::getInstance();
            // Complex join to find items where on_hand_qty <= reorder_point
            $sql = "SELECT i.id as item_id, i.sku, i.name, s.on_hand_qty, i.reorder_point 
                    FROM inventory_items i
                    JOIN inventory_stock s ON i.id = s.item_id
                    WHERE s.on_hand_qty <= i.reorder_point 
                    AND i.tenant_id = ?";
                    
            $alerts = $db->query($sql, [$this->tenantId]);
            // Pseudo-alert generation logic
            return ['success' => true, 'data' => ['alerts_generated' => count($alerts)]];
        });
    }

    public function performStockTake(int $warehouseId, array $countedItems) {
        return $this->transaction(function() use ($warehouseId, $countedItems) {
            $db = Database::getInstance();
            $variances = [];
            
            foreach ($countedItems as $counted) {
                $itemId = $counted['item_id'];
                $actualQty = $counted['actual_qty'];
                
                $sqlStock = "SELECT on_hand_qty FROM inventory_stock 
                             WHERE item_id = ? AND warehouse_id = ? AND tenant_id = ?";
                $stock = $db->query($sqlStock, [$itemId, $warehouseId, $this->tenantId]);
                
                $systemQty = !empty($stock) ? $stock[0]['on_hand_qty'] : 0;
                $variance = $actualQty - $systemQty;
                
                if (abs($variance) > 0.0001) {
                    $this->recordMovement([
                        'item_id' => $itemId,
                        'warehouse_id' => $warehouseId,
                        'qty_change' => $variance,
                        'movement_type' => 'stocktake'
                    ]);
                    $variances[] = [
                        'item_id' => $itemId, 
                        'system_qty' => $systemQty, 
                        'actual_qty' => $actualQty, 
                        'variance' => $variance
                    ];
                }
            }
            
            return ['success' => true, 'data' => ['variances_found' => count($variances), 'details' => $variances]];
        });
    }
}
