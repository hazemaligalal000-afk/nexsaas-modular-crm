<?php
namespace Modules\Accounting\Bank;

use Core\BaseService;
use Core\Database;
use Modules\Accounting\FX\FXService;

/**
 * CashPositionService: Multi-currency wallet tracking to total EGP
 * Batch E - Task 33.4
 */
class CashPositionService extends BaseService {

    /**
     * Get Cash dashboard (All bank/wallet balances dynamically translated)
     * Req 49.8, 49.9, 49.10
     */
    public function getCashPositionDashboard(string $asOfDate) {
        $db = Database::getInstance();
        $fx = new FXService($this->tenantId, $this->companyCode);
        
        $sql = "
            SELECT ba.id, ba.account_name, ba.currency, ba.bank_name,
                   COALESCE(SUM(bt.amount), 0) as native_balance
            FROM bank_accounts ba
            LEFT JOIN bank_transactions bt ON ba.id = bt.bank_account_id AND bt.transaction_date <= ?
            WHERE ba.tenant_id = ? AND ba.company_code = ?
            GROUP BY ba.id, ba.account_name, ba.currency, ba.bank_name
        ";
        
        $accounts = $db->query($sql, [$asOfDate, $this->tenantId, $this->companyCode]);
        
        $totalEGP = 0.00;
        $positions = [];
        
        foreach ($accounts as $acc) {
            $currency = $acc['currency'];
            $nativeBalance = (float)$acc['native_balance'];
            
            $rate = ($currency === 'EGP') ? 1.00 : $fx->getRateForDate($currency, $asOfDate);
            $egpEquivalent = $nativeBalance * $rate;
            
            $totalEGP += $egpEquivalent;
            
            $positions[] = [
                'account_name' => $acc['account_name'],
                'bank_name' => $acc['bank_name'],
                'currency' => $currency,
                'native_balance' => $nativeBalance,
                'rate_applied' => $rate,
                'egp_equivalent' => round($egpEquivalent, 2)
            ];
        }
        
        return [
            'as_of_date' => $asOfDate,
            'total_egp_equivalent' => round($totalEGP, 2),
            'wallets' => $positions // Includes INSTAPAY, QNB E-WALLET, PAYPALL USD as defined in db schema
        ];
    }
}
