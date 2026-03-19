<?php
namespace Modules\Accounting\Payroll;

use Core\BaseService;
use Core\Database;

/**
 * PayrollBankExportService: Formats ATM salary files 
 * Batch H - Task 36.3
 */
class PayrollBankExportService extends BaseService {

    /**
     * Generate Bank Transfer CSV/TXT file for active net payroll (Req 52.6)
     */
    public function generateATMTransferFile(int $runId, string $bankFormat = 'SAIB_EGP') {
        $db = Database::getInstance();
        
        // Fetch only valid lines with Net > 0
        $sql = "SELECT e.first_name, e.last_name, e.bank_iban, e.national_id, pl.net_pay 
                FROM payroll_lines pl
                JOIN employees e ON pl.employee_id = e.id
                WHERE pl.tenant_id = ? AND pl.payroll_run_id = ? AND pl.status = 'valid' AND pl.net_pay > 0";
                
        $lines = $db->query($sql, [$this->tenantId, $runId]);
        
        $outputFile = [];
        $totalTransfer = 0;
        
        if ($bankFormat === 'SAIB_EGP') {
            // Header Row
            $outputFile[] = "IBAN,Beneficiary Name,National ID,Transfer Amount,Currency,Reference";
            
            foreach ($lines as $row) {
                $fullName = strtoupper("{$row['first_name']} {$row['last_name']}");
                $amountStr = number_format($row['net_pay'], 2, '.', '');
                
                $outputFile[] = "{$row['bank_iban']},{$fullName},{$row['national_id']},{$amountStr},EGP,SALARY_RUN_{$runId}";
                $totalTransfer += $row['net_pay'];
            }
        }
        
        return [
            'status' => 'export_ready',
            'file_format' => 'csv',
            'data' => implode("\n", $outputFile),
            'record_count' => count($lines),
            'total_transfer' => $totalTransfer
        ];
    }
}
