<?php
/**
 * ERP/GL/FinancialStatementService.php
 *
 * Financial Statement Service generating:
 * - P&L Statement: Income - Cost - Expenses
 * - Balance Sheet: Assets = Liabilities + Equity
 * - Cash Flow Statement: direct method from bank movements
 *
 * Requirements: 18.10
 */

declare(strict_types=1);

namespace Modules\ERP\GL;

use Core\BaseModel;

class FinancialStatementService
{
    private BaseModel $model;

    public function __construct(BaseModel $model)
    {
        $this->model = $model;
    }

    /**
     * Generate Profit & Loss Statement
     * Formula: Net Income = Income - Cost - Expenses
     *
     * @param string $companyCode Two-digit company code (01-06)
     * @param string $finPeriod   Financial period in YYYYMM format
     *
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function generateProfitAndLoss(string $companyCode, string $finPeriod): array
    {
        try {
            $sql = "
                SELECT 
                    coa.account_type,
                    SUM(jel.cr_value_base - jel.dr_value_base) as amount_egp
                FROM journal_entry_lines jel
                INNER JOIN journal_entry_headers jeh ON jel.je_header_id = jeh.id
                INNER JOIN chart_of_accounts coa ON jel.account_code = coa.account_code 
                    AND jel.company_code = coa.company_code
                    AND jel.tenant_id = coa.tenant_id
                    AND coa.deleted_at IS NULL
                WHERE jel.tenant_id = ?
                    AND jel.company_code = ?
                    AND jel.fin_period = ?
                    AND jeh.status = 'posted'
                    AND coa.account_type IN ('Income', 'Expense', 'Cost')
                    AND jel.deleted_at IS NULL
                    AND jeh.deleted_at IS NULL
                GROUP BY coa.account_type
            ";

            $db = $this->model->getDb();
            $result = $db->Execute($sql, [
                $this->model->getTenantId(),
                $companyCode,
                $finPeriod
            ]);

            if ($result === false) {
                throw new \RuntimeException('Failed to generate P&L: ' . $db->ErrorMsg());
            }

            $income = 0.0;
            $cost = 0.0;
            $expense = 0.0;

            while (!$result->EOF) {
                $row = $result->fields;
                $amount = (float)$row['amount_egp'];

                switch ($row['account_type']) {
                    case 'Income':
                        $income += $amount;
                        break;
                    case 'Cost':
                        $cost += $amount;
                        break;
                    case 'Expense':
                        $expense += $amount;
                        break;
                }

                $result->MoveNext();
            }

            $grossProfit = $income - $cost;
            $netIncome = $grossProfit - $expense;

            return [
                'success' => true,
                'data' => [
                    'company_code' => $companyCode,
                    'fin_period' => $finPeriod,
                    'statement_type' => 'profit_and_loss',
                    'income' => number_format($income, 2, '.', ''),
                    'cost_of_sales' => number_format($cost, 2, '.', ''),
                    'gross_profit' => number_format($grossProfit, 2, '.', ''),
                    'expenses' => number_format($expense, 2, '.', ''),
                    'net_income' => number_format($netIncome, 2, '.', ''),
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to generate P&L statement: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate Balance Sheet
     * Formula: Assets = Liabilities + Equity
     *
     * @param string $companyCode Two-digit company code (01-06)
     * @param string $finPeriod   Financial period in YYYYMM format
     *
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function generateBalanceSheet(string $companyCode, string $finPeriod): array
    {
        try {
            $sql = "
                SELECT 
                    coa.account_type,
                    SUM(jel.dr_value_base - jel.cr_value_base) as amount_egp
                FROM journal_entry_lines jel
                INNER JOIN journal_entry_headers jeh ON jel.je_header_id = jeh.id
                INNER JOIN chart_of_accounts coa ON jel.account_code = coa.account_code 
                    AND jel.company_code = coa.company_code
                    AND jel.tenant_id = coa.tenant_id
                    AND coa.deleted_at IS NULL
                WHERE jel.tenant_id = ?
                    AND jel.company_code = ?
                    AND jel.fin_period <= ?
                    AND jeh.status = 'posted'
                    AND coa.account_type IN ('Asset', 'Liability', 'Equity')
                    AND jel.deleted_at IS NULL
                    AND jeh.deleted_at IS NULL
                GROUP BY coa.account_type
            ";

            $db = $this->model->getDb();
            $result = $db->Execute($sql, [
                $this->model->getTenantId(),
                $companyCode,
                $finPeriod
            ]);

            if ($result === false) {
                throw new \RuntimeException('Failed to generate balance sheet: ' . $db->ErrorMsg());
            }

            $assets = 0.0;
            $liabilities = 0.0;
            $equity = 0.0;

            while (!$result->EOF) {
                $row = $result->fields;
                $amount = (float)$row['amount_egp'];

                switch ($row['account_type']) {
                    case 'Asset':
                        $assets += $amount;
                        break;
                    case 'Liability':
                        $liabilities += $amount;
                        break;
                    case 'Equity':
                        $equity += $amount;
                        break;
                }

                $result->MoveNext();
            }

            $totalLiabilitiesAndEquity = $liabilities + $equity;
            $difference = $assets - $totalLiabilitiesAndEquity;

            return [
                'success' => true,
                'data' => [
                    'company_code' => $companyCode,
                    'fin_period' => $finPeriod,
                    'statement_type' => 'balance_sheet',
                    'assets' => number_format($assets, 2, '.', ''),
                    'liabilities' => number_format($liabilities, 2, '.', ''),
                    'equity' => number_format($equity, 2, '.', ''),
                    'total_liabilities_and_equity' => number_format($totalLiabilitiesAndEquity, 2, '.', ''),
                    'difference' => number_format($difference, 2, '.', ''),
                    'balanced' => abs($difference) < 0.01,
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to generate balance sheet: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate Cash Flow Statement (direct method)
     * Based on bank account movements
     *
     * @param string $companyCode Two-digit company code (01-06)
     * @param string $finPeriod   Financial period in YYYYMM format
     *
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function generateCashFlow(string $companyCode, string $finPeriod): array
    {
        try {
            // Get bank account movements (accounts with subtype 'Bank')
            $sql = "
                SELECT 
                    coa.account_subtype,
                    SUM(jel.dr_value_base) as cash_inflow,
                    SUM(jel.cr_value_base) as cash_outflow,
                    SUM(jel.dr_value_base - jel.cr_value_base) as net_cash_flow
                FROM journal_entry_lines jel
                INNER JOIN journal_entry_headers jeh ON jel.je_header_id = jeh.id
                INNER JOIN chart_of_accounts coa ON jel.account_code = coa.account_code 
                    AND jel.company_code = coa.company_code
                    AND jel.tenant_id = coa.tenant_id
                    AND coa.deleted_at IS NULL
                WHERE jel.tenant_id = ?
                    AND jel.company_code = ?
                    AND jel.fin_period = ?
                    AND jeh.status = 'posted'
                    AND coa.account_subtype IN ('Bank', 'Cash')
                    AND jel.deleted_at IS NULL
                    AND jeh.deleted_at IS NULL
                GROUP BY coa.account_subtype
            ";

            $db = $this->model->getDb();
            $result = $db->Execute($sql, [
                $this->model->getTenantId(),
                $companyCode,
                $finPeriod
            ]);

            if ($result === false) {
                throw new \RuntimeException('Failed to generate cash flow: ' . $db->ErrorMsg());
            }

            $cashInflow = 0.0;
            $cashOutflow = 0.0;

            while (!$result->EOF) {
                $row = $result->fields;
                $cashInflow += (float)$row['cash_inflow'];
                $cashOutflow += (float)$row['cash_outflow'];
                $result->MoveNext();
            }

            $netCashFlow = $cashInflow - $cashOutflow;

            return [
                'success' => true,
                'data' => [
                    'company_code' => $companyCode,
                    'fin_period' => $finPeriod,
                    'statement_type' => 'cash_flow',
                    'method' => 'direct',
                    'cash_inflow' => number_format($cashInflow, 2, '.', ''),
                    'cash_outflow' => number_format($cashOutflow, 2, '.', ''),
                    'net_cash_flow' => number_format($netCashFlow, 2, '.', ''),
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to generate cash flow statement: ' . $e->getMessage()
            ];
        }
    }
}
