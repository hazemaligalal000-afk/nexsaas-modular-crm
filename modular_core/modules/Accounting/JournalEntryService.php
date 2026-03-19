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
        // Validate period is open
        $finPeriod = $data['header']['fin_period'] ?? date('Ym');
        $companyCode = $data['header']['company_code'] ?? $this->companyCode;

        if ($this->model->isPeriodLocked($companyCode, $finPeriod)) {
            throw new \RuntimeException("Period {$finPeriod} is locked");
        }

        // Prepare header
        $header = $data['header'];
        $header['created_by'] = $userId;
        $header['status'] = $header['status'] ?? 'draft';

        // Prepare lines
        $lines = $data['lines'] ?? [];

        // Validate balance
        $validation = $this->validateBalance($lines);
        if (!$validation['balanced']) {
            throw new \RuntimeException("Entry is not balanced: {$validation['message']}");
        }

        // Create entry
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
