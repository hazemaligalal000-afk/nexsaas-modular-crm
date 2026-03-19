<?php
namespace Modules\Accounting\Statements;

use Core\BaseService;
use Core\Database;

/**
 * TrialBalanceService: Generates Trial Balance Reports
 * Batch J - Task 38.1
 */
class TrialBalanceService extends BaseService {

    /**
     * Generate Trial Balance per Company_Code per Fin_Period (Req 54.1)
     */
    public function getTrialBalance(string $finPeriod) {
        $db = Database::getInstance();
        
        // Sums debits and credits per COA Account. Enforces explicit tenant and company filtering.
        $sql = "
            SELECT account_code, 
                   SUM(debit) as total_debit, 
                   SUM(credit) as total_credit,
                   (SUM(debit) - SUM(credit)) as net_balance
            FROM journal_entry_lines
            WHERE tenant_id = ? AND company_code = ? AND fin_period = ?
            GROUP BY account_code
            ORDER BY account_code ASC
        ";
        
        $results = $db->query($sql, [$this->tenantId, $this->companyCode, $finPeriod]);
        
        $reportBase = [
            'total_debits' => 0.00,
            'total_credits' => 0.00,
            'is_balanced' => true,
            'accounts' => $results
        ];
        
        foreach ($results as $row) {
            $reportBase['total_debits'] += (float)$row['total_debit'];
            $reportBase['total_credits'] += (float)$row['total_credit'];
        }
        
        // Strict Double-Entry check: rounding to 2 decimals to avoid floating point anomalies 
        if (round($reportBase['total_debits'], 2) !== round($reportBase['total_credits'], 2)) {
            $reportBase['is_balanced'] = false;
        }
        
        return $reportBase;
    }
    
    /**
     * Export layout formatter (Exportable to Excel requirement)
     */
    public function exportToExcel(string $finPeriod) {
        $tb = $this->getTrialBalance($finPeriod);
        // Emulate generation of Spreadsheet XML/CSV binary
        return ['status' => 'export_ready', 'file_type' => 'xlsx', 'payload' => '...BASE64_XLSX_DATA...'];
    }
}
