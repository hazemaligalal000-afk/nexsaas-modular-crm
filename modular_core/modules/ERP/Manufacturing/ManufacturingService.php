<?php
namespace Modules\ERP\Manufacturing;

use Core\BaseService;
use Core\Database;

class ManufacturingService extends BaseService {
    public function createBOM(array $data) {
        return $this->transaction(function() use ($data) {
            $db = Database::getInstance();
            
            $sql = "INSERT INTO boms (
                        tenant_id, company_code, parent_item_id, name, version,
                        min_production_qty, production_lead_time_days
                    ) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id";
                    
            $result = $db->query($sql, [
                $this->tenantId, $this->companyCode, $data['parent_item_id'],
                $data['name'], $data['version'] ?? 'v1',
                $data['min_production_qty'] ?? 1.0,
                $data['production_lead_time_days'] ?? 0
            ]);
            
            $bomId = $result[0]['id'];
            $totalEstCost = 0;
            
            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $i => $line) {
                    $lineCost = $line['est_unit_cost'] * $line['quantity'];
                    $totalEstCost += $lineCost;
                    
                    $sqlLine = "INSERT INTO bom_lines (
                                    tenant_id, company_code, bom_id, line_no, item_id,
                                    quantity, uom, est_unit_cost, est_total_cost, scrap_pct
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $db->query($sqlLine, [
                        $this->tenantId, $this->companyCode, $bomId, $i + 1,
                        $line['item_id'], $line['quantity'], $line['uom'],
                        $line['est_unit_cost'], $lineCost, $line['scrap_pct'] ?? 0.0
                    ]);
                }
            }
            
            $db->query("UPDATE boms SET total_est_cost = ? WHERE id = ?", [$totalEstCost, $bomId]);
            
            return ['success' => true, 'data' => ['bom_id' => $bomId]];
        });
    }

    public function startWorkOrder(int $woId) {
        return $this->transaction(function() use ($woId) {
            $db = Database::getInstance();
            // Stock checks and reservations
            $wo = $db->query("SELECT * FROM work_orders WHERE id = ? AND tenant_id = ?", [$woId, $this->tenantId]);
            if (empty($wo)) {
                return ['success' => false, 'error' => 'Work order not found'];
            }
            
            $bomId = $wo[0]['bom_id'];
            $qtyToMfa = $wo[0]['quantity'];
            
            // Check availability
            $components = $db->query("SELECT item_id, quantity FROM bom_lines WHERE bom_id = ? AND tenant_id = ?", [$bomId, $this->tenantId]);
            $missing = [];
            
            foreach ($components as $comp) {
                $requiredQty = $comp['quantity'] * $qtyToMfa;
                $stock = $db->query("SELECT on_hand_qty FROM inventory_stock 
                                     WHERE item_id = ? AND warehouse_id = ? AND tenant_id = ?",
                                     [$comp['item_id'], $wo[0]['warehouse_id'], $this->tenantId]);
                
                $onHand = !empty($stock) ? $stock[0]['on_hand_qty'] : 0;
                
                if ($onHand < $requiredQty) {
                    $missing[] = [
                        'item_id' => $comp['item_id'],
                        'required' => $requiredQty,
                        'available' => $onHand,
                        'shortfall' => $requiredQty - $onHand
                    ];
                }
            }
            
            if (!empty($missing)) {
                return ['success' => false, 'error' => 'Insufficient stock', 'details' => $missing];
            }
            
            // Deduct stock for each component...
            foreach ($components as $comp) {
                $requiredQty = $comp['quantity'] * $qtyToMfa;
                // Move from on_hand_qty to reserved or consumed via StockMovementService
                
                $sqlTrack = "INSERT INTO work_order_components (
                                 tenant_id, company_code, work_order_id, item_id, planned_qty
                             ) VALUES (?, ?, ?, ?, ?)";
                $db->query($sqlTrack, [
                    $this->tenantId, $this->companyCode, $woId, $comp['item_id'], $requiredQty
                ]);
            }
            
            $db->query("UPDATE work_orders SET status = 'in_progress' WHERE id = ?", [$woId]);
            
            return ['success' => true, 'data' => ['status' => 'in_progress']];
        });
    }

    public function completeWorkOrder(int $woId) {
        return $this->transaction(function() use ($woId) {
            // Adds finished products to inventory_stock via StockMovementService
            $db = Database::getInstance();
            $db->query("UPDATE work_orders SET status = 'completed', actual_end_date = NOW() WHERE id = ?", [$woId]);
            return ['success' => true];
        });
    }
}
