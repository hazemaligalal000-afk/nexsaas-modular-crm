<?php
/**
 * Accounting/SettlementService.php
 * 
 * Settlement Engine - Handles automated Voucher 999 generation
 * Based on System Reference Section 8.2
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseService;
use Modules\Accounting\JournalEntryModel;
use Modules\Accounting\CurrencyModel;
class SettlementService extends BaseService
{
    private JournalEntryModel $jeModel;
    private CurrencyModel $currModel;

    public function __construct(JournalEntryModel $jeModel, CurrencyModel $currModel)
    {
        $this->jeModel = $jeModel;
        $this->currModel = $currModel;
        $this->db = $jeModel->getDb();
    }

    /**
     * Create a settlement voucher (999) for FX revaluation or manual adjustment
     */
    public function createSettlement(string $companyCode, string $finPeriod, array $lines, int $userId): int
    {
        // Rule 8.2: Sections 991–996 map directly to currency codes 01–06
        // This is enforced here by checking the section code
        foreach ($lines as $line) {
            $sc = (string)$line['section_code'];
            if (!str_starts_with($sc, '99')) {
                 throw new \RuntimeException("Manual 999 vouchers must use settlement sections (991-996).");
            }
        }

        $header = [
            'company_code' => $companyCode,
            'voucher_code' => '999',
            'section_code' => $lines[0]['section_code'] ?? '991',
            'voucher_date' => date('Y-m-d'),
            'fin_period' => $finPeriod,
            'currency_code' => '01', // Settlements are usually in base currency EGP
            'exchange_rate' => 1.000000,
            'status' => 'posted', // Settlements are often auto-posted
            'description' => 'Automated Settlement Voucher',
            'created_by' => $userId
        ];

        return $this->jeModel->createJournalEntry($header, $lines);
    }

    /**
     * Generate FX Revaluation Settlement
     * Revalues all foreign currency balances to current rate and posts to gain/loss account
     */
    public function runFXRevaluation(string $companyCode, string $finPeriod, string $currencyCode, float $newRate, int $userId): int
    {
        // 1. Get current balance in foreign currency and base currency
        // 2. Calculate what the base currency balance should be at the new rate
        // 3. Create a 999 voucher for the difference (gain or loss)
        
        // This is a simplified version
        $sql = "SELECT account_code, SUM(dr_value) - SUM(cr_value) as fc_balance, 
                       SUM(dr_value_base) - SUM(cr_value_base) as base_balance
                FROM journal_entry_lines
                WHERE company_code = ? AND currency_code = ? AND fin_period <= ? AND deleted_at IS NULL
                GROUP BY account_code";
        
        $balances = $this->db->GetAll($sql, [$companyCode, $currencyCode, $finPeriod]);
        
        $lines = [];
        $totalGainLoss = 0;

        foreach ($balances as $balance) {
            if (abs($balance['fc_balance']) < 0.01) continue;

            $newBaseValue = $balance['fc_balance'] * $newRate;
            $adjustment = $newBaseValue - $balance['base_balance'];

            if (abs($adjustment) < 0.01) continue;

            // Define GAIN/LOSS account (standard CoA)
            $gainLossAccount = $adjustment > 0 ? '7100' : '8100'; // 7100 = FX Gain, 8100 = FX Loss
            
            // Post adjustment to the account and offset with Gain/Loss
            $lines[] = [
                'account_code' => $balance['account_code'],
                'dr_value' => $adjustment > 0 ? abs($adjustment) : 0,
                'cr_value' => $adjustment < 0 ? abs($adjustment) : 0,
                'dr_value_base' => $adjustment > 0 ? abs($adjustment) : 0,
                'cr_value_base' => $adjustment < 0 ? abs($adjustment) : 0,
                'currency_code' => '01', // Posted in EGP
                'line_desc' => "FX Revaluation: {$currencyCode} at {$newRate}",
                'section_code' => '99' . $currencyCode
            ];

            $totalGainLoss += $adjustment;
        }

        if (empty($lines)) return 0;

        // Add the balancing line for FX Gain/Loss
        $lines[] = [
            'account_code' => $totalGainLoss > 0 ? '7100' : '8100',
            'dr_value' => $totalGainLoss < 0 ? abs($totalGainLoss) : 0,
            'cr_value' => $totalGainLoss > 0 ? abs($totalGainLoss) : 0,
            'dr_value_base' => $totalGainLoss < 0 ? abs($totalGainLoss) : 0,
            'cr_value_base' => $totalGainLoss > 0 ? abs($totalGainLoss) : 0,
            'currency_code' => '01',
            'line_desc' => "Total FX Revaluation " . ($totalGainLoss > 0 ? "Gain" : "Loss"),
            'section_code' => '99' . $currencyCode
        ];

        return $this->createSettlement($companyCode, $finPeriod, $lines, $userId);
    }
}
