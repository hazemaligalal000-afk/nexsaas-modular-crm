<?php
/**
 * Accounting/PayrollService.php
 * 
 * BATCH H — Automated Payroll Run
 * Calculates monthly salaries and generates payroll vouchers.
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseService;

class PayrollService extends BaseService
{
    private JournalEntryService $jeService;
    private $db;

    public function __construct($db, JournalEntryService $jeService)
    {
        $this->db = $db;
        $this->jeService = $jeService;
    }

    /**
     * Run payroll for a company/period
     */
    public function processPayroll(string $tenantId, string $companyCode, string $finPeriod, int $userId): array
    {
        // 1. Fetch employees
        $sql = "SELECT employee_no, name_en, name_ar, basic_salary, housing_allowance, transport_allowance, social_insurance_pct, tax_pct 
                FROM hr_employees 
                WHERE tenant_id = ? AND company_code = ? AND status = 'active' AND deleted_at IS NULL";
        
        $employees = $this->db->GetAll($sql, [$tenantId, $companyCode]);

        if (empty($employees)) {
            return [
                'processed' => false,
                'message' => 'No active employees found for company: ' . $companyCode
            ];
        }

        $lines = [];
        $totalNetPayroll = 0;
        $totalGrossExpense = 0;

        foreach ($employees as $emp) {
            $gross = $emp['basic_salary'] + $emp['housing_allowance'] + $emp['transport_allowance'];
            $deductions = ($gross * ($emp['social_insurance_pct'] / 100)) + ($gross * ($emp['tax_pct'] / 100));
            $net = $gross - $deductions;

            $totalNetPayroll += $net;
            $totalGrossExpense += $gross;

            // Debit: Salaries Expense (5.1.1)
            $lines[] = [
                'account_code' => '5.1.1',
                'dr_value' => $gross,
                'cr_value' => 0,
                'line_desc' => 'GROSS SALARY: ' . ($emp['name_en'] ?? $emp['name_ar']) . ' (' . $emp['employee_no'] . ')',
                'employee_no' => $emp['employee_no'],
                'cost_center_code' => 'ADMIN' // Default to Admin, would ideally be from employee record
            ];
        }

        // Credit: Salaries Payable (2.1.2)
        $lines[] = [
            'account_code' => '2.1.2',
            'dr_value' => 0,
            'cr_value' => $totalNetPayroll,
            'line_desc' => 'TOTAL PAYROLL PAYABLE - ' . $finPeriod,
        ];

        // Credit: Statutory Deductions (2.1.3 - Social Insurance, etc.)
        $lines[] = [
            'account_code' => '2.1.3',
            'dr_value' => 0,
            'cr_value' => $totalGrossExpense - $totalNetPayroll,
            'line_desc' => 'STATUTORY DEDUCTIONS - ' . $finPeriod,
        ];

        // Create Voucher Type 2 (Expenses)
        $header = [
            'company_code' => $companyCode,
            'voucher_code' => '2',
            'section_code' => '02', // Admin Expenses
            'voucher_date' => date('Y-m-d'),
            'fin_period' => $finPeriod,
            'currency_code' => '01',
            'exchange_rate' => 1.0,
            'description' => 'AUTO-GENERATED: PAYROLL RUN - ' . $finPeriod,
            'status' => 'draft',
            'created_by' => $userId
        ];

        $id = $this->jeService->create($header, $lines, $userId);

        return [
            'processed' => true,
            'je_id' => $id,
            'employee_count' => count($employees),
            'total_gross' => $totalGrossExpense,
            'total_net' => $totalNetPayroll
        ];
    }
}
