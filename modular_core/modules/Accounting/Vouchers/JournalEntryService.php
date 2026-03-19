<?php
namespace Modules\Accounting\Vouchers;

use Core\BaseService;
use Core\Database;

/**
 * JournalEntryService: Management of double-entry accounting records.
 * Task 30.1, 30.2
 */
class JournalEntryService extends BaseService {
    public function post(array $data) {
        return $this->transaction(function() use ($data) {
            $db = Database::getInstance();

            // 1. Validate Balance (Σ Dr = Σ Cr)
            $totalDr = 0; $totalCr = 0;
            foreach ($data['lines'] as $line) {
                $totalDr += $line['dr_amount'] ?? 0;
                $totalCr += $line['cr_amount'] ?? 0;
            }

            if (abs($totalDr - $totalCr) > 0.001) {
                return ['success' => false, 'error' => 'Journal entry is not balanced'];
            }

            // 2. Create Header
            $voucherCode = $this->generateVoucherCode($data['journal_type']);
            $sqlHeader = "INSERT INTO journal_entries (
                tenant_id, company_code, voucher_code, entry_date, description, status
            ) VALUES (?, ?, ?, ?, ?, 'posted') RETURNING id";

            $res = $db->query($sqlHeader, [
                $this->tenantId, $this->companyCode, $voucherCode, $data['entry_date'], $data['description']
            ]);
            $entryId = $res[0]['id'];

            // 3. Create Lines
            foreach ($data['lines'] as $line) {
                $sqlLine = "INSERT INTO journal_entry_lines (
                    tenant_id, company_code, journal_entry_id, account_code, dr_amount, cr_amount, fin_period
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $db->query($sqlLine, [
                    $this->tenantId, $this->companyCode, $entryId, $line['account_code'],
                    $line['dr_amount'], $line['cr_amount'], date('Ym', strtotime($data['entry_date']))
                ]);
            }

            return ['success' => true, 'id' => $entryId, 'voucher_code' => $voucherCode];
        });
    }

    private function generateVoucherCode($type) {
        return $type . '-' . uniqid();
    }
}
