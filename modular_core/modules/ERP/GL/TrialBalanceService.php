<?php
/**
 * ERP/GL/TrialBalanceService.php
 *
 * Trial Balance Service generating debit/credit/net per account
 * per Company_Code per Fin_Period
 *
 * Requirements: 18.10
 */

declare(strict_types=1);

namespace Modules\ERP\GL;

use Core\BaseModel;

class TrialBalanceService
{
    private BaseModel $model;

    public function __construct(BaseModel $model)
    {
        $this->model = $model;
    }

    /**
     * Generate trial balance for a company and period
     *
     * @param string $companyCode Two-digit company code (01-06)
     * @param string $finPeriod   Financial period in YYYYMM format
     * @param string|null $currencyCode Optional currency filter (01-06)
     *
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function generate(string $companyCode, string $finPeriod, ?string $currencyCode = null): array
    {
        try {
            $sql = "
                SELECT 
                    jel.account_code,
                    coa.account_name_en,
                    coa.account_name_ar,
                    coa.account_type,
                    jel.currency_code,
                    SUM(jel.dr_value) as total_debit,
                    SUM(jel.cr_value) as total_credit,
                    SUM(jel.dr_value) - SUM(jel.cr_value) as net_balance,
                    SUM(jel.dr_value_base) as total_debit_egp,
                    SUM(jel.cr_value_base) as total_credit_egp,
                    SUM(jel.dr_value_base) - SUM(jel.cr_value_base) as net_balance_egp
                FROM journal_entry_lines jel
                INNER JOIN journal_entry_headers jeh ON jel.je_header_id = jeh.id
                LEFT JOIN chart_of_accounts coa ON jel.account_code = coa.account_code 
                    AND jel.company_code = coa.company_code
                    AND jel.tenant_id = coa.tenant_id
                    AND coa.deleted_at IS NULL
                WHERE jel.tenant_id = ?
                    AND jel.company_code = ?
                    AND jel.fin_period = ?
                    AND jeh.status = 'posted'
                    AND jel.deleted_at IS NULL
                    AND jeh.deleted_at IS NULL
            ";

            $params = [
                $this->model->getTenantId(),
                $companyCode,
                $finPeriod
            ];

            if ($currencyCode !== null) {
                $sql .= " AND jel.currency_code = ?";
                $params[] = $currencyCode;
            }

            $sql .= "
                GROUP BY jel.account_code, coa.account_name_en, coa.account_name_ar, 
                         coa.account_type, jel.currency_code
                ORDER BY jel.account_code, jel.currency_code
            ";

            $db = $this->model->getDb();
            $result = $db->Execute($sql, $params);

            if ($result === false) {
                throw new \RuntimeException('Failed to generate trial balance: ' . $db->ErrorMsg());
            }

            $accounts = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;
            $totalDebitEGP = 0.0;
            $totalCreditEGP = 0.0;

            while (!$result->EOF) {
                $row = $result->fields;
                $accounts[] = [
                    'account_code' => $row['account_code'],
                    'account_name_en' => $row['account_name_en'],
                    'account_name_ar' => $row['account_name_ar'],
                    'account_type' => $row['account_type'],
                    'currency_code' => $row['currency_code'],
                    'total_debit' => number_format((float)$row['total_debit'], 2, '.', ''),
                    'total_credit' => number_format((float)$row['total_credit'], 2, '.', ''),
                    'net_balance' => number_format((float)$row['net_balance'], 2, '.', ''),
                    'total_debit_egp' => number_format((float)$row['total_debit_egp'], 2, '.', ''),
                    'total_credit_egp' => number_format((float)$row['total_credit_egp'], 2, '.', ''),
                    'net_balance_egp' => number_format((float)$row['net_balance_egp'], 2, '.', ''),
                ];

                $totalDebitEGP += (float)$row['total_debit_egp'];
                $totalCreditEGP += (float)$row['total_credit_egp'];

                $result->MoveNext();
            }

            return [
                'success' => true,
                'data' => [
                    'company_code' => $companyCode,
                    'fin_period' => $finPeriod,
                    'currency_code' => $currencyCode,
                    'accounts' => $accounts,
                    'totals' => [
                        'total_debit_egp' => number_format($totalDebitEGP, 2, '.', ''),
                        'total_credit_egp' => number_format($totalCreditEGP, 2, '.', ''),
                        'difference' => number_format(abs($totalDebitEGP - $totalCreditEGP), 2, '.', ''),
                    ]
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
}
