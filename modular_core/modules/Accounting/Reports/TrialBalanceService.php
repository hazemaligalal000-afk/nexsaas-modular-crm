<?php
/**
 * Accounting/Reports/TrialBalanceService.php
 *
 * Multi-Currency Trial Balance Service
 * Task 30.8: Implement multi-currency trial balance showing debit/credit/net in transaction currency and EGP
 *
 * Requirements: 47.11
 */

declare(strict_types=1);

namespace Modules\Accounting\Reports;

use Core\BaseModel;

class TrialBalanceService
{
    private BaseModel $model;
    private string $tenantId;
    private $db;

    public function __construct(BaseModel $model, string $tenantId)
    {
        $this->model = $model;
        $this->tenantId = $tenantId;
        $this->db = $model->getDb();
    }

    /**
     * Generate multi-currency trial balance
     * Task 30.8: Requirements 47.11
     *
     * Shows debit, credit, and net columns in both transaction currency and EGP equivalent
     *
     * @param string $companyCode Company code
     * @param string $finPeriod Financial period (YYYYMM)
     * @param string|null $currencyCode Optional currency filter (01-06)
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function generateMultiCurrencyTrialBalance(
        string $companyCode,
        string $finPeriod,
        ?string $currencyCode = null
    ): array {
        try {
            $currencyFilter = $currencyCode ? 'AND jeh.currency_code = ?' : '';
            $params = [$this->tenantId, $companyCode, $finPeriod];
            if ($currencyCode) {
                $params[] = $currencyCode;
            }

            // Query to aggregate balances by account and currency
            $sql = "
                SELECT 
                    jel.account_code,
                    coa.account_desc,
                    coa.account_desc_ar,
                    coa.account_type,
                    jeh.currency_code,
                    c.currency_name,
                    c.currency_name_ar,
                    SUM(jel.dr_value) as total_dr,
                    SUM(jel.cr_value) as total_cr,
                    SUM(jel.dr_value) - SUM(jel.cr_value) as net_balance,
                    SUM(jel.dr_value_base) as total_dr_egp,
                    SUM(jel.cr_value_base) as total_cr_egp,
                    SUM(jel.dr_value_base) - SUM(jel.cr_value_base) as net_balance_egp
                FROM journal_entry_lines jel
                JOIN journal_entry_headers jeh ON jel.je_header_id = jeh.id
                LEFT JOIN chart_of_accounts coa ON jel.account_code = coa.account_code 
                    AND coa.tenant_id = jeh.tenant_id 
                    AND coa.company_code = jeh.company_code
                LEFT JOIN currencies c ON jeh.currency_code = c.code
                WHERE jeh.tenant_id = ?
                  AND jeh.company_code = ?
                  AND jeh.fin_period = ?
                  AND jeh.status = 'posted'
                  {$currencyFilter}
                  AND jeh.deleted_at IS NULL
                  AND jel.deleted_at IS NULL
                GROUP BY 
                    jel.account_code,
                    coa.account_desc,
                    coa.account_desc_ar,
                    coa.account_type,
                    jeh.currency_code,
                    c.currency_name,
                    c.currency_name_ar
                ORDER BY 
                    jel.account_code,
                    jeh.currency_code
            ";

            $result = $this->db->Execute($sql, $params);

            $balances = [];
            $totals = [
                'total_dr' => 0,
                'total_cr' => 0,
                'net_balance' => 0,
                'total_dr_egp' => 0,
                'total_cr_egp' => 0,
                'net_balance_egp' => 0,
            ];

            while ($result && !$result->EOF) {
                $row = [
                    'account_code' => $result->fields['account_code'],
                    'account_desc' => $result->fields['account_desc'],
                    'account_desc_ar' => $result->fields['account_desc_ar'],
                    'account_type' => $result->fields['account_type'],
                    'currency_code' => $result->fields['currency_code'],
                    'currency_name' => $result->fields['currency_name'],
                    'currency_name_ar' => $result->fields['currency_name_ar'],
                    'total_dr' => round((float)$result->fields['total_dr'], 2),
                    'total_cr' => round((float)$result->fields['total_cr'], 2),
                    'net_balance' => round((float)$result->fields['net_balance'], 2),
                    'total_dr_egp' => round((float)$result->fields['total_dr_egp'], 2),
                    'total_cr_egp' => round((float)$result->fields['total_cr_egp'], 2),
                    'net_balance_egp' => round((float)$result->fields['net_balance_egp'], 2),
                ];

                $balances[] = $row;

                // Accumulate totals (EGP only for grand total)
                $totals['total_dr_egp'] += $row['total_dr_egp'];
                $totals['total_cr_egp'] += $row['total_cr_egp'];
                $totals['net_balance_egp'] += $row['net_balance_egp'];

                $result->MoveNext();
            }

            // Round totals
            $totals['total_dr_egp'] = round($totals['total_dr_egp'], 2);
            $totals['total_cr_egp'] = round($totals['total_cr_egp'], 2);
            $totals['net_balance_egp'] = round($totals['net_balance_egp'], 2);

            return [
                'success' => true,
                'data' => [
                    'company_code' => $companyCode,
                    'fin_period' => $finPeriod,
                    'currency_code' => $currencyCode,
                    'balances' => $balances,
                    'totals' => $totals,
                    'generated_at' => date('Y-m-d H:i:s'),
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to generate trial balance: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate trial balance by account type
     *
     * @param string $companyCode Company code
     * @param string $finPeriod Financial period (YYYYMM)
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function generateTrialBalanceByAccountType(
        string $companyCode,
        string $finPeriod
    ): array {
        try {
            $sql = "
                SELECT 
                    coa.account_type,
                    jeh.currency_code,
                    c.currency_name,
                    SUM(jel.dr_value) as total_dr,
                    SUM(jel.cr_value) as total_cr,
                    SUM(jel.dr_value) - SUM(jel.cr_value) as net_balance,
                    SUM(jel.dr_value_base) as total_dr_egp,
                    SUM(jel.cr_value_base) as total_cr_egp,
                    SUM(jel.dr_value_base) - SUM(jel.cr_value_base) as net_balance_egp
                FROM journal_entry_lines jel
                JOIN journal_entry_headers jeh ON jel.je_header_id = jeh.id
                LEFT JOIN chart_of_accounts coa ON jel.account_code = coa.account_code 
                    AND coa.tenant_id = jeh.tenant_id 
                    AND coa.company_code = jeh.company_code
                LEFT JOIN currencies c ON jeh.currency_code = c.code
                WHERE jeh.tenant_id = ?
                  AND jeh.company_code = ?
                  AND jeh.fin_period = ?
                  AND jeh.status = 'posted'
                  AND jeh.deleted_at IS NULL
                  AND jel.deleted_at IS NULL
                GROUP BY 
                    coa.account_type,
                    jeh.currency_code,
                    c.currency_name
                ORDER BY 
                    coa.account_type,
                    jeh.currency_code
            ";

            $result = $this->db->Execute($sql, [$this->tenantId, $companyCode, $finPeriod]);

            $balances = [];
            while ($result && !$result->EOF) {
                $balances[] = [
                    'account_type' => $result->fields['account_type'],
                    'currency_code' => $result->fields['currency_code'],
                    'currency_name' => $result->fields['currency_name'],
                    'total_dr' => round((float)$result->fields['total_dr'], 2),
                    'total_cr' => round((float)$result->fields['total_cr'], 2),
                    'net_balance' => round((float)$result->fields['net_balance'], 2),
                    'total_dr_egp' => round((float)$result->fields['total_dr_egp'], 2),
                    'total_cr_egp' => round((float)$result->fields['total_cr_egp'], 2),
                    'net_balance_egp' => round((float)$result->fields['net_balance_egp'], 2),
                ];
                $result->MoveNext();
            }

            return [
                'success' => true,
                'data' => [
                    'company_code' => $companyCode,
                    'fin_period' => $finPeriod,
                    'balances_by_type' => $balances,
                    'generated_at' => date('Y-m-d H:i:s'),
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to generate trial balance by account type: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Export trial balance to CSV
     *
     * @param string $companyCode Company code
     * @param string $finPeriod Financial period (YYYYMM)
     * @param string|null $currencyCode Optional currency filter
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function exportToCSV(
        string $companyCode,
        string $finPeriod,
        ?string $currencyCode = null
    ): array {
        $result = $this->generateMultiCurrencyTrialBalance($companyCode, $finPeriod, $currencyCode);
        
        if (!$result['success']) {
            return $result;
        }

        try {
            $filename = "trial_balance_{$companyCode}_{$finPeriod}.csv";
            $filepath = sys_get_temp_dir() . '/' . $filename;
            
            $fp = fopen($filepath, 'w');
            
            // Write header
            fputcsv($fp, [
                'Account Code',
                'Account Description',
                'Account Type',
                'Currency',
                'Debit (Transaction Currency)',
                'Credit (Transaction Currency)',
                'Net Balance (Transaction Currency)',
                'Debit (EGP)',
                'Credit (EGP)',
                'Net Balance (EGP)'
            ]);

            // Write data
            foreach ($result['data']['balances'] as $row) {
                fputcsv($fp, [
                    $row['account_code'],
                    $row['account_desc'],
                    $row['account_type'],
                    $row['currency_name'],
                    $row['total_dr'],
                    $row['total_cr'],
                    $row['net_balance'],
                    $row['total_dr_egp'],
                    $row['total_cr_egp'],
                    $row['net_balance_egp'],
                ]);
            }

            // Write totals
            fputcsv($fp, []);
            fputcsv($fp, [
                'TOTAL',
                '',
                '',
                'EGP',
                '',
                '',
                '',
                $result['data']['totals']['total_dr_egp'],
                $result['data']['totals']['total_cr_egp'],
                $result['data']['totals']['net_balance_egp'],
            ]);

            fclose($fp);

            return [
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'filepath' => $filepath
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to export trial balance: ' . $e->getMessage()
            ];
        }
    }
}
