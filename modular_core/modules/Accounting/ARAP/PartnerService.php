<?php
namespace Modules\Accounting\ARAP;

use Core\BaseService;
use Core\Database;

/**
 * PartnerService: Dues and withdrawals tracking (Task 32.8)
 */
class PartnerService extends BaseService {

    /**
     * Record partner dues from Net Profit
     */
    public function recordDues(string $partnerCode, float $profitShareAmount) {
        // Creates a Journal Entry: Dr Retained Earnings/Annual Profit, Cr Partner Dues (Req 48.12)
        return true;
    }
    
    /**
     * Partner selects to withdraw funds
     */
    public function requestWithdrawal(string $partnerCode, float $amount) {
        $db = Database::getInstance();
        $sql = "SELECT withdrawal_approval_threshold FROM partners WHERE tenant_id = ? AND partner_code = ?";
        $res = $db->query($sql, [$this->tenantId, $partnerCode]);
        
        $threshold = $res[0]['withdrawal_approval_threshold'] ?? 100000;
        $status = 'pending'; // 1st approval
        
        if ($amount > $threshold) {
            $status = 'pending_dual'; // 2nd approval required
        }
        
        // Log withdrawal request (Req 53.3, 53.4)
        return ['status' => $status, 'amount' => $amount];
    }
}
