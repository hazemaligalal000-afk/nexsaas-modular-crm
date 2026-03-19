<?php
namespace Modules\Reporting;

use Core\BaseService;
use Core\Database;
use Modules\Accounting\Statements\IncomeStatementService;
use Modules\Accounting\Statements\BalanceSheetService;

/**
 * FinancialRatiosService: Calculates dynamic key KPIs
 * Batch L - Task 40.2
 */
class FinancialRatiosService extends BaseService {

    /**
     * Compute native ratios (Current, Quick, D/E, Gross, Net Margins) - Req 56.2
     */
    public function getKeyRatios(string $finPeriod) {
        $db = Database::getInstance();
        $ratios = [];
        
        // 1. Fetch current assets, current liabilities, inventory
        // (Assuming standard first two digits mapping CA=11, CL=21, INV=114)
        $sqlBS = "
            SELECT account_code, SUM(debit - credit) as net
            FROM journal_entry_lines
            WHERE tenant_id = ? AND company_code = ? AND fin_period <= ?
              AND (account_code LIKE '11%' OR account_code LIKE '21%' OR account_code LIKE '114%')
            GROUP BY account_code
        ";
        $resultsBS = $db->query($sqlBS, [$this->tenantId, $this->companyCode, $finPeriod]);
        
        $ca = 0; $cl = 0; $inv = 0;
        foreach ($resultsBS as $row) {
            if (strpos($row['account_code'], '11') === 0) $ca += floatval($row['net']);
            if (strpos($row['account_code'], '114') === 0) $inv += floatval($row['net']);
            if (strpos($row['account_code'], '21') === 0) $cl += (floatval($row['net']) * -1);
        }
        
        $ratios['current_ratio'] = $cl > 0 ? round($ca / $cl, 2) : 0;
        $ratios['quick_ratio'] = $cl > 0 ? round(($ca - $inv) / $cl, 2) : 0;
        
        // 2. Fetch Total Liabilities and Total Equity via generic mapping
        $bsData = (new BalanceSheetService($this->tenantId, $this->companyCode))->generateBalanceSheet($finPeriod);
        $totalLiabilities = $bsData['liabilities_total'];
        $totalEquity = $bsData['equity_total'];
        
        $ratios['debt_to_equity'] = $totalEquity > 0 ? round($totalLiabilities / $totalEquity, 2) : 0;
        
        // 3. Fetch Gross Margin (Revenue - COGS) and Net Margin
        $pnl = (new IncomeStatementService($this->tenantId, $this->companyCode))->generateStandardPnL($finPeriod);
        
        $revenue = $pnl['total_income']; // Approx
        $cogsSql = "SELECT SUM(debit - credit) as cogs FROM journal_entry_lines WHERE tenant_id = ? AND company_code = ? AND fin_period = ? AND account_code LIKE '51%'"; // cost of sales
        $cogs = $db->query($cogsSql, [$this->tenantId, $this->companyCode, $finPeriod])[0]['cogs'] ?? 0;
        
        $grossMarginAmount = $revenue - $cogs;
        $netIncomeAmount = $pnl['net_income'];
        
        $ratios['gross_margin_pct'] = $revenue > 0 ? round(($grossMarginAmount / $revenue) * 100, 2) : 0;
        $ratios['net_margin_pct'] = $revenue > 0 ? round(($netIncomeAmount / $revenue) * 100, 2) : 0;
        
        return [
            'fin_period' => $finPeriod,
            'kpis' => $ratios
        ];
    }
}
