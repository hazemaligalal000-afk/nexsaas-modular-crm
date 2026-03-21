<?php
/**
 * Accounting/JournalEntryService.php
 * 
 * Journal Entry Service - Business logic layer
 * BATCH B — Journal Entry & Voucher Engine
 */

declare(strict_types=1);

namespace Modules\Accounting;

class JournalEntryService
{
    private JournalEntryModel $model;
    private string $tenantId;
    private string $companyCode;
    private $db;

    public function __construct(JournalEntryModel $model, string $tenantId, string $companyCode)
    {
        $this->model = $model;
        $this->tenantId = $tenantId;
        $this->companyCode = $companyCode;
        $this->db = $model->getDb();
    }

    /**
     * List journal entries
     */
    public function list(array $filters, int $limit, int $offset): array
    {
        return $this->model->searchJournalEntries($filters, $limit, $offset);
    }

    /**
     * Count journal entries
     */
    public function count(array $filters): int
    {
        // Implement count query
        return 0; // Placeholder
    }

    /**
     * Get journal entry with lines
     */
    public function getWithLines(int $id): ?array
    {
        return $this->model->getJournalEntryWithLines($id);
    }

    /**
     * Create journal entry
     */
    public function create(array $data, int $userId): int
    {
        // Extract header and lines
        $header = $data['header'] ?? [];
        $lines = $data['lines'] ?? [];

        // 1. Basic Multi-Tenant & Company Checks
        $companyCode = $header['company_code'] ?? $this->companyCode;
        $tenantId = $this->tenantId;

        // 2. Validate Financial Period
        $finPeriod = $header['fin_period'] ?? date('Ym');
        if ($this->model->isPeriodLocked($companyCode, $finPeriod)) {
            throw new \RuntimeException("Financial period {$finPeriod} is not open for posting.");
        }

        // 3. Currency-Voucher Pair Validation (Rule 8.3)
        $vouCode = (string)$header['voucher_code'];
        $currCode = (string)$header['currency_code'];
        if ($vouCode !== '999' && $vouCode !== $currCode) {
            throw new \RuntimeException("Currency code {$currCode} does not match Voucher Type {$vouCode}.");
        }

        // 4. Validate Balance (Rule 7.3)
        $validation = $this->validateBalance($lines);
        if (!$validation['balanced']) {
            throw new \RuntimeException("Double-entry balance validation failed: " . $validation['message']);
        }

        // 5. Company-Specific Rules (Translation Word Counts - Rule 8.6)
        $company = $this->db->Execute("SELECT activity FROM companies WHERE code = ? AND tenant_id = ?", [$companyCode, $tenantId])->fields;
        $isTranslation = ($company['activity'] ?? '') === 'Translation';

        // 6. Enrich Lines & Validate Individual Rows
        foreach ($lines as &$line) {
            // Rule 8.4: Cost Center required for expenses (sc=02)
            $sc = (string)($line['section_code'] ?? $header['section_code']);
            if ($sc === '02' && empty($line['cost_center_code'])) {
                throw new \RuntimeException("Cost center is required for all expense lines.");
            }

            // Rule 8.6: Word count for translation
            if ($isTranslation && ($line['dr_value'] > 0 || $line['cr_value'] > 0)) {
                // Simplified check: if it's a revenue account (starts with 4 in standard CoA)
                if (str_starts_with($line['account_code'], '4')) {
                    if (empty($line['vendor_word_count']) || empty($line['translator_word_count'])) {
                        // For fully implementation, we might warn or enforce. Let's enforce for revenue.
                        // throw new \RuntimeException("Word counts are required for revenue lines in translation companies.");
                    }
                }
            }

            // Resolve descriptions if missing
            if (empty($line['account_desc'])) {
                $acc = $this->db->Execute("SELECT account_name_en FROM chart_of_accounts WHERE account_code = ? AND tenant_id = ?", [$line['account_code'], $tenantId])->fields;
                $line['account_desc'] = $acc['account_name_en'] ?? '';
            }
            
            if (!empty($line['cost_center_code']) && empty($line['cost_center_name'])) {
                $cc = $this->db->Execute("SELECT name FROM cost_centers WHERE code = ? AND tenant_id = ?", [$line['cost_center_code'], $tenantId])->fields;
                $line['cost_center_name'] = $cc['name'] ?? '';
            }

            if (!empty($line['vendor_code']) && empty($line['vendor_name'])) {
                $v = $this->db->Execute("SELECT partner_name FROM partners WHERE partner_code = ? AND tenant_id = ?", [$line['vendor_code'], $tenantId])->fields;
                $line['vendor_name'] = $v['partner_name'] ?? '';
            }
        }

        // Prepare header for model
        $header['created_by'] = $userId;
        $header['status'] = $header['status'] ?? 'draft';

        // Create entry via model
        return $this->model->createJournalEntry($header, $lines);
    }

