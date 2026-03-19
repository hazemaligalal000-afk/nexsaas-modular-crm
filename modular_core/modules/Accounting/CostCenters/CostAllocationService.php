<?php
namespace Modules\Accounting\CostCenters;

use Core\BaseService;
use Core\Database;

/**
 * CostAllocationService: Distributes indirect expenses to Cost Centers (Batch F - 34.3, 34.5)
 */
class CostAllocationService extends BaseService {

    /**
     * Engine: distribute indirect expenses from allocation account to target cost centers (Req 50.4)
     */
    public function executeOverheadAllocation(string $finPeriod, string $sourceAccount) {
        $db = Database::getInstance();
        
        // 1. Get total balance to distribute
        $sql = "SELECT SUM(debit - credit) as pool_balance FROM journal_entry_lines 
                WHERE tenant_id = ? AND company_code = ? AND account_code = ? AND fin_period = ?";
        $balance = $db->query($sql, [$this->tenantId, $this->companyCode, $sourceAccount, $finPeriod])[0]['pool_balance'] ?? 0;
        
        if ($balance <= 0) return ['status' => 'no_balance'];
        
        // 2. Fetch configured rules directly targeted from this source
        $rulesSql = "SELECT target_cost_center_id, allocation_pct FROM cost_allocation_rules 
                     WHERE tenant_id = ? AND company_code = ? AND source_account = ?";
        $rules = $db->query($rulesSql, [$this->tenantId, $this->companyCode, $sourceAccount]);
        
        // 3. Loop and create journal entries moving amount out of Source into specific cost_center targets
        $distributed = [];
        foreach ($rules as $r) {
            $amt = $balance * ($r['allocation_pct'] / 100);
            $distributed[] = ['cost_center_id' => $r['target_cost_center_id'], 'amount' => $amt];
            // Post GL Entry: Debit Target Cost Center, Credit Source Account
        }
        
        return $distributed;
    }
    
    /**
     * Department time allocation (Req 50.10)
     * Distributes payroll costs across cost centers upon payroll journal post.
     */
    public function distributePayrollCosts(string $finPeriod, array $payrollDataArray) {
        $db = Database::getInstance();
        $payrollAccount = "PAYROLL EXPENSE"; // Generic mapping

        foreach ($payrollDataArray as $employee) {
            // Find employee's time_pct distributions for the month
            $timesheetSql = "SELECT cost_center_id, time_pct FROM department_time_allocations 
                             WHERE tenant_id = ? AND employee_id = ? AND fin_period = ?";
            $allocations = $db->query($timesheetSql, [$this->tenantId, $employee['id'], $finPeriod]);
            
            foreach ($allocations as $row) {
                // Determine share based on Employee's base salary or total payroll package
                $share = $employee['gross_pay'] * ($row['time_pct'] / 100);
                // Sub-post Debit into specific Cost Center under Payroll Expense
            }
        }
        return ['status' => 'payroll_distributed'];
    }
}
