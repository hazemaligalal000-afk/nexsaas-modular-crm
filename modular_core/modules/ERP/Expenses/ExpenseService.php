<?php
namespace Modules\ERP\Expenses;

use Core\BaseService;
use Core\Database;

class ExpenseService extends BaseService {
    public function submitClaim(array $data) {
        return $this->transaction(function() use ($data) {
            $db = Database::getInstance();
            // Basic validation
            if (empty($data['employee_id']) || empty($data['total_amount'])) {
                return ['success' => false, 'error' => 'Missing required fields'];
            }

            $claimNo = 'EXP-' . date('Ymd') . '-' . rand(1000, 9999);
            
            $sql = "INSERT INTO expense_claims (
                        tenant_id, company_code, claim_no, employee_id, claim_date, total_amount, status
                    ) VALUES (?, ?, ?, ?, ?, ?, 'submitted') RETURNING id";
                    
            $params = [
                $this->tenantId,
                $this->companyCode,
                $claimNo,
                $data['employee_id'],
                date('Y-m-d'),
                $data['total_amount']
            ];
            
            $result = $db->query($sql, $params);
            return ['success' => true, 'data' => ['claim_id' => $result[0]['id'], 'claim_no' => $claimNo]];
        });
    }

    public function approveClaim(int $claimId, int $approverId) {
        return $this->transaction(function() use ($claimId, $approverId) {
            $db = Database::getInstance();
            
            // Check if exists
            $claim = $db->query("SELECT * FROM expense_claims WHERE id = ? AND tenant_id = ?", [$claimId, $this->tenantId]);
            if (empty($claim)) {
                return ['success' => false, 'error' => 'Claim not found'];
            }
            
            $sql = "UPDATE expense_claims SET status = 'approved', approver_id = ?, approved_at = NOW() 
                    WHERE id = ? AND tenant_id = ?";
            $db->query($sql, [$approverId, $claimId, $this->tenantId]);
            
            // Post journal entry mock
            $journalEntryId = rand(1000, 9999);
            $db->query("UPDATE expense_claims SET journal_entry_id = ? WHERE id = ?", [$journalEntryId, $claimId]);
            
            return ['success' => true, 'data' => ['claim_id' => $claimId, 'status' => 'approved', 'journal_entry_id' => $journalEntryId]];
        });
    }

    public function threeWayMatch(int $poId, int $grId, int $invoiceId) {
        return $this->transaction(function() use ($poId, $grId, $invoiceId) {
            $db = Database::getInstance();
            
            // This is a simplified three-way match implementation
            // 1. Get PO totals
            $po = $db->query("SELECT total_amount FROM purchase_orders WHERE id = ?", [$poId]);
            
            // 2. Get Supplier Invoice totals
            $inv = $db->query("SELECT total_amount FROM supplier_invoices WHERE id = ?", [$invoiceId]);
            
            if (empty($po) || empty($inv)) {
                return ['success' => false, 'error' => 'Documents not found'];
            }
            
            $poTotal = $po[0]['total_amount'];
            $invTotal = $inv[0]['total_amount'];
            
            $discrepancyAmount = abs($invTotal - $poTotal);
            $discrepancyPct = ($poTotal > 0) ? ($discrepancyAmount / $poTotal) * 100 : 0;
            
            $isMatched = $discrepancyPct <= 5.0;
            
            $sql = "UPDATE supplier_invoices SET is_matched = ?, discrepancy_amount = ?, discrepancy_pct = ? WHERE id = ?";
            $db->query($sql, [
                $isMatched ? 'true' : 'false',
                $discrepancyAmount,
                $discrepancyPct,
                $invoiceId
            ]);
            
            return [
                'success' => true, 
                'data' => [
                    'is_matched' => $isMatched, 
                    'discrepancy_pct' => $discrepancyPct
                ]
            ];
        });
    }
}
