<?php
/**
 * ERP/GL/JournalEntryService.php
 *
 * Journal Entry Service implementing double-entry bookkeeping with:
 * - Balance validation (Σ Dr = Σ Cr)
 * - Period validation (open/closed check)
 * - Auto-assignment of voucher_code and section_code
 * - Multi-currency support with EGP conversion
 * - All 35 fields per line
 * - Company code isolation
 *
 * Requirements: 18.2, 18.3, 18.5, 18.6, 18.7, 18.9, 18.12
 */

declare(strict_types=1);

namespace Modules\ERP\GL;

use Core\BaseModel;

class JournalEntryService
{
    private BaseModel $model;

    /**
     * Currency code to voucher code mapping
     * Requirements: 18.5, 18.6, 18.7
     */
    private const CURRENCY_TO_VOUCHER = [
        '01' => '1',  // EGP
        '02' => '2',  // USD
        '03' => '3',  // AED
        '04' => '4',  // SAR
        '05' => '5',  // EUR
        '06' => '6',  // GBP
    ];

    /**
     * Section codes for income/expense
     */
    private const SECTION_INCOME = '01';
    private const SECTION_EXPENSE = '02';

    /**
     * Settlement voucher code and section codes
     */
    private const VOUCHER_SETTLEMENT = '999';
    private const SETTLEMENT_SECTIONS = ['991', '992', '993', '994', '995', '996'];

    public function __construct(BaseModel $model)
    {
        $this->model = $model;
    }

