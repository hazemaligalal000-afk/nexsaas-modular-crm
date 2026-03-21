<?php
/**
 * Accounting/BankReconService.php
 * 
 * BATCH M — Bank Reconciliation Engine
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseService;

class BankReconService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Match bank statement lines against GL bank lines
     * Rule: Match by amount + date (± 3 days) + reference
     */
    public function matchLines(string $tenantId, string $companyCode, string $bankAccountCode, array $statementLines): array
    {
        $matches = [];
        $unmatched = [];

        foreach ($statementLines as $sl) {
             $amount = (float)$sl['amount'];
             $dateStr = $sl['date']; // Y-m-d

             // Search for a matching line in the General Ledger
             $sql = "SELECT id, voucher_no, amount_base, voucher_date, description
                     FROM journal_entry_lines 
                     WHERE tenant_id = ? AND company_code = ? AND account_code = ? 
                       AND abs(amount_base - ?) < 0.01 
                       AND abs(EXTRACT(DAY FROM (voucher_date - CAST(? AS DATE)))) <= 3 
                       AND recon_status = 'unmatched' AND deleted_at IS NULL";
             
             $match = $this->db->GetRow($sql, [$tenantId, $companyCode, $bankAccountCode, $amount, $dateStr]);

             if ($match) {
                 // Mark as matched in the GL
                 $this->db->Execute(
                     "UPDATE journal_entry_lines SET recon_status = 'matched', recon_date = NOW(), recon_id = ? 
                      WHERE id = ?", 
                     [$sl['bank_statement_id'], $match['id']]
                 );

                 $matches[] = [
                    'statement_line' => $sl,
                    'gl_line' => $match
                 ];
             } else {
                 $unmatched[] = $sl;
             }
        }

        return [
            'matches' => $matches,
            'unmatched' => $unmatched,
            'match_count' => count($matches),
            'unmatched_count' => count($unmatched)
        ];
    }
}
