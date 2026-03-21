<?php
/**
 * Accounting/TaxService.php
 * 
 * BATCH H+ — Tax Compliance (GCC/Egypt)
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseService;

class TaxService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get VAT Summary (EGY 14% / GCC 15%) for a period
     * Rule: Input VAT (Purchases) - Output VAT (Sales)
     */
    public function getVatReport(string $tenantId, string $companyCode, string $startPeriod, string $endPeriod): array
    {
        $sql = "
            SELECT 
                coa.account_name_en, 
                coa.account_code,
                SUM(jel.dr_value_base) as input_vat,
                SUM(jel.cr_value_base) as output_vat
            FROM journal_entry_lines jel
            JOIN chart_of_accounts coa 
                ON jel.account_code = coa.account_code 
                AND jel.tenant_id = coa.tenant_id
            WHERE coa.tenant_id = ? 
              AND coa.company_code = ? 
              AND (coa.account_code LIKE '1.1.9%' OR coa.account_code LIKE '2.1.9%') -- VAT Accounts (Standard structure)
              AND jel.fin_period BETWEEN ? AND ?
              AND jel.deleted_at IS NULL
            GROUP BY coa.id
        ";
        
        $results = $this->db->GetAll($sql, [$tenantId, $companyCode, $startPeriod, $endPeriod]);

        $totalInput = 0;
        $totalOutput = 0;

        foreach ($results as $row) {
            $totalInput += $row['input_vat'];
            $totalOutput += $row['output_vat'];
        }

        return [
            'period' => $startPeriod . ' - ' . $endPeriod,
            'details' => $results,
            'total_input_vat' => $totalInput,
            'total_output_vat' => $totalOutput,
            'vat_payable_refundable' => $totalOutput - $totalInput,
            'status' => ($totalOutput - $totalInput) >= 0 ? 'Payable' : 'Refundable'
        ];
    }

    /**
     * Get Withholding Tax (WHT) Summary (EGY 1%)
     */
    public function getWhtReport(string $tenantId, string $companyCode, string $finPeriod): array
    {
        // Rule: Usually 1% on supply, 3% on services, 5% on consulting (Egypt)
        $sql = "SELECT account_code, partner_no, SUM(cr_value_base) as wht_accrued
                FROM journal_entry_lines 
                WHERE tenant_id = ? AND company_code = ? 
                  AND account_code LIKE '2.1.8%' -- WHT Accounts
                  AND fin_period = ? AND deleted_at IS NULL
                GROUP BY account_code, partner_no";
        
        $results = $this->db->GetAll($sql, [$tenantId, $companyCode, $finPeriod]);

        return [
            'fin_period' => $finPeriod,
            'total_wht' => array_sum(array_column($results, 'wht_accrued')),
            'details' => $results
        ];
    }
}
