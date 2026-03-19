<?php
namespace Modules\ERP\Procurement;

use Core\BaseService;
use Core\Database;

class ProcurementService extends BaseService {
    public function submitRequisition(array $data) {
        return $this->transaction(function() use ($data) {
            $db = Database::getInstance();
            // Create a PR document
            $prNo = 'PR-' . date('Ymd') . '-' . rand(1000, 9999);
            
            $sql = "INSERT INTO purchase_requisitions (
                        tenant_id, company_code, pr_no, pr_date, department_id, requested_by, priority, purpose
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";
                    
            $result = $db->query($sql, [
                $this->tenantId, $this->companyCode, $prNo, date('Y-m-d'),
                $data['department_id'], $data['requested_by'], 
                $data['priority'] ?? 'medium', $data['purpose']
            ]);
            
            $prId = $result[0]['id'];
            
            // Insert line items
            if (!empty($data['lines']) && is_array($data['lines'])) {
                foreach ($data['lines'] as $line) {
                    $sqlLine = "INSERT INTO purchase_requisition_lines (
                                    tenant_id, company_code, pr_id, line_no, item_id, description, quantity
                                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $db->query($sqlLine, [
                        $this->tenantId, $this->companyCode, $prId,
                        $line['line_no'], $line['item_id'], $line['description'], $line['quantity']
                    ]);
                }
            }
            
            return ['success' => true, 'data' => ['pr_id' => $prId, 'pr_no' => $prNo]];
        });
    }

    public function generatePOFromRequisition(int $prId, int $vendorId) {
        return $this->transaction(function() use ($prId, $vendorId) {
            $db = Database::getInstance();
            
            $pr = $db->query("SELECT * FROM purchase_requisitions WHERE id = ? AND tenant_id = ?", [$prId, $this->tenantId]);
            if (empty($pr)) {
                return ['success' => false, 'error' => 'Requisition not found'];
            }
            
            $vendor = $db->query("SELECT company_name FROM accounts WHERE id = ? AND tenant_id = ?", [$vendorId, $this->tenantId]);
            if (empty($vendor)) {
                return ['success' => false, 'error' => 'Vendor not found'];
            }
            
            $poNo = 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
            
            $sqlPO = "INSERT INTO purchase_orders (
                          tenant_id, company_code, po_no, po_date, vendor_id, vendor_name, requisition_id, status
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft') RETURNING id";
                      
            $result = $db->query($sqlPO, [
                $this->tenantId, $this->companyCode, $poNo, date('Y-m-d'),
                $vendorId, $vendor[0]['company_name'], $prId
            ]);
            
            $poId = $result[0]['id'];
            
            $sqlLines = "SELECT * FROM purchase_requisition_lines WHERE pr_id = ? AND tenant_id = ?";
            $lines = $db->query($sqlLines, [$prId, $this->tenantId]);
            
            foreach ($lines as $line) {
                // Here we'd map estimated costs to actual unit prices in real app
                $sqlPOLine = "INSERT INTO purchase_order_lines (
                                tenant_id, company_code, po_id, line_no, item_id, description, quantity, unit_price
                              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $db->query($sqlPOLine, [
                    $this->tenantId, $this->companyCode, $poId,
                    $line['line_no'], $line['item_id'], $line['description'],
                    $line['quantity'], 0 // Stub unit price
                ]);
            }
            
            // Mark PR as ordered
            $db->query("UPDATE purchase_requisitions SET status = 'ordered' WHERE id = ?", [$prId]);
            
            return ['success' => true, 'data' => ['po_id' => $poId, 'po_no' => $poNo]];
        });
    }

    public function createRFQ(array $data) {
        return $this->transaction(function() use ($data) {
            $db = Database::getInstance();
            // Stub for RFQ generation
            $rfqNo = 'RFQ-' . date('Ymd') . '-' . rand(1000, 9999);
            
            $sql = "INSERT INTO rfqs (
                        tenant_id, company_code, rfq_no, rfq_date, closing_date, pr_id, status
                    ) VALUES (?, ?, ?, ?, ?, ?, 'draft') RETURNING id";
                    
            $result = $db->query($sql, [
                $this->tenantId, $this->companyCode, $rfqNo, date('Y-m-d'),
                $data['closing_date'], $data['pr_id'] ?? null
            ]);
            
            return ['success' => true, 'data' => ['rfq_id' => $result[0]['id'], 'rfq_no' => $rfqNo]];
        });
    }
}