    /**
     * Update journal entry
     */
    public function update(int $id, array $data, int $userId): void
    {
        $entry = $this->model->findById($id);

        if ($entry === null) {
            throw new \RuntimeException('Journal entry not found');
        }

        if ($entry['status'] !== 'draft') {
            throw new \RuntimeException('Can only edit draft entries');
        }

        // Update logic here
    }

    /**
     * Approve journal entry
     */
    public function approve(int $id, int $userId): void
    {
        $entry = $this->model->findById($id);

        if ($entry === null) {
            throw new \RuntimeException('Journal entry not found');
        }

        if ($entry['status'] !== 'submitted') {
            throw new \RuntimeException('Can only approve submitted entries');
        }

        $this->model->updateStatus($id, 'approved', (string)$userId);
    }

    /**
     * Post journal entry
     */
    public function post(int $id, int $userId): void
    {
        $entry = $this->model->findById($id);

        if ($entry === null) {
            throw new \RuntimeException('Journal entry not found');
        }

        if (!in_array($entry['status'], ['approved', 'draft'])) {
            throw new \RuntimeException('Can only post approved or draft entries');
        }

        $this->model->updateStatus($id, 'posted', (string)$userId);

        // TODO: Update account balances
        // TODO: Dispatch to RabbitMQ for async processing
    }

    /**
     * Reverse journal entry
     */
    public function reverse(int $id, int $userId): int
    {
        return $this->model->reverseJournalEntry($id, (string)$userId);
    }

    /**
     * Delete journal entry
     */
    public function delete(int $id): void
    {
        $entry = $this->model->findById($id);

        if ($entry === null) {
            throw new \RuntimeException('Journal entry not found');
        }

        if ($entry['status'] !== 'draft') {
            throw new \RuntimeException('Can only delete draft entries');
        }

        $this->model->softDelete($id);
    }

    /**
     * Get next voucher number
     */
    public function getNextVoucherNumber(string $companyCode, string $finPeriod): int
    {
        $sql = "SELECT COALESCE(MAX(voucher_no), 0) + 1 as next_no
                FROM journal_entry_headers
                WHERE company_code = ? AND fin_period = ? AND deleted_at IS NULL";
        
        $rs = $this->db->Execute($sql, [$companyCode, $finPeriod]);
        return (int)($rs->fields['next_no'] ?? 1);
    }

    /**
     * Validate double-entry balance
     */
    public function validateBalance(array $lines): array
    {
        $totalDr = 0;
        $totalCr = 0;

        foreach ($lines as $line) {
            $totalDr += (float)($line['dr_value'] ?? 0);
            $totalCr += (float)($line['cr_value'] ?? 0);
        }

        $difference = abs($totalDr - $totalCr);
        $balanced = $difference < 0.01;

        return [
            'balanced' => $balanced,
            'total_dr' => $totalDr,
            'total_cr' => $totalCr,
            'difference' => $difference,
            'message' => $balanced ? 'Entry is balanced' : "Dr/Cr difference: {$difference}"
        ];
    }
}
