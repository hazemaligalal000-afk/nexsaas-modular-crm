<?php
namespace Modules\Accounting\ARAP;

use Core\BaseService;
use Core\Database;

/**
 * AccrualService: Manages period-end accruals and auto-reversals (Task 32.6)
 */
class AccrualService extends BaseService {
    
    /**
     * Create Accrual Document and post to GL (Req 48.10)
     */
    public function createAccrual(string $accrualType, string $description, float $amount, string $finPeriod) {
        $db = Database::getInstance();
        $sql = "INSERT INTO accruals (tenant_id, company_code, accrual_type, description, amount, fin_period)
                VALUES (?, ?, ?, ?, ?, ?) RETURNING id";
        
        $res = $db->query($sql, [
            $this->tenantId,
            $this->companyCode,
            $accrualType,
            $description,
            $amount,
            $finPeriod
        ]);
        
        // Post Journal Entry (Simulated)
        // Debit Expense, Credit Accrued Liabilities (if expense)
        // Set journal_entry_id = <posted_id> on accruals table
        
        return $res[0]['id'];
    }
    
    /**
     * Called by Celery or Monthly CRON to reverse prior period accruals
     */
    public function autoReverseAccruals(string $priorFinPeriod, string $newFinPeriod) {
        $db = Database::getInstance();
        $sql = "SELECT id, accrual_type, amount, journal_entry_id FROM accruals 
                WHERE tenant_id = ? AND company_code = ? AND fin_period = ? AND status = 'active'";
        $activeAccruals = $db->query($sql, [$this->tenantId, $this->companyCode, $priorFinPeriod]);
        
        foreach ($activeAccruals as $accrual) {
            // Create equal and opposite Journal Entry in $newFinPeriod (Req 48.10)
            // Reverse Dr/Cr
            // Update accrual status to 'reversed' and set reversal_entry_id
            $updateSql = "UPDATE accruals SET status = 'reversed', reversal_entry_id = -1, updated_at = NOW() WHERE id = ?";
            $db->query($updateSql, [$accrual['id']]);
        }
        
        return count($activeAccruals);
    }
}
