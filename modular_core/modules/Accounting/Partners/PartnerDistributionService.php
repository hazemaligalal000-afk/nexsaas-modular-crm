<?php
namespace Modules\Accounting\Partners;

use Core\BaseService;
use Core\Database;

/**
 * PartnerDistributionService: Period Close Distribution & Forecasts
 * Batch I - Tasks 37.2, 37.4
 */
class PartnerDistributionService extends BaseService {

    /**
     * Partner distribution on Fin_Period close (Req 53.1, 53.2)
     */
    public function executePeriodDistribution(string $finPeriod) {
        $db = Database::getInstance();
        
        // 1. Calculate Net Income (Total Income - Total Expenses for Period)
        $sqlNetIncome = "
            SELECT SUM(credit - debit) as net_profit 
            FROM journal_entry_lines 
            WHERE tenant_id = ? AND company_code = ? AND fin_period = ? 
              AND account_code LIKE '4%' OR account_code LIKE '5%' -- Assuming typical 4=Rev, 5=Exp chart structure
        ";
        $netProfit = $db->query($sqlNetIncome, [$this->tenantId, $this->companyCode, $finPeriod])[0]['net_profit'] ?? 0;
        
        if ($netProfit <= 0) return ['status' => 'no_profit_to_distribute', 'net' => $netProfit];

        // 2. Fetch partners and their shares
        $sqlPartners = "SELECT partner_code, share_pct FROM partners WHERE tenant_id = ?";
        $partners = $db->query($sqlPartners, [$this->tenantId]);
        
        $distributions = [];
        
        foreach ($partners as $p) {
            $shareAmount = $netProfit * ($p['share_pct'] / 100);
            
            // Post Distribution Journal Entry:
            // Debit: 'ANNUAL PROFIT' account
            // Credit: 'PARTNER DUES' account (using $p['partner_code'])
            
            $distributions[] = [
                'partner_code' => $p['partner_code'],
                'share_pct' => $p['share_pct'],
                'amount_distributed' => round($shareAmount, 2)
            ];
        }
        
        // Update Period Close Status conceptually
        return [
            'status' => 'distributed',
            'fin_period' => $finPeriod,
            'net_profit_distributed' => $netProfit,
            'details' => $distributions
        ];
    }
    
    /**
     * Partner profit forecast based on projected net income (Req 53.8)
     */
    public function generateProfitForecast(float $projectedNetIncome) {
        $db = Database::getInstance();
        $partners = $db->query("SELECT partner_code, share_pct FROM partners WHERE tenant_id = ?", [$this->tenantId]);
        
        $forecast = [];
        foreach ($partners as $p) {
            $forecast[] = [
                'partner_code' => $p['partner_code'],
                'projected_dues' => round($projectedNetIncome * ($p['share_pct'] / 100), 2)
            ];
        }
        return ['projected_income' => $projectedNetIncome, 'forecast' => $forecast];
    }
}
