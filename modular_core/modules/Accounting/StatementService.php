<?php
/**
 * Accounting/StatementService.php
 * 
 * BATCH J — Financial Statements
 * Trial Balance, Profit & Loss, Balance Sheet
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseService;

class StatementService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get Trial Balance for a period
     */
    public function getTrialBalance(string $tenantId, string $companyCode, string $finPeriod): array
    {
        $sql = "
            SELECT 
                coa.account_code, 
                coa.account_name_en, 
                coa.account_name_ar, 
                coa.account_type,
                COALESCE(SUM(jel.dr_value_base), 0) as total_dr,
                COALESCE(SUM(jel.cr_value_base), 0) as total_cr,
                COALESCE(SUM(jel.dr_value_base) - SUM(jel.cr_value_base), 0) as net_balance
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel 
                ON coa.account_code = jel.account_code 
                AND coa.tenant_id = jel.tenant_id
                AND coa.company_code = jel.company_code
                AND jel.fin_period <= ?
                AND jel.deleted_at IS NULL
            WHERE coa.tenant_id = ? AND coa.company_code = ? AND coa.deleted_at IS NULL
            GROUP BY coa.id
            ORDER BY coa.account_code
        ";
        
        $results = $this->db->GetAll($sql, [$finPeriod, $tenantId, $companyCode]);
        return $results;
    }

    /**
     * Get Profit & Loss Statement (P&L)
     */
    public function getProfitLoss(string $tenantId, string $companyCode, string $startPeriod, string $endPeriod): array
    {
        $sql = "
            SELECT 
                coa.account_code, 
                coa.account_name_en, 
                coa.account_type,
                COALESCE(SUM(jel.dr_value_base), 0) as dr,
                COALESCE(SUM(jel.cr_value_base), 0) as cr
            FROM chart_of_accounts coa
            JOIN journal_entry_lines jel 
                ON coa.account_code = jel.account_code 
                AND coa.tenant_id = jel.tenant_id
                AND coa.company_code = jel.company_code
                AND jel.deleted_at IS NULL
            WHERE coa.tenant_id = ? 
              AND coa.company_code = ? 
              AND coa.account_type IN ('Income', 'Expense', 'Cost')
              AND jel.fin_period BETWEEN ? AND ?
            GROUP BY coa.id
            ORDER BY coa.account_type DESC, coa.account_code
        ";
        
        $results = $this->db->GetAll($sql, [$tenantId, $companyCode, $startPeriod, $endPeriod]);
        
        $income = 0;
        $expenses = 0;
        $data = ['income' => [], 'expenses' => [], 'net_profit' => 0];

        foreach ($results as $row) {
            $balance = $row['cr'] - $row['dr']; // Cr balance for income
            if ($row['account_type'] === 'Income') {
                $income += $balance;
                $data['income'][] = $row;
            } else {
                $balance = $row['dr'] - $row['cr']; // Dr balance for expenses
                $expenses += $balance;
                $data['expenses'][] = $row;
            }
        }

        $data['total_income'] = $income;
        $data['total_expenses'] = $expenses;
        $data['net_profit'] = $income - $expenses;

        return $data;
    }

    /**
     * Get Balance Sheet
     */
    public function getBalanceSheet(string $tenantId, string $companyCode, string $finPeriod): array
    {
        $sql = "
            SELECT 
                coa.account_code, 
                coa.account_name_en, 
                coa.account_type,
                COALESCE(SUM(jel.dr_value_base) - SUM(jel.cr_value_base), 0) as balance
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel 
                ON coa.account_code = jel.account_code 
                AND coa.tenant_id = jel.tenant_id
                AND coa.company_code = jel.company_code
                AND jel.fin_period <= ?
                AND jel.deleted_at IS NULL
            WHERE coa.tenant_id = ? 
              AND coa.company_code = ? 
              AND coa.account_type IN ('Asset', 'Liability', 'Equity')
            GROUP BY coa.id
            ORDER BY coa.account_type, coa.account_code
        ";
        
        $results = $this->db->GetAll($sql, [$finPeriod, $tenantId, $companyCode]);
        
        $assets = 0;
        $liabilities = 0;
        $equity = 0;
        $data = ['assets' => [], 'liabilities' => [], 'equity' => []];

        foreach ($results as $row) {
            if ($row['account_type'] === 'Asset') {
                $assets += $row['balance'];
                $data['assets'][] = $row;
            } elseif ($row['account_type'] === 'Liability') {
                $liabilities += abs($row['balance']);
                $data['liabilities'][] = $row;
            } else {
                $equity += abs($row['balance']);
                $data['equity'][] = $row;
            }
        }

        $data['total_assets'] = $assets;
        $data['total_liabilities'] = $liabilities;
        $data['total_equity'] = $equity;

        return $data;
    }

    /**
     * Get key financial performance indicators (KPIs)
     * For: Dashboard BI
     */
    public function getFinancialHealth(string $tenantId, string $companyCode, string $finPeriod): array
    {
        $bs = $this->getBalanceSheet($tenantId, $companyCode, $finPeriod);
        $pl = $this->getProfitLoss($tenantId, $companyCode, substr($finPeriod, 0, 4) . '01', $finPeriod);

        $liquidity = $bs['total_liabilities'] > 0 ? $bs['total_assets'] / $bs['total_liabilities'] : $bs['total_assets'];
        $margin = $pl['total_income'] > 0 ? ($pl['net_profit'] / $pl['total_income']) * 100 : 0;

        return [
            'liquidity_ratio' => round($liquidity, 2),
            'profit_margin' => round($margin, 2),
            'net_profit_ytd' => $pl['net_profit'],
            'total_assets' => $bs['total_assets'],
            'total_liabilities' => $bs['total_liabilities'],
            'total_equity' => $bs['total_equity'],
            'status' => $liquidity > 1.5 ? 'Healthy' : ($liquidity > 1.0 ? 'Stable' : 'Risk')
        ];
    }
}
