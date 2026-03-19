<?php
namespace Modules\Accounting\ARAP;

use Core\BaseService;
use Core\Database;

/**
 * CustomerStatementService: Running balance, AR statements
 * Task 32.9
 */
class CustomerStatementService extends BaseService {

    /**
     * Get Customer Statement (all transactions, payments, running balance per Fin_Period)
     * Req 48.13
     */
    public function getStatement(string $customerCode, string $finPeriod) {
        $db = Database::getInstance();
        
        // 1. Fetch Invoices (Debits to AR)
        $invoicesRaw = $db->query(
            "SELECT invoice_date as date, 'Invoice' as type, invoice_number as reference, total_amount as debit, 0 as credit " .
            "FROM ar_invoices WHERE tenant_id = ? AND company_code = ? AND customer_code = ? AND fin_period = ?",
            [$this->tenantId, $this->companyCode, $customerCode, $finPeriod]
        );
        
        // 2. Fetch Payments (Credits to AR)
        $paymentsRaw = $db->query(
            "SELECT payment_date as date, 'Payment' as type, reference_number as reference, 0 as debit, amount as credit " .
            "FROM payments WHERE tenant_id = ? AND company_code = ? AND partner_code = ? " .
            "AND DATE_TRUNC('month', payment_date) = TO_DATE(?, 'YYYYMM')",
            [$this->tenantId, $this->companyCode, $customerCode, $finPeriod]
        );
        
        // 3. Merge, Sort, and Calculate Running Balance
        $transactions = array_merge($invoicesRaw, $paymentsRaw);
        usort($transactions, fn($a, $b) => strcmp($a['date'], $b['date']));
        
        $runningBalance = 0; // Assuming opening balance = 0 for this simplified snippet
        foreach ($transactions as &$tx) {
            $runningBalance += ($tx['debit'] - $tx['credit']);
            $tx['running_balance'] = $runningBalance;
        }
        
        return $transactions;
    }
}
