<?php
/**
 * Accounting/JournalEntryModel.php
 * 
 * Journal Entry Model - Core double-entry accounting engine
 * Based on: سيستم_جديد.xlsx (35-field structure)
 * 
 * BATCH B — Journal Entry & Voucher Engine
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseModel;

class JournalEntryModel extends BaseModel
{
    protected string $table = 'journal_entry_headers';

    /**
     * Create a new journal entry with lines
     * 
     * @param array $headerData Header data
     * @param array $lines Array of line items
     * @return int Journal entry header ID
     * @throws \RuntimeException
     */
    public function createJournalEntry(array $headerData, array $lines): int
    {
        // Start transaction
        $this->db->StartTrans();

        try {
            // Validate double-entry balance
            $totalDr = array_sum(array_column($lines, 'dr_value'));
            $totalCr = array_sum(array_column($lines, 'cr_value'));
            
            if (abs($totalDr - $totalCr) > 0.01) {
                throw new \RuntimeException(
                    "Double-entry balance check failed: Dr={$totalDr}, Cr={$totalCr}. " .
                    "Difference: " . abs($totalDr - $totalCr)
                );
            }

            // Calculate base currency totals
            $totalDrBase = 0;
            $totalCrBase = 0;
            foreach ($lines as $line) {
                $rate = $line['exchange_rate'] ?? 1.0;
                $totalDrBase += ($line['dr_value'] ?? 0) * $rate;
                $totalCrBase += ($line['cr_value'] ?? 0) * $rate;
            }

            // Set totals in header
            $headerData['total_dr'] = $totalDr;
            $headerData['total_cr'] = $totalCr;
            $headerData['total_dr_base'] = $totalDrBase;
            $headerData['total_cr_base'] = $totalCrBase;

            // Generate voucher number if not provided
            if (empty($headerData['voucher_no'])) {
                $headerData['voucher_no'] = $this->getNextVoucherNumber(
                    $headerData['company_code'],
                    $headerData['fin_period']
                );
            }

            // Insert header
            $headerId = $this->insert($headerData);

            // Insert lines
            $lineNo = 1;
            foreach ($lines as $line) {
                $line['je_header_id'] = $headerId;
                $line['line_no'] = $lineNo++;
                $line['company_code'] = $headerData['company_code'];
                $line['tenant_id'] = $this->tenantId;
                $line['fin_period'] = $headerData['fin_period'];
                $line['voucher_date'] = $headerData['voucher_date'];
                $line['voucher_no'] = $headerData['voucher_no'];
                $line['section_code'] = $headerData['section_code'];
                $line['currency_code'] = $headerData['currency_code'];
                $line['exchange_rate'] = $headerData['exchange_rate'];

                // Calculate base currency amounts
                $rate = $line['exchange_rate'] ?? 1.0;
                $line['dr_value_base'] = ($line['dr_value'] ?? 0) * $rate;
                $line['cr_value_base'] = ($line['cr_value'] ?? 0) * $rate;

                $this->insertLine($line);
                
                // Real-time balance update (Rule 7.2)
                $this->recordBalanceImpact($line);
            }

            // Commit transaction
            $this->db->CompleteTrans();

            return $headerId;

        } catch (\Exception $e) {
            $this->db->FailTrans();
            throw new \RuntimeException("Failed to create journal entry: " . $e->getMessage());
        }
    }

    /**
     * Update the period-based account balance (Batch D)
     */
    private function recordBalanceImpact(array $line): void
    {
        $sql = "INSERT INTO account_balances 
                (tenant_id, company_code, account_code, currency_code, fin_period, 
                 total_dr, total_cr, total_dr_base, total_cr_base)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (tenant_id, company_code, account_code, currency_code, fin_period, deleted_at)
                DO UPDATE SET 
                    total_dr = account_balances.total_dr + EXCLUDED.total_dr,
                    total_cr = account_balances.total_cr + EXCLUDED.total_cr,
                    total_dr_base = account_balances.total_dr_base + EXCLUDED.total_dr_base,
                    total_cr_base = account_balances.total_cr_base + EXCLUDED.total_cr_base,
                    updated_at = NOW()";
        
        $this->db->Execute($sql, [
            $this->tenantId, 
            $line['company_code'], 
            $line['account_code'], 
            $line['currency_code'] ?? '01', 
            $line['fin_period'],
            $line['dr_value'] ?? 0,
            $line['cr_value'] ?? 0,
            $line['dr_value_base'] ?? 0,
            $line['cr_value_base'] ?? 0
        ]);
    }

    /**
     * Get next voucher number for a company/period
     * 
     * @param string $companyCode
     * @param string $finPeriod
     * @return int
     */
    private function getNextVoucherNumber(string $companyCode, string $finPeriod): int
    {
        $sql = "
            SELECT COALESCE(MAX(voucher_no), 0) + 1 as next_no
            FROM {$this->table}
            WHERE company_code = ? AND fin_period = ?
        ";
        
        $rows = $this->scopeQuery($sql, [$companyCode, $finPeriod]);
        return (int)($rows[0]['next_no'] ?? 1);
    }

    /**
     * Insert a journal entry line
     * 
     * @param array $lineData
     * @return int Line ID
     */
    private function insertLine(array $lineData): int
    {
        $columns = array_keys($lineData);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($lineData);

        $sql = sprintf(
            "INSERT INTO journal_entry_lines (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $result = $this->db->Execute($sql, $values);

        if ($result === false) {
            throw new \RuntimeException('Failed to insert journal entry line: ' . $this->db->ErrorMsg());
        }

        return (int) $this->db->Insert_ID();
    }

    /**
     * Get journal entry with lines
     * 
     * @param int $headerId
     * @return array|null
     */
    public function getJournalEntryWithLines(int $headerId): ?array
    {
        $header = $this->findById($headerId);
        if ($header === null) {
            return null;
        }

        $sql = "
            SELECT * FROM journal_entry_lines
            WHERE je_header_id = ?
            ORDER BY line_no
        ";
        
        $rs = $this->db->Execute($sql, [$headerId]);
        $lines = [];
        while ($rs && !$rs->EOF) {
            $lines[] = $rs->fields;
            $rs->MoveNext();
        }

        $header['lines'] = $lines;
        return $header;
    }

    /**
     * Update journal entry status
     * 
     * @param int $headerId
     * @param string $status
     * @param string $userId
     * @return bool
     */
    public function updateStatus(int $headerId, string $status, string $userId): bool
    {
        $validStatuses = ['draft', 'submitted', 'approved', 'posted', 'reversed'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $data = ['status' => $status];
        
        switch ($status) {
            case 'posted':
                $data['posted_by'] = $userId;
                $data['posted_at'] = date('Y-m-d H:i:s');
                break;
            case 'approved':
                $data['approved_by'] = $userId;
                $data['approved_at'] = date('Y-m-d H:i:s');
                break;
            case 'reversed':
                $data['reversed_by'] = $userId;
                $data['reversed_at'] = date('Y-m-d H:i:s');
                break;
        }

        $success = $this->update($headerId, $data);
        
        if ($success) {
            $this->recordAuditLog($headerId, $status, $userId);
        }

        return $success;
    }

    /**
     * Record interaction in audit log (Batch E)
     */
    private function recordAuditLog(int $headerId, string $action, string $userId): void
    {
        $sql = "INSERT INTO journal_audit_log (je_header_id, action, performed_by, ip_address)
                VALUES (?, ?, ?, ?)";
        
        $this->db->Execute($sql, [$headerId, $action, $userId, $_SERVER['REMOTE_ADDR'] ?? 'system']);
    }

    /**
     * Reverse a journal entry (create equal and opposite entry)
     * 
     * @param int $originalHeaderId
     * @param string $userId
     * @return int New reversed entry ID
     */
    public function reverseJournalEntry(int $originalHeaderId, string $userId): int
    {
        $original = $this->getJournalEntryWithLines($originalHeaderId);
        if ($original === null) {
            throw new \RuntimeException("Journal entry not found: {$originalHeaderId}");
        }

        if ($original['status'] !== 'posted') {
            throw new \RuntimeException("Can only reverse posted entries");
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
            'status' => 'posted',
            'description' => 'REVERSAL OF VOUCHER #' . $original['voucher_no'],
            'reversal_of_id' => $originalHeaderId,
            'posted_by' => $userId,
            'posted_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId
        ];

        // Reverse lines (swap Dr and Cr)
        $reversalLines = [];
        foreach ($original['lines'] as $line) {
            $reversalLines[] = [
                'account_code' => $line['account_code'],
                'dr_value' => $line['cr_value'],  // Swap
                'cr_value' => $line['dr_value'],  // Swap
                'line_desc' => 'REVERSAL: ' . $line['line_desc'],
                'cost_center_code' => $line['cost_center_code'],
                'vendor_code' => $line['vendor_code'],
                'employee_no' => $line['employee_no'],
                'partner_no' => $line['partner_no'],
                'asset_no' => $line['asset_no']
            ];
        }

        $reversalId = $this->createJournalEntry($reversalHeader, $reversalLines);

        // Mark original as reversed
        $this->updateStatus($originalHeaderId, 'reversed', $userId);

        return $reversalId;
    }

    /**
     * Search journal entries
     * 
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function searchJournalEntries(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['company_code'])) {
            $where[] = "company_code = ?";
            $params[] = $filters['company_code'];
        }

        if (!empty($filters['fin_period'])) {
            $where[] = "fin_period = ?";
            $params[] = $filters['fin_period'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['voucher_code'])) {
            $where[] = "voucher_code = ?";
            $params[] = $filters['voucher_code'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "voucher_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "voucher_date <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'AND ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT * FROM {$this->table}
            WHERE 1=1 {$whereClause}
            ORDER BY voucher_date DESC, voucher_no DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->scopeQuery($sql, $params);
    }

    /**
     * Check if period is locked
     * 
     * @param string $companyCode
     * @param string $finPeriod
     * @return bool
     */
    public function isPeriodLocked(string $companyCode, string $finPeriod): bool
    {
        $sql = "
            SELECT status FROM financial_periods
            WHERE company_code = ? AND period_code = ?
        ";
        
        $rows = $this->scopeQuery($sql, [$companyCode, $finPeriod]);
        
        if (empty($rows)) {
            throw new \RuntimeException("Financial period not found: {$finPeriod}");
        }

        return in_array($rows[0]['status'], ['closed', 'locked']);
    }
}
