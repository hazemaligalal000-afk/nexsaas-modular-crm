<?php
namespace Modules\Accounting\Bank;

use Core\BaseService;
use Core\Database;

/**
 * PettyCashService: Petty Cash Fund management (Batch E - 33.2)
 */
class PettyCashService extends BaseService {

    /**
     * Issue new Petty Cash Fund to Custodian
     */
    public function issueFund(string $fundName, int $custodianUserId, float $limit, string $currency) {
        $db = Database::getInstance();
        $sql = "INSERT INTO petty_cash_funds (tenant_id, company_code, fund_name, custodian_user_id, currency, fund_limit, current_balance)
                VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id";
                
        $res = $db->query($sql, [
            $this->tenantId, $this->companyCode, $fundName, $custodianUserId, $currency, $limit, $limit
        ]);
        
        // Auto-post: Debit Petty Cash, Credit Main Bank Account
        
        return $res[0]['id'];
    }
    
    /**
     * Replenish from provided submitted receipts amount (Req 49.2)
     */
    public function replenishFund(int $fundId, float $totalReceiptsAmount) {
        $db = Database::getInstance();
        $sql = "UPDATE petty_cash_funds 
                SET current_balance = current_balance + ? 
                WHERE tenant_id = ? AND company_code = ? AND id = ?";
                
        $db->query($sql, [$totalReceiptsAmount, $this->tenantId, $this->companyCode, $fundId]);
        
        // Auto-post: Debit relevant Cost Centers/Expenses (from receipts), Credit Main Bank Account
        return ['status' => 'replenished', 'replenished_amount' => $totalReceiptsAmount];
    }
}
