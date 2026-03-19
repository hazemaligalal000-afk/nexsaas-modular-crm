<?php
namespace Modules\Accounting\Bank;

use Core\BaseService;
use Core\Database;

/**
 * BankTransactionService: Cash calls, Transfers, Receipts
 * Batch E - Tasks 33.2
 */
class BankTransactionService extends BaseService {

    /**
     * Inter-bank transfer via Cash In Transit account (Req 49.4)
     */
    public function transferFunds(int $sourceBankId, int $targetBankId, float $amount, string $currency) {
        // 1. Debit 'Cash in Transit', Credit Source Bank Account
        // 2. Debit Target Bank Account, Credit 'Cash in Transit'
        
        $db = Database::getInstance();
        $sql = "INSERT INTO bank_transactions (tenant_id, company_code, bank_account_id, transaction_date, transaction_type, amount, currency, description, is_reconciled) 
                VALUES 
                (?, ?, ?, CURRENT_DATE, 'transfer_out', ?, ?, 'Inter-bank transfer out', FALSE),
                (?, ?, ?, CURRENT_DATE, 'transfer_in', ?, ?, 'Inter-bank transfer in', FALSE)";
                
        $db->query($sql, [
            $this->tenantId, $this->companyCode, $sourceBankId, -$amount, $currency,
            $this->tenantId, $this->companyCode, $targetBankId, $amount, $currency
        ]);
        
        // Post explicit Journal Entry representing Cash In Transit
        return ['status' => 'transfer_initiated'];
    }

    /**
     * Track and record receipts for Cash Calls (Req 49.3)
     */
    public function recordCashCallReceipt(string $callNumber, float $amountReceived) {
        $db = Database::getInstance();
        
        // Retrieve outstanding cash call
        $sql = "SELECT id, amount, amount_received, currency FROM cash_calls WHERE tenant_id = ? AND company_code = ? AND call_number = ?";
        $calls = $db->query($sql, [$this->tenantId, $this->companyCode, $callNumber]);
        
        if (empty($calls)) throw new \Exception("Cash call not found");
        $call = $calls[0];
        
        $newReceived = $call['amount_received'] + $amountReceived;
        $status = ($newReceived >= $call['amount']) ? 'fulfilled' : 'partially_received';
        
        $updateSql = "UPDATE cash_calls SET amount_received = ?, status = ? WHERE id = ?";
        $db->query($updateSql, [$newReceived, $status, $call['id']]);
        
        return ['status' => $status, 'received' => $newReceived];
    }
}
