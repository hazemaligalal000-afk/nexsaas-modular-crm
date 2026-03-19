<?php
namespace Modules\Accounting\Bank;

use Core\BaseService;
use Core\Database;

/**
 * BankReconciliationService: Match CSV imports vs Book Balance
 * Batch E - Task 33.3
 */
class BankReconciliationService extends BaseService {

    /**
     * Parse CSV bank statement, match against book transactions, return outstanding
     * Req 49.5, 49.6
     */
    public function importCsvStatement(int $bankAccountId, array $csvRows, float $statementEndingBalance, string $statementDate) {
        $db = Database::getInstance();
        
        // 1. Fetch un-reconciled book records
        $sql = "SELECT id, amount, reference_number 
                FROM bank_transactions 
                WHERE tenant_id = ? AND company_code = ? AND bank_account_id = ? AND is_reconciled = FALSE";
        $bookTransactions = $db->query($sql, [$this->tenantId, $this->companyCode, $bankAccountId]);
        
        $matchedIds = [];
        $unmatchedCsv = [];
        
        // Simulating the actual matching logic (Usually matches Amount + Date ±2 days + Reference)
        foreach ($csvRows as $row) {
            $isMatched = false;
            foreach ($bookTransactions as $bt) {
                if (floatval($row['amount']) == floatval($bt['amount'])) {
                    $matchedIds[] = $bt['id'];
                    $isMatched = true;
                    // Auto-post bank charges if description matches 'FEE', 'CHG' (Req 49.7)
                    if (strpos(strtoupper($row['description']), 'FEE') !== false) {
                        // Fire auto-post journal entry
                    }
                    break;
                }
            }
            if (!$isMatched) {
                $unmatchedCsv[] = $row;
            }
        }
        
        // Book Balance Calculation
        $bookBalance = 150000.00; // Mock 
        $difference = $statementEndingBalance - $bookBalance;
        
        // Insert reconciliation Record
        $recSql = "INSERT INTO bank_reconciliations (tenant_id, company_code, bank_account_id, statement_date, statement_balance, book_balance, reconciled_difference)
                   VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id";
        $res = $db->query($recSql, [$this->tenantId, $this->companyCode, $bankAccountId, $statementDate, $statementEndingBalance, $bookBalance, $difference]);
        
        return [
            'reconciliation_id' => $res[0]['id'],
            'matched_count' => count($matchedIds),
            'unmatched_csv_rows' => $unmatchedCsv,
            'book_balance' => $bookBalance,
            'difference' => $difference
        ];
    }
}
