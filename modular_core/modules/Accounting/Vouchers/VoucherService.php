<?php
/**
 * Accounting/Vouchers/VoucherService.php
 *
 * Voucher Service implementing full journal entry and voucher engine
 * Task 30: Batch B - Journal Entry and Voucher Engine
 *
 * Requirements: 46.1-46.20, 47.10, 47.11
 */

declare(strict_types=1);

namespace Modules\Accounting\Vouchers;

use Core\BaseModel;

class VoucherService
{
    private BaseModel $model;
    private string $tenantId;
    private string $companyCode;
    private $db;
    private $redis;

    /**
     * Currency code to voucher code mapping (Req 46.2, 18.5)
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
     * Section codes (Req 46.2, 18.6)
     */
    private const SECTION_INCOME = '01';
    private const SECTION_EXPENSE = '02';

    /**
     * Settlement voucher code and section codes (Req 47.10, 18.7)
     */
    private const VOUCHER_SETTLEMENT = '999';
    private const SETTLEMENT_SECTIONS = ['991', '992', '993', '994', '995', '996'];

    /**
     * Approval workflow states (Req 46.13)
     */
    private const STATUS_DRAFT = 'draft';
    private const STATUS_SUBMITTED = 'submitted';
    private const STATUS_APPROVED = 'approved';
    private const STATUS_POSTED = 'posted';
    private const STATUS_REVERSED = 'reversed';

    public function __construct(BaseModel $model, string $tenantId, string $companyCode, $redis = null)
    {
        $this->model = $model;
        $this->tenantId = $tenantId;
        $this->companyCode = $companyCode;
        $this->db = $model->getDb();
        $this->redis = $redis;
    }

