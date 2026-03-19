<?php
namespace Modules\Accounting;

use Core\BaseService;
use Core\Database;

/**
 * PeriodService: Manages open/closed/locked financial periods (Batch N - 41.2)
 */
class PeriodService extends BaseService {

    /**
     * Check if a period is open for posting
     * Req 58.2
     */
    public function isPeriodOpen(string $companyCode, string $finPeriod): bool {
        $db = Database::getInstance();
        $sql = "SELECT status FROM financial_periods 
                WHERE tenant_id = ? AND company_code = ? AND period_code = ?";
        $res = $db->query($sql, [$this->tenantId, $companyCode, $finPeriod]);
        
        return !empty($res) && $res[0]['status'] === 'open';
    }

    /**
     * Close a period, strictly preventing further backdated entries
     * Req 58.2
     */
    public function closePeriod(string $companyCode, string $finPeriod) {
        $db = Database::getInstance();
        
        // Checklist verification would happen here (Req 54.9)
        
        $sql = "UPDATE financial_periods SET status = 'closed', closed_at = NOW() 
                WHERE tenant_id = ? AND company_code = ? AND period_code = ?";
        return $db->execute($sql, [$this->tenantId, $companyCode, $finPeriod]);
    }
    
    /**
     * List all periods with their status
     */
    public function listPeriods(string $companyCode) {
        $db = Database::getInstance();
        $sql = "SELECT * FROM financial_periods WHERE tenant_id = ? AND company_code = ? ORDER BY period_code DESC";
        return $db->query($sql, [$this->tenantId, $companyCode]);
    }
}