    /**
     * Post a journal entry with full validation and auto-assignment
     *
     * Requirements: 18.2, 18.3, 18.5, 18.6, 18.7, 18.9, 18.12
     *
     * @param array $entry {
     *   company_code: string (required),
     *   fin_period: string (required, YYYYMM),
     *   voucher_date: string (required, YYYY-MM-DD),
     *   service_date: string (optional, YYYYMM),
     *   currency_code: string (required, 01-06),
     *   exchange_rate: float (optional, defaults to 1.0 for EGP),
     *   description: string (optional),
     *   lines: array of line items (required, min 2)
     * }
     *
     * Each line: {
     *   area_code: string (optional),
     *   account_code: string (required),
     *   cost_center_code: string (optional),
     *   vendor_code: string (optional),
     *   check_transfer_no: string (optional),
     *   dr_value: float (required, >= 0),
     *   cr_value: float (required, >= 0),
     *   line_desc: string (optional),
     *   asset_no: string (optional),
     *   transaction_no: string (optional),
     *   customer_invoice_no: string (optional),
     *   internal_invoice_no: string (optional),
     *   employee_no: string (optional),
     *   partner_no: string (optional),
     *   vendor_word_count: int (optional),
     *   translator_word_count: int (optional),
     *   agent_name: string (optional),
     *   ... (all 35 fields supported)
     * }
     *
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function post(array $entry): array
    {
        // Validate required fields
        $validation = $this->validateEntry($entry);
        if (!$validation['success']) {
            return $validation;
        }

        // Requirement 18.9: Require explicit company_code filter
        if (empty($entry['company_code'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'company_code is required for all journal entries (Req 18.9)'
            ];
        }

        // Validate period is open (Requirement 18.12)
        $periodCheck = $this->validatePeriodOpen(
            $entry['company_code'],
            $entry['fin_period']
        );
        if (!$periodCheck['success']) {
            return $periodCheck;
        }

        // Validate double-entry balance (Requirements 18.2, 18.3)
        $balanceCheck = $this->validateBalance($entry['lines']);
        if (!$balanceCheck['success']) {
            return $balanceCheck;
        }

        // Auto-assign voucher_code and section_code (Requirements 18.5, 18.6, 18.7)
        $codeAssignment = $this->assignVoucherAndSectionCodes($entry);
        if (!$codeAssignment['success']) {
            return $codeAssignment;
        }

        $voucherCode = $codeAssignment['data']['voucher_code'];
        $sectionCode = $codeAssignment['data']['section_code'];

        // Convert amounts to EGP (Requirement 18.12)
        $exchangeRate = $entry['exchange_rate'] ?? 1.0;
        $linesWithEGP = $this->convertToEGP($entry['lines'], $exchangeRate);

        // Calculate totals
        $totals = $this->calculateTotals($linesWithEGP);

        // Begin transaction
        $db = $this->model->getDb();
        $db->StartTrans();

        try {
            // Get next voucher number for this company/period
            $voucherNo = $this->getNextVoucherNumber(
                $entry['company_code'],
                $entry['fin_period']
            );

            // Insert header
            $headerData = [
                'company_code' => $entry['company_code'],
                'voucher_no' => $voucherNo,
                'voucher_code' => $voucherCode,
                'section_code' => $sectionCode,
                'voucher_date' => $entry['voucher_date'],
                'fin_period' => $entry['fin_period'],
                'service_date' => $entry['service_date'] ?? null,
                'currency_code' => $entry['currency_code'],
                'exchange_rate' => $exchangeRate,
                'total_dr' => $totals['total_dr'],
                'total_cr' => $totals['total_cr'],
                'total_dr_base' => $totals['total_dr_base'],
                'total_cr_base' => $totals['total_cr_base'],
                'status' => 'draft',
                'description' => $entry['description'] ?? null,
                'created_by' => $entry['created_by'] ?? null,
            ];

            $headerId = $this->insertHeader($headerData);

            // Insert lines with all 35 fields
            $lineNo = 1;
            foreach ($linesWithEGP as $line) {
                $lineData = [
                    'je_header_id' => $headerId,
                    'company_code' => $entry['company_code'],
                    'area_code' => $line['area_code'] ?? null,
                    'area_desc' => $line['area_desc'] ?? null,
                    'fin_period' => $entry['fin_period'],
                    'voucher_date' => $entry['voucher_date'],
                    'service_date' => $entry['service_date'] ?? null,
                    'voucher_no' => $voucherNo,
                    'section_code' => $sectionCode,
                    'voucher_sub' => $line['voucher_sub'] ?? null,
                    'line_no' => $lineNo++,
                    'account_code' => $line['account_code'],
                    'account_desc' => $line['account_desc'] ?? null,
                    'cost_identifier' => $line['cost_identifier'] ?? null,
                    'cost_center_code' => $line['cost_center_code'] ?? null,
                    'cost_center_name' => $line['cost_center_name'] ?? null,
                    'vendor_code' => $line['vendor_code'] ?? null,
                    'vendor_name' => $line['vendor_name'] ?? null,
                    'check_transfer_no' => $line['check_transfer_no'] ?? null,
                    'exchange_rate' => $exchangeRate,
                    'currency_code' => $entry['currency_code'],
                    'dr_value' => $line['dr_value'],
                    'cr_value' => $line['cr_value'],
                    'dr_value_base' => $line['dr_value_base'],
                    'cr_value_base' => $line['cr_value_base'],
                    'line_desc' => $line['line_desc'] ?? null,
                    'asset_no' => $line['asset_no'] ?? null,
                    'transaction_no' => $line['transaction_no'] ?? null,
                    'profit_loss_flag' => $line['profit_loss_flag'] ?? null,
                    'customer_invoice_no' => $line['customer_invoice_no'] ?? null,
                    'income_stmt_flag' => $line['income_stmt_flag'] ?? null,
                    'internal_invoice_no' => $line['internal_invoice_no'] ?? null,
                    'employee_no' => $line['employee_no'] ?? null,
                    'partner_no' => $line['partner_no'] ?? null,
                    'vendor_word_count' => $line['vendor_word_count'] ?? 0,
                    'translator_word_count' => $line['translator_word_count'] ?? 0,
                    'agent_name' => $line['agent_name'] ?? null,
                    'created_by' => $entry['created_by'] ?? null,
                ];

                $this->insertLine($lineData);
            }

            // Commit transaction
            $db->CompleteTrans();

            return [
                'success' => true,
                'data' => [
                    'je_header_id' => $headerId,
                    'voucher_no' => $voucherNo,
                    'voucher_code' => $voucherCode,
                    'section_code' => $sectionCode,
                    'company_code' => $entry['company_code'],
                    'fin_period' => $entry['fin_period'],
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            $db->RollbackTrans();
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to post journal entry: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate entry structure and required fields
     */
    private function validateEntry(array $entry): array
    {
        $required = ['company_code', 'fin_period', 'voucher_date', 'currency_code', 'lines'];
        foreach ($required as $field) {
            if (empty($entry[$field])) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Missing required field: {$field}"
                ];
            }
        }

        if (!is_array($entry['lines']) || count($entry['lines']) < 2) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Journal entry must have at least 2 lines'
            ];
        }

        // Validate fin_period format (YYYYMM)
        if (!preg_match('/^\d{6}$/', $entry['fin_period'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'fin_period must be in YYYYMM format'
            ];
        }

        // Validate currency_code
        if (!isset(self::CURRENCY_TO_VOUCHER[$entry['currency_code']])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Invalid currency_code. Must be 01-06'
            ];
        }

        return ['success' => true, 'data' => null, 'error' => null];
    }

    /**
     * Validate period is open for posting
     * Requirement 18.12
     */
    private function validatePeriodOpen(string $companyCode, string $finPeriod): array
    {
        $sql = "SELECT status FROM financial_periods 
                WHERE tenant_id = ? AND company_code = ? AND period_code = ? AND deleted_at IS NULL";

        $db = $this->model->getDb();
        $result = $db->Execute($sql, [
            $this->model->getTenantId(),
            $companyCode,
            $finPeriod
        ]);

        if (!$result || $result->EOF) {
            return [
                'success' => false,
                'data' => null,
                'error' => "Financial period {$finPeriod} not found for company {$companyCode}"
            ];
        }

        $status = $result->fields['status'];
        if ($status !== 'open') {
            return [
                'success' => false,
                'data' => null,
                'error' => "Financial period {$finPeriod} is {$status} for company {$companyCode}. Cannot post entries."
            ];
        }

        return ['success' => true, 'data' => null, 'error' => null];
    }

    /**
     * Validate double-entry balance: Σ Dr = Σ Cr
     * Requirements: 18.2, 18.3
     */
    private function validateBalance(array $lines): array
    {
        $totalDr = 0.0;
        $totalCr = 0.0;

        foreach ($lines as $line) {
            if (!isset($line['account_code'])) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Each line must have an account_code'
                ];
            }

            $dr = (float)($line['dr_value'] ?? 0);
            $cr = (float)($line['cr_value'] ?? 0);

            // Each line must be either Dr OR Cr, not both
            if ($dr > 0 && $cr > 0) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Each line must be either debit OR credit, not both'
                ];
            }

            $totalDr += $dr;
            $totalCr += $cr;
        }

        // Check balance with tolerance for floating point precision
        $diff = abs($totalDr - $totalCr);
        if ($diff > 0.01) {
            return [
                'success' => false,
                'data' => null,
                'error' => sprintf(
                    'Journal entry is unbalanced: Total Dr (%.2f) ≠ Total Cr (%.2f). Difference: %.2f',
                    $totalDr,
                    $totalCr,
                    $diff
                )
            ];
        }

        return ['success' => true, 'data' => null, 'error' => null];
    }

    /**
     * Auto-assign voucher_code and section_code based on currency
     * Requirements: 18.5, 18.6, 18.7
     */
    private function assignVoucherAndSectionCodes(array $entry): array
    {
        $currencyCode = $entry['currency_code'];

        // Check if this is a settlement voucher (explicit override)
        if (isset($entry['voucher_code']) && $entry['voucher_code'] === self::VOUCHER_SETTLEMENT) {
            // Settlement vouchers must use section codes 991-996
            $sectionCode = $entry['section_code'] ?? null;
            if (!in_array($sectionCode, self::SETTLEMENT_SECTIONS, true)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Settlement voucher (999) must use section codes 991-996'
                ];
            }

            // Settlement vouchers cannot use section codes 01 or 02
            if ($sectionCode === self::SECTION_INCOME || $sectionCode === self::SECTION_EXPENSE) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Settlement voucher (999) cannot use section codes 01 or 02 (Req 18.7)'
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'voucher_code' => self::VOUCHER_SETTLEMENT,
                    'section_code' => $sectionCode
                ],
                'error' => null
            ];
        }

        // Auto-assign voucher code based on currency
        $voucherCode = self::CURRENCY_TO_VOUCHER[$currencyCode];

        // Auto-assign section code (default to expense, can be overridden)
        $sectionCode = $entry['section_code'] ?? self::SECTION_EXPENSE;

        // Validate section code for non-settlement vouchers
        if (!in_array($sectionCode, [self::SECTION_INCOME, self::SECTION_EXPENSE], true)) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Non-settlement vouchers must use section code 01 (Income) or 02 (Expense)'
            ];
        }

        return [
            'success' => true,
            'data' => [
                'voucher_code' => $voucherCode,
                'section_code' => $sectionCode
            ],
            'error' => null
        ];
    }

    /**
     * Convert amounts to EGP using exchange rate
     * Requirement 18.12
     */
    private function convertToEGP(array $lines, float $exchangeRate): array
    {
        $converted = [];
        foreach ($lines as $line) {
            $line['dr_value_base'] = round($line['dr_value'] * $exchangeRate, 2);
            $line['cr_value_base'] = round($line['cr_value'] * $exchangeRate, 2);
            $converted[] = $line;
        }
        return $converted;
    }

    /**
     * Calculate totals for header
     */
    private function calculateTotals(array $lines): array
    {
        $totalDr = 0.0;
        $totalCr = 0.0;
        $totalDrBase = 0.0;
        $totalCrBase = 0.0;

        foreach ($lines as $line) {
            $totalDr += $line['dr_value'];
            $totalCr += $line['cr_value'];
            $totalDrBase += $line['dr_value_base'];
            $totalCrBase += $line['cr_value_base'];
        }

        return [
            'total_dr' => round($totalDr, 2),
            'total_cr' => round($totalCr, 2),
            'total_dr_base' => round($totalDrBase, 2),
            'total_cr_base' => round($totalCrBase, 2),
        ];
    }

    /**
     * Get next voucher number for company/period
     */
    private function getNextVoucherNumber(string $companyCode, string $finPeriod): int
    {
        $sql = "SELECT COALESCE(MAX(voucher_no), 0) + 1 as next_no 
                FROM journal_entry_headers 
                WHERE tenant_id = ? AND company_code = ? AND fin_period = ? AND deleted_at IS NULL";

        $db = $this->model->getDb();
        $result = $db->Execute($sql, [
            $this->model->getTenantId(),
            $companyCode,
            $finPeriod
        ]);

        if (!$result || $result->EOF) {
            return 1;
        }

        return (int)$result->fields['next_no'];
    }

    /**
     * Insert journal entry header
     */
    private function insertHeader(array $data): int
    {
        $data['tenant_id'] = $this->model->getTenantId();

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);

        $sql = sprintf(
            "INSERT INTO journal_entry_headers (%s) VALUES (%s) RETURNING id",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $db = $this->model->getDb();
        $result = $db->Execute($sql, $values);

        if (!$result || $result->EOF) {
            throw new \RuntimeException('Failed to insert journal entry header');
        }

        return (int)$result->fields['id'];
    }

    /**
     * Insert journal entry line
     */
    private function insertLine(array $data): void
    {
        $data['tenant_id'] = $this->model->getTenantId();

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);

        $sql = sprintf(
            "INSERT INTO journal_entry_lines (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $db = $this->model->getDb();
        $result = $db->Execute($sql, $values);

        if ($result === false) {
            throw new \RuntimeException('Failed to insert journal entry line: ' . $db->ErrorMsg());
        }
    }
}