    /**
     * Save voucher with validation, auto-assignment, all 35 fields, word count calculation
     * Task 30.1: Implement VoucherService::save()
     * Requirements: 46.1-46.12, 46.20
     *
     * @param array $data Voucher data with header and lines
     * @param int $userId User ID creating the voucher
     * @param bool $isOpeningBalance Whether this is an opening balance entry (bypasses balance check)
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function save(array $data, int $userId, bool $isOpeningBalance = false): array
    {
        // Validate required fields
        $validation = $this->validateVoucherData($data);
        if (!$validation['success']) {
            return $validation;
        }

        $header = $data['header'];
        $lines = $data['lines'];

        // Requirement 46.3: Validate period is open
        $periodCheck = $this->validatePeriodOpen(
            $header['company_code'] ?? $this->companyCode,
            $header['fin_period']
        );
        if (!$periodCheck['success']) {
            return $periodCheck;
        }

        // Requirement 46.6: Validate double-entry balance (unless opening balance)
        if (!$isOpeningBalance) {
            $balanceCheck = $this->validateBalance($lines);
            if (!$balanceCheck['success']) {
                return $balanceCheck;
            }
        }

        // Requirement 46.2: Auto-assign voucher_code and section_code
        $codeAssignment = $this->assignVoucherAndSectionCodes($header);
        if (!$codeAssignment['success']) {
            return $codeAssignment;
        }

        $voucherCode = $codeAssignment['data']['voucher_code'];
        $sectionCode = $codeAssignment['data']['section_code'];

        // Requirement 46.7: Auto-fill exchange rate from Redis cache
        $exchangeRate = $this->getExchangeRate(
            $header['currency_code'],
            $header['voucher_date'],
            $header['exchange_rate'] ?? null
        );

        // Requirement 46.12: Calculate word count amounts
        $lines = $this->calculateWordCountAmounts($lines);

        // Convert amounts to EGP (base currency)
        $linesWithEGP = $this->convertToEGP($lines, $exchangeRate);

        // Calculate totals
        $totals = $this->calculateTotals($linesWithEGP);

        // Begin transaction
        $this->db->StartTrans();

        try {
            // Get next voucher number
            $voucherNo = $this->getNextVoucherNumber(
                $header['company_code'] ?? $this->companyCode,
                $header['fin_period']
            );

            // Insert header
            $headerData = [
                'tenant_id' => $this->tenantId,
                'company_code' => $header['company_code'] ?? $this->companyCode,
                'voucher_no' => $voucherNo,
                'voucher_code' => $voucherCode,
                'section_code' => $sectionCode,
                'voucher_date' => $header['voucher_date'],
                'fin_period' => $header['fin_period'],
                'service_date' => $header['service_date'] ?? null,
                'currency_code' => $header['currency_code'],
                'exchange_rate' => $exchangeRate,
                'total_dr' => $totals['total_dr'],
                'total_cr' => $totals['total_cr'],
                'total_dr_base' => $totals['total_dr_base'],
                'total_cr_base' => $totals['total_cr_base'],
                'status' => self::STATUS_DRAFT,
                'description' => $header['description'] ?? null,
                'created_by' => (string)$userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $headerId = $this->insertHeader($headerData);

            // Insert lines with all 35 fields
            $lineNo = 1;
            foreach ($linesWithEGP as $line) {
                $lineData = $this->prepareLineData($line, $headerId, $header, $lineNo++, $userId, $exchangeRate);
                $this->insertLine($lineData);
            }

            // Commit transaction
            $this->db->CompleteTrans();

            // Requirement 61.1: Audit record for every manual journal entry modification
            \Core\AuditLogger::log(
                $this->tenantId,
                (string)$userId,
                'VoucherService',
                'CREATED',
                "Voucher {$voucherNo} was created for company " . ($header['company_code'] ?? $this->companyCode),
                (int)$headerId,
                $headerData
            );

            return [
                'success' => true,
                'data' => [
                    'id' => $headerId,
                    'voucher_no' => $voucherNo,
                    'voucher_code' => $voucherCode,
                    'section_code' => $sectionCode,
                    'company_code' => $header['company_code'] ?? $this->companyCode,
                    'fin_period' => $header['fin_period'],
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            $this->db->RollbackTrans();
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to save voucher: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Prepare line data with all 35 fields
     * Requirements: 18.4, 46.1, 46.8-46.11
     */
    private function prepareLineData(
        array $line,
        int $headerId,
        array $header,
        int $lineNo,
        int $userId,
        float $exchangeRate
    ): array {
        return [
            'tenant_id' => $this->tenantId,
            'company_code' => $header['company_code'] ?? $this->companyCode,
            'je_header_id' => $headerId,
            
            // Voucher identification (fields 1-10)
            'area_code' => $line['area_code'] ?? null,
            'area_desc' => $line['area_desc'] ?? null,
            'fin_period' => $header['fin_period'],
            'voucher_date' => $header['voucher_date'],
            'service_date' => $header['service_date'] ?? null,
            'voucher_no' => $header['voucher_no'] ?? 0,
            'section_code' => $header['section_code'] ?? self::SECTION_EXPENSE,
            'voucher_sub' => $line['voucher_sub'] ?? null,
            'line_no' => $lineNo,
            
            // Account and cost allocation (fields 11-16)
            'account_code' => $line['account_code'],
            'account_desc' => $line['account_desc'] ?? null,
            'cost_identifier' => $line['cost_identifier'] ?? null,
            'cost_center_code' => $line['cost_center_code'] ?? null,
            'cost_center_name' => $line['cost_center_name'] ?? null,
            
            // Vendor/Client linkage (fields 17-18)
            'vendor_code' => $line['vendor_code'] ?? null,
            'vendor_name' => $line['vendor_name'] ?? null,
            
            // Banking (field 19)
            'check_transfer_no' => $line['check_transfer_no'] ?? null,
            
            // Currency and amounts (fields 20-25)
            'exchange_rate' => $exchangeRate,
            'currency_code' => $header['currency_code'],
            'dr_value' => $line['dr_value'] ?? 0.00,
            'cr_value' => $line['cr_value'] ?? 0.00,
            'dr_value_base' => $line['dr_value_base'] ?? 0.00,
            'cr_value_base' => $line['cr_value_base'] ?? 0.00,
            
            // Description and references (fields 26-32)
            'line_desc' => $line['line_desc'] ?? null,
            'asset_no' => $line['asset_no'] ?? null,
            'transaction_no' => $line['transaction_no'] ?? null,
            'profit_loss_flag' => $line['profit_loss_flag'] ?? null,
            'customer_invoice_no' => $line['customer_invoice_no'] ?? null,
            'income_stmt_flag' => $line['income_stmt_flag'] ?? null,
            'internal_invoice_no' => $line['internal_invoice_no'] ?? null,
            
            // Employee and partner linkage (fields 33-34)
            'employee_no' => $line['employee_no'] ?? null,
            'partner_no' => $line['partner_no'] ?? null,
            
            // Translation business metrics (fields 35-36)
            'vendor_word_count' => $line['vendor_word_count'] ?? 0,
            'translator_word_count' => $line['translator_word_count'] ?? 0,
            
            // Agent (field 37)
            'agent_name' => $line['agent_name'] ?? null,
            
            // Audit
            'created_by' => (string)$userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Validate voucher data structure
     */
    private function validateVoucherData(array $data): array
    {
        if (empty($data['header'])) {
            return ['success' => false, 'data' => null, 'error' => 'Missing voucher header'];
        }

        if (empty($data['lines']) || !is_array($data['lines'])) {
            return ['success' => false, 'data' => null, 'error' => 'Missing voucher lines'];
        }

        if (count($data['lines']) < 2) {
            return ['success' => false, 'data' => null, 'error' => 'Voucher must have at least 2 lines'];
        }

        $header = $data['header'];
        $required = ['fin_period', 'voucher_date', 'currency_code'];
        
        foreach ($required as $field) {
            if (empty($header[$field])) {
                return ['success' => false, 'data' => null, 'error' => "Missing required field: {$field}"];
            }
        }

        // Validate fin_period format (YYYYMM)
        if (!preg_match('/^\d{6}$/', $header['fin_period'])) {
            return ['success' => false, 'data' => null, 'error' => 'fin_period must be in YYYYMM format'];
        }

        // Validate currency_code
        if (!isset(self::CURRENCY_TO_VOUCHER[$header['currency_code']])) {
            return ['success' => false, 'data' => null, 'error' => 'Invalid currency_code. Must be 01-06'];
        }

        // Validate each line has account_code
        foreach ($data['lines'] as $idx => $line) {
            if (empty($line['account_code'])) {
                return ['success' => false, 'data' => null, 'error' => "Line {$idx}: Missing account_code"];
            }
        }

        return ['success' => true, 'data' => null, 'error' => null];
    }

    /**
     * Validate period is open for posting
     * Requirement 46.3, 46.19
     */
    private function validatePeriodOpen(string $companyCode, string $finPeriod): array
    {
        $sql = "SELECT status FROM financial_periods 
                WHERE tenant_id = ? AND company_code = ? AND period_code = ? AND deleted_at IS NULL";

        $result = $this->db->Execute($sql, [$this->tenantId, $companyCode, $finPeriod]);

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
     * Requirements: 46.6, 18.2, 18.3
     */
    private function validateBalance(array $lines): array
    {
        $totalDr = 0.0;
        $totalCr = 0.0;

        foreach ($lines as $line) {
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
                    'Voucher is unbalanced: Total Dr (%.2f) ≠ Total Cr (%.2f). Difference: %.2f',
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
     * Requirements: 46.2, 18.5, 18.6, 18.7, 47.10
     */
    private function assignVoucherAndSectionCodes(array $header): array
    {
        $currencyCode = $header['currency_code'];

        // Check if this is a settlement voucher (explicit override)
        if (isset($header['voucher_code']) && $header['voucher_code'] === self::VOUCHER_SETTLEMENT) {
            // Settlement vouchers must use section codes 991-996
            $sectionCode = $header['section_code'] ?? null;
            if (!in_array($sectionCode, self::SETTLEMENT_SECTIONS, true)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Settlement voucher (999) must use section codes 991-996'
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
        $sectionCode = $header['section_code'] ?? self::SECTION_EXPENSE;

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
     * Get exchange rate from Redis cache or database
     * Requirement 46.7, 47.5
     *
     * @param string $currencyCode Currency code (01-06)
     * @param string $date Date in Y-m-d format
     * @param float|null $userOverride User-provided override rate
     * @return float Exchange rate
     */
    private function getExchangeRate(string $currencyCode, string $date, ?float $userOverride = null): float
    {
        // User override takes precedence
        if ($userOverride !== null && $userOverride > 0) {
            return $userOverride;
        }

        // EGP always has rate 1.0
        if ($currencyCode === '01') {
            return 1.0;
        }

        // Try Redis cache first
        if ($this->redis) {
            $cacheKey = "fx:rate:{$currencyCode}:{$date}";
            $cachedRate = $this->redis->get($cacheKey);
            if ($cachedRate !== false && $cachedRate !== null) {
                return (float)$cachedRate;
            }
        }

        // Fall back to database
        $sql = "SELECT rate FROM exchange_rates 
                WHERE tenant_id = ? AND currency_code = ? AND rate_date = ? AND deleted_at IS NULL
                ORDER BY created_at DESC LIMIT 1";

        $result = $this->db->Execute($sql, [$this->tenantId, $currencyCode, $date]);

        if ($result && !$result->EOF) {
            $rate = (float)$result->fields['rate'];
            
            // Cache for future use
            if ($this->redis) {
                $cacheKey = "fx:rate:{$currencyCode}:{$date}";
                $this->redis->setex($cacheKey, 86400, $rate); // 24 hour TTL
            }
            
            return $rate;
        }

        // Default to 1.0 if no rate found
        return 1.0;
    }

    /**
     * Calculate word count amounts
     * Requirement 46.12
     */
    private function calculateWordCountAmounts(array $lines): array
    {
        foreach ($lines as &$line) {
            // If per-word rate is configured and word count is provided
            if (!empty($line['vendor_word_count']) && !empty($line['vendor_per_word_rate'])) {
                $amount = $line['vendor_word_count'] * $line['vendor_per_word_rate'];
                if (empty($line['dr_value']) && empty($line['cr_value'])) {
                    // Auto-assign to debit or credit based on context
                    $line['dr_value'] = $amount;
                }
            }

            if (!empty($line['translator_word_count']) && !empty($line['translator_per_word_rate'])) {
                $amount = $line['translator_word_count'] * $line['translator_per_word_rate'];
                if (empty($line['dr_value']) && empty($line['cr_value'])) {
                    $line['cr_value'] = $amount;
                }
            }
        }
        
        return $lines;
    }

    /**
     * Convert amounts to EGP using exchange rate
     * Requirement 46.8, 18.12
     */
    private function convertToEGP(array $lines, float $exchangeRate): array
    {
        $converted = [];
        foreach ($lines as $line) {
            $line['dr_value_base'] = round(($line['dr_value'] ?? 0) * $exchangeRate, 2);
            $line['cr_value_base'] = round(($line['cr_value'] ?? 0) * $exchangeRate, 2);
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
            $totalDr += $line['dr_value'] ?? 0;
            $totalCr += $line['cr_value'] ?? 0;
            $totalDrBase += $line['dr_value_base'] ?? 0;
            $totalCrBase += $line['cr_value_base'] ?? 0;
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

        $result = $this->db->Execute($sql, [$this->tenantId, $companyCode, $finPeriod]);

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
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);

        $sql = sprintf(
            "INSERT INTO journal_entry_headers (%s) VALUES (%s) RETURNING id",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $result = $this->db->Execute($sql, $values);

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
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);

        $sql = sprintf(
            "INSERT INTO journal_entry_lines (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $result = $this->db->Execute($sql, $values);

        if ($result === false) {
            throw new \RuntimeException('Failed to insert journal entry line: ' . $this->db->ErrorMsg());
        }
    }
}

    /**
     * Task 30.2: Implement voucher approval state machine
     * Requirements: 46.13, 46.14, 46.15
     *
     * State transitions:
     * Draft → Submitted → Approved → Posted → Reversed
     */

    /**
     * Submit voucher for approval
     */
    public function submit(int $voucherId, int $userId): array
    {
        return $this->transitionStatus($voucherId, self::STATUS_DRAFT, self::STATUS_SUBMITTED, $userId);
    }

    /**
     * Approve voucher
     */
    public function approve(int $voucherId, int $userId): array
    {
        return $this->transitionStatus($voucherId, self::STATUS_SUBMITTED, self::STATUS_APPROVED, $userId, [
            'approved_by' => (string)$userId,
            'approved_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Post voucher to ledger
     */
    public function post(int $voucherId, int $userId): array
    {
        return $this->transitionStatus($voucherId, self::STATUS_APPROVED, self::STATUS_POSTED, $userId, [
            'posted_by' => (string)$userId,
            'posted_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Reverse a posted voucher (Requirement 46.14)
     * Creates equal and opposite voucher and links them
     */
    public function reverse(int $voucherId, int $userId): array
    {
        $this->db->StartTrans();

        try {
            // Get original voucher with lines
            $original = $this->getVoucherWithLines($voucherId);
            
            if (!$original) {
                throw new \RuntimeException("Voucher not found: {$voucherId}");
            }

            if ($original['status'] !== self::STATUS_POSTED) {
                throw new \RuntimeException("Can only reverse posted vouchers");
            }

            // Create reversal header
            $reversalHeader = [
                'company_code' => $original['company_code'],
                'voucher_code' => $original['voucher_code'],
                'section_code' => $original['section_code'],
                'voucher_date' => date('Y-m-d'),
                'fin_period' => date('Ym'),
                'service_date' => $original['service_date'],
                'currency_code' => $original['currency_code'],
                'exchange_rate' => $original['exchange_rate'],
                'description' => 'REVERSAL OF VOUCHER #' . $original['voucher_no'],
            ];

            // Reverse lines (swap Dr and Cr)
            $reversalLines = [];
            foreach ($original['lines'] as $line) {
                $reversalLines[] = array_merge($line, [
                    'dr_value' => $line['cr_value'],  // Swap
                    'cr_value' => $line['dr_value'],  // Swap
                    'line_desc' => 'REVERSAL: ' . ($line['line_desc'] ?? ''),
                ]);
            }

            // Create reversal voucher
            $reversalResult = $this->save([
                'header' => $reversalHeader,
                'lines' => $reversalLines
            ], $userId);

            if (!$reversalResult['success']) {
                throw new \RuntimeException($reversalResult['error']);
            }

            $reversalId = $reversalResult['data']['id'];

            // Auto-post the reversal
            $this->transitionStatus($reversalId, self::STATUS_DRAFT, self::STATUS_POSTED, $userId, [
                'posted_by' => (string)$userId,
                'posted_at' => date('Y-m-d H:i:s')
            ]);

            // Mark original as reversed
            $this->updateVoucherStatus($voucherId, self::STATUS_REVERSED, [
                'reversed_by' => (string)$userId,
                'reversed_at' => date('Y-m-d H:i:s'),
                'reversal_of_id' => $reversalId
            ]);

            $this->db->CompleteTrans();

            return [
                'success' => true,
                'data' => [
                    'original_id' => $voucherId,
                    'reversal_id' => $reversalId,
                    'reversal_voucher_no' => $reversalResult['data']['voucher_no']
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            $this->db->RollbackTrans();
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to reverse voucher: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Copy voucher (Requirement 46.15)
     * Creates a new draft voucher with same lines
     */
    public function copy(int $voucherId, int $userId): array
    {
        $original = $this->getVoucherWithLines($voucherId);
        
        if (!$original) {
            return [
                'success' => false,
                'data' => null,
                'error' => "Voucher not found: {$voucherId}"
            ];
        }

        // Create copy with current date
        $copyHeader = [
            'company_code' => $original['company_code'],
            'currency_code' => $original['currency_code'],
            'voucher_date' => date('Y-m-d'),
            'fin_period' => date('Ym'),
            'service_date' => $original['service_date'],
            'description' => 'COPY OF VOUCHER #' . $original['voucher_no'] . ': ' . ($original['description'] ?? ''),
        ];

        // Copy lines (remove IDs)
        $copyLines = [];
        foreach ($original['lines'] as $line) {
            unset($line['id'], $line['je_header_id'], $line['line_no'], $line['created_at'], $line['updated_at']);
            $copyLines[] = $line;
        }

        return $this->save([
            'header' => $copyHeader,
            'lines' => $copyLines
        ], $userId);
    }

    /**
     * Transition voucher status with validation
     */
    private function transitionStatus(
        int $voucherId,
        string $fromStatus,
        string $toStatus,
        int $userId,
        array $additionalFields = []
    ): array {
        $voucher = $this->getVoucher($voucherId);
        
        if (!$voucher) {
            return [
                'success' => false,
                'data' => null,
                'error' => "Voucher not found: {$voucherId}"
            ];
        }

        if ($voucher['status'] !== $fromStatus) {
            return [
                'success' => false,
                'data' => null,
                'error' => "Invalid status transition: voucher is {$voucher['status']}, expected {$fromStatus}"
            ];
        }

        $this->updateVoucherStatus($voucherId, $toStatus, $additionalFields);

        return [
            'success' => true,
            'data' => ['id' => $voucherId, 'status' => $toStatus],
            'error' => null
        ];
    }

    /**
     * Update voucher status
     */
    private function updateVoucherStatus(int $voucherId, string $status, array $additionalFields = []): void
    {
        $fields = array_merge(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], $additionalFields);
        
        $setClauses = [];
        $values = [];
        foreach ($fields as $key => $value) {
            $setClauses[] = "{$key} = ?";
            $values[] = $value;
        }
        $values[] = $voucherId;

        $sql = "UPDATE journal_entry_headers SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $this->db->Execute($sql, $values);
    }

    /**
     * Get voucher by ID
     */
    private function getVoucher(int $voucherId): ?array
    {
        $sql = "SELECT * FROM journal_entry_headers WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL";
        $result = $this->db->Execute($sql, [$voucherId, $this->tenantId]);
        
        if (!$result || $result->EOF) {
            return null;
        }
        
        return $result->fields;
    }

    /**
     * Get voucher with lines
     */
    private function getVoucherWithLines(int $voucherId): ?array
    {
        $voucher = $this->getVoucher($voucherId);
        
        if (!$voucher) {
            return null;
        }

        $sql = "SELECT * FROM journal_entry_lines WHERE je_header_id = ? AND deleted_at IS NULL ORDER BY line_no";
        $result = $this->db->Execute($sql, [$voucherId]);
        
        $lines = [];
        while ($result && !$result->EOF) {
            $lines[] = $result->fields;
            $result->MoveNext();
        }

        $voucher['lines'] = $lines;
        return $voucher;
    }

    /**
     * Task 30.4: Bulk voucher import from Excel
     * Requirement 46.16
     *
     * @param string $filePath Path to Excel file
     * @param int $userId User ID performing import
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function bulkImport(string $filePath, int $userId): array
    {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'File not found'
            ];
        }

        try {
            // Load Excel file (requires PhpSpreadsheet)
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // First row is header
            $header = array_shift($rows);
            
            $errors = [];
            $imported = 0;
            $vouchers = [];
            $currentVoucher = null;

            foreach ($rows as $rowNum => $row) {
                $rowData = array_combine($header, $row);
                
                // Detect new voucher (when voucher_no changes or first row)
                if ($currentVoucher === null || $rowData['voucher_no'] !== $currentVoucher['header']['voucher_no']) {
                    // Save previous voucher if exists
                    if ($currentVoucher !== null) {
                        $result = $this->save($currentVoucher, $userId);
                        if ($result['success']) {
                            $imported++;
                            $vouchers[] = $result['data'];
                        } else {
                            $errors[] = "Row {$rowNum}: " . $result['error'];
                        }
                    }

                    // Start new voucher
                    $currentVoucher = [
                        'header' => [
                            'company_code' => $rowData['company_code'] ?? $this->companyCode,
                            'fin_period' => $rowData['fin_period'],
                            'voucher_date' => $rowData['voucher_date'],
                            'service_date' => $rowData['service_date'] ?? null,
                            'currency_code' => $rowData['currency_code'],
                            'exchange_rate' => $rowData['exchange_rate'] ?? null,
                            'description' => $rowData['description'] ?? null,
                        ],
                        'lines' => []
                    ];
                }

                // Add line to current voucher
                $currentVoucher['lines'][] = [
                    'account_code' => $rowData['account_code'],
                    'dr_value' => $rowData['dr_value'] ?? 0,
                    'cr_value' => $rowData['cr_value'] ?? 0,
                    'line_desc' => $rowData['line_desc'] ?? null,
                    'cost_center_code' => $rowData['cost_center_code'] ?? null,
                    'vendor_code' => $rowData['vendor_code'] ?? null,
                    'vendor_name' => $rowData['vendor_name'] ?? null,
                    'employee_no' => $rowData['employee_no'] ?? null,
                    'partner_no' => $rowData['partner_no'] ?? null,
                    'asset_no' => $rowData['asset_no'] ?? null,
                    'check_transfer_no' => $rowData['check_transfer_no'] ?? null,
                    'vendor_word_count' => $rowData['vendor_word_count'] ?? 0,
                    'translator_word_count' => $rowData['translator_word_count'] ?? 0,
                ];
            }

            // Save last voucher
            if ($currentVoucher !== null) {
                $result = $this->save($currentVoucher, $userId);
                if ($result['success']) {
                    $imported++;
                    $vouchers[] = $result['data'];
                } else {
                    $errors[] = "Last voucher: " . $result['error'];
                }
            }

            return [
                'success' => true,
                'data' => [
                    'imported' => $imported,
                    'errors' => $errors,
                    'vouchers' => $vouchers
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Task 30.5: Voucher search interface
     * Requirement 46.17
     *
     * @param array $filters Search filters
     * @param int $limit Results per page
     * @param int $offset Pagination offset
     * @return array
     */
    public function search(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['tenant_id = ?'];
        $params = [$this->tenantId];

        if (!empty($filters['company_code'])) {
            $where[] = 'company_code = ?';
            $params[] = $filters['company_code'];
        }

        if (!empty($filters['fin_period'])) {
            $where[] = 'fin_period = ?';
            $params[] = $filters['fin_period'];
        }

        if (!empty($filters['voucher_code'])) {
            $where[] = 'voucher_code = ?';
            $params[] = $filters['voucher_code'];
        }

        if (!empty($filters['section_code'])) {
            $where[] = 'section_code = ?';
            $params[] = $filters['section_code'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'voucher_date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'voucher_date <= ?';
            $params[] = $filters['date_to'];
        }

        // Search by account_code, vendor_code in lines
        if (!empty($filters['account_code']) || !empty($filters['vendor_code'])) {
            $lineWhere = [];
            if (!empty($filters['account_code'])) {
                $lineWhere[] = 'account_code = ?';
                $params[] = $filters['account_code'];
            }
            if (!empty($filters['vendor_code'])) {
                $lineWhere[] = 'vendor_code = ?';
                $params[] = $filters['vendor_code'];
            }
            
            $where[] = 'id IN (SELECT je_header_id FROM journal_entry_lines WHERE ' . implode(' OR ', $lineWhere) . ')';
        }

        $where[] = 'deleted_at IS NULL';

        $sql = "SELECT * FROM journal_entry_headers 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY voucher_date DESC, voucher_no DESC
                LIMIT {$limit} OFFSET {$offset}";

        $result = $this->db->Execute($sql, $params);
        
        $vouchers = [];
        while ($result && !$result->EOF) {
            $vouchers[] = $result->fields;
            $result->MoveNext();
        }

        return $vouchers;
    }

    /**
     * Task 30.6: Generate bilingual PDF for posted vouchers
     * Requirement 46.18
     *
     * @param int $voucherId Voucher ID
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function generatePDF(int $voucherId): array
    {
        $voucher = $this->getVoucherWithLines($voucherId);
        
        if (!$voucher) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Voucher not found'
            ];
        }

        if ($voucher['status'] !== self::STATUS_POSTED) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Can only generate PDF for posted vouchers'
            ];
        }

        try {
            // Initialize mPDF with RTL support
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 20,
                'margin_bottom' => 20,
            ]);

            // Build HTML content (bilingual: Arabic RTL + English LTR)
            $html = $this->buildVoucherPDFHTML($voucher);
            
            $mpdf->WriteHTML($html);
            
            $filename = "voucher_{$voucher['voucher_no']}_{$voucher['fin_period']}.pdf";
            $filepath = sys_get_temp_dir() . '/' . $filename;
            
            $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

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
                'error' => 'PDF generation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build bilingual HTML for voucher PDF
     */
    private function buildVoucherPDFHTML(array $voucher): string
    {
        $html = '
        <style>
            body { font-family: DejaVu Sans, sans-serif; }
            .rtl { direction: rtl; text-align: right; }
            .ltr { direction: ltr; text-align: left; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { border: 1px solid #000; padding: 5px; font-size: 10px; }
            th { background-color: #f0f0f0; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .section { margin: 20px 0; }
        </style>
        
        <div class="header">
            <h2>Journal Voucher / قيد يومية</h2>
            <p>Voucher No: ' . htmlspecialchars($voucher['voucher_no']) . ' | رقم القيد: ' . htmlspecialchars($voucher['voucher_no']) . '</p>
            <p>Date: ' . htmlspecialchars($voucher['voucher_date']) . ' | التاريخ: ' . htmlspecialchars($voucher['voucher_date']) . '</p>
            <p>Period: ' . htmlspecialchars($voucher['fin_period']) . ' | الفترة: ' . htmlspecialchars($voucher['fin_period']) . '</p>
        </div>

        <div class="section">
            <p><strong>Description / الوصف:</strong> ' . htmlspecialchars($voucher['description'] ?? '') . '</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Line / السطر</th>
                    <th>Account / الحساب</th>
                    <th>Description / الوصف</th>
                    <th>Debit / مدين</th>
                    <th>Credit / دائن</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($voucher['lines'] as $line) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($line['line_no']) . '</td>
                    <td>' . htmlspecialchars($line['account_code']) . ' - ' . htmlspecialchars($line['account_desc'] ?? '') . '</td>
                    <td>' . htmlspecialchars($line['line_desc'] ?? '') . '</td>
                    <td>' . number_format($line['dr_value'], 2) . '</td>
                    <td>' . number_format($line['cr_value'], 2) . '</td>
                </tr>';
        }

        $html .= '
                <tr>
                    <td colspan="3"><strong>Total / الإجمالي</strong></td>
                    <td><strong>' . number_format($voucher['total_dr'], 2) . '</strong></td>
                    <td><strong>' . number_format($voucher['total_cr'], 2) . '</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="section">
            <p><strong>Posted By / تم الترحيل بواسطة:</strong> ' . htmlspecialchars($voucher['posted_by'] ?? '') . '</p>
            <p><strong>Posted At / تاريخ الترحيل:</strong> ' . htmlspecialchars($voucher['posted_at'] ?? '') . '</p>
        </div>';

        return $html;
    }
}
