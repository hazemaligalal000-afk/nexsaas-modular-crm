<?php
namespace Modules\Accounting\FX;

use Core\BaseService;
use Core\Database;

/**
 * FXService: Multi-Currency and Exchange Rate Engine (Batch C)
 * Task 31.2, 31.3, 31.4, 31.5
 * Requirements: 47.3, 47.4, 47.5, 47.6, 47.7, 47.8, 47.9
 */
class FXService extends BaseService {
    
    /**
     * Get exchange rate for a specific currency and date
     * Redis-first with DB fallback
     * 
     * @param string $currencyCode Currency code (01-06)
     * @param string $date Date in YYYY-MM-DD format
     * @return float Exchange rate to EGP
     */
    public function getRateForDate(string $currencyCode, string $date): float {
        $redis = \Core\Performance\CacheManager::getInstance();
        $cacheKey = "fx:rate:{$currencyCode}:{$date}";
        
        // 1. Redis First (Req 47.4)
        $rate = $redis->get($cacheKey);
        if ($rate !== null) {
            return (float)$rate;
        }

        // 2. Fall back to DB
        $db = Database::getInstance();
        $sql = "SELECT rate_to_base FROM exchange_rates 
                WHERE tenant_id = ? AND currency_code = ? AND rate_date = ? 
                AND deleted_at IS NULL";
        $res = $db->query($sql, [$this->tenantId, $currencyCode, $date]);
        
        if (!empty($res)) {
            $rateValue = (float)$res[0]['rate_to_base'];
            $redis->set($cacheKey, $rateValue, 86400); // 24h TTL (Req 47.5)
            return $rateValue;
        }

        // 3. Check if auto-fetch is enabled
        $autoFetch = $this->isAutoFetchEnabled($currencyCode);
        if ($autoFetch) {
            $fetchedRate = $this->fetchFromCentralBank($currencyCode, $date);
            if ($fetchedRate !== null) {
                $this->saveRate($currencyCode, $date, $fetchedRate, 'auto');
                $redis->set($cacheKey, $fetchedRate, 86400);
                return $fetchedRate;
            }
        }

        // 4. Return 1.0 for base currency (EGP), throw for others
        if ($currencyCode === '01') {
            return 1.0;
        }
        
        throw new \RuntimeException("Exchange rate not found for {$currencyCode} on {$date}");
    }

    /**
     * Get exchange rate history for a currency over a date range
     * 
     * @param string $currencyCode Currency code
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Array of [date, rate] pairs
     */
    public function getRateHistory(string $currencyCode, string $startDate, string $endDate): array {
        $db = Database::getInstance();
        $sql = "SELECT rate_date, rate_to_base 
                FROM exchange_rates 
                WHERE tenant_id = ? AND currency_code = ? 
                AND rate_date BETWEEN ? AND ?
                AND deleted_at IS NULL
                ORDER BY rate_date ASC";
        
        $rows = $db->query($sql, [$this->tenantId, $currencyCode, $startDate, $endDate]);
        
        return array_map(function($row) {
            return [
                'date' => $row['rate_date'],
                'rate' => (float)$row['rate_to_base']
            ];
        }, $rows);
    }

    /**
     * Save or update exchange rate
     * 
     * @param string $currencyCode Currency code
     * @param string $date Date
     * @param float $rate Rate to EGP
     * @param string $source 'manual' or 'auto'
     * @return bool
     */
    public function saveRate(string $currencyCode, string $date, float $rate, string $source = 'manual'): bool {
        $db = Database::getInstance();
        
        // Check if rate exists
        $existing = $db->query(
            "SELECT id FROM exchange_rates 
             WHERE tenant_id = ? AND currency_code = ? AND rate_date = ? 
             AND deleted_at IS NULL",
            [$this->tenantId, $currencyCode, $date]
        );
        
        if (!empty($existing)) {
            // Update existing
            $sql = "UPDATE exchange_rates 
                    SET rate_to_base = ?, source = ?, updated_at = NOW()
                    WHERE id = ?";
            $db->execute($sql, [$rate, $source, $existing[0]['id']]);
        } else {
            // Insert new
            $sql = "INSERT INTO exchange_rates 
                    (tenant_id, currency_code, rate_date, rate_to_base, source, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $db->execute($sql, [
                $this->tenantId, 
                $currencyCode, 
                $date, 
                $rate, 
                $source,
                $this->userId ?? 'system'
            ]);
        }
        
        // Invalidate cache
        $redis = \Core\Performance\CacheManager::getInstance();
        $redis->delete("fx:rate:{$currencyCode}:{$date}");
        
        return true;
    }

    /**
     * Check if auto-fetch is enabled for a currency
     * 
     * @param string $currencyCode Currency code
     * @return bool
     */
    private function isAutoFetchEnabled(string $currencyCode): bool {
        $db = Database::getInstance();
        $sql = "SELECT auto_fetch_enabled FROM currencies 
                WHERE tenant_id = ? AND code = ? AND deleted_at IS NULL";
        $res = $db->query($sql, [$this->tenantId, $currencyCode]);
        
        return !empty($res) && $res[0]['auto_fetch_enabled'] === true;
    }

    /**
     * Fetch rate from Central Bank of Egypt API
     * 
     * @param string $currencyCode Currency code
     * @param string $date Date
     * @return float|null Rate or null if unavailable
     */
    private function fetchFromCentralBank(string $currencyCode, string $date): ?float {
        // Simulated API call to Central Bank of Egypt
        // In production, this would call the actual CBE API
        // Req 47.4: Rate source toggle
        
        $currencyMap = [
            '02' => 'USD',
            '03' => 'AED',
            '04' => 'SAR',
            '05' => 'EUR',
            '06' => 'GBP'
        ];
        
        $isoCode = $currencyMap[$currencyCode] ?? null;
        if (!$isoCode) {
            return null;
        }
        
        // Placeholder rates - in production, fetch from CBE API
        $rates = [
            'USD' => 50.00,
            'AED' => 13.62,
            'SAR' => 13.33,
            'EUR' => 54.50,
            'GBP' => 63.00
        ];
        
        return $rates[$isoCode] ?? null;
    }

    /**
     * Calculate realized FX gain/loss on settlement
     * Req 47.6
     * 
     * @param int $invoiceId Original invoice ID
     * @param int $paymentId Payment/settlement ID
     * @return array ['gain_loss_amount' => float, 'journal_entry_id' => int]
     */
    public function computeRealizedGainLoss(int $invoiceId, int $paymentId): array {
        $db = Database::getInstance();
        
        // Get invoice details
        $invoice = $db->query(
            "SELECT currency_code, amount_foreign, exchange_rate, invoice_date, company_code
             FROM ar_invoices 
             WHERE id = ? AND tenant_id = ?",
            [$invoiceId, $this->tenantId]
        );
        
        if (empty($invoice)) {
            throw new \RuntimeException("Invoice not found");
        }
        
        $inv = $invoice[0];
        
        // Get payment details
        $payment = $db->query(
            "SELECT payment_date, exchange_rate 
             FROM payments 
             WHERE id = ? AND tenant_id = ?",
            [$paymentId, $this->tenantId]
        );
        
        if (empty($payment)) {
            throw new \RuntimeException("Payment not found");
        }
        
        $pmt = $payment[0];
        
        // Calculate realized gain/loss
        $invoiceRate = (float)$inv['exchange_rate'];
        $paymentRate = (float)$pmt['exchange_rate'];
        $foreignAmount = (float)$inv['amount_foreign'];
        
        $invoiceEGP = $foreignAmount * $invoiceRate;
        $paymentEGP = $foreignAmount * $paymentRate;
        $gainLoss = $paymentEGP - $invoiceEGP;
        
        // Post journal entry if gain/loss is significant (> 0.01 EGP)
        $journalEntryId = null;
        if (abs($gainLoss) > 0.01) {
            $journalEntryId = $this->postFXGainLossEntry(
                $inv['company_code'],
                $gainLoss,
                $inv['currency_code'],
                $pmt['payment_date']
            );
        }
        
        return [
            'gain_loss_amount' => $gainLoss,
            'journal_entry_id' => $journalEntryId
        ];
    }

    /**
     * Post FX gain/loss journal entry
     * 
     * @param string $companyCode Company code
     * @param float $gainLoss Gain (positive) or loss (negative)
     * @param string $currencyCode Currency code
     * @param string $date Transaction date
     * @return int Journal entry ID
     */
    private function postFXGainLossEntry(string $companyCode, float $gainLoss, string $currencyCode, string $date): int {
        $db = Database::getInstance();
        
        // Get FX gain/loss account from config
        $fxAccount = $this->getFXGainLossAccount($currencyCode);
        
        // Create journal entry
        $jeId = $db->insert('journal_entries', [
            'tenant_id' => $this->tenantId,
            'company_code' => $companyCode,
            'entry_date' => $date,
            'description' => "Realized FX Gain/Loss - {$currencyCode}",
            'status' => 'posted',
            'created_by' => $this->userId ?? 'system',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Post lines
        if ($gainLoss > 0) {
            // Gain: Credit FX Gain account
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => $fxAccount,
                'debit' => 0,
                'credit' => abs($gainLoss),
                'description' => 'Realized FX Gain'
            ]);
        } else {
            // Loss: Debit FX Loss account
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => $fxAccount,
                'debit' => abs($gainLoss),
                'credit' => 0,
                'description' => 'Realized FX Loss'
            ]);
        }
        
        return $jeId;
    }

    /**
     * Get FX gain/loss account for a currency
     * 
     * @param string $currencyCode Currency code
     * @return string Account code
     */
    private function getFXGainLossAccount(string $currencyCode): string {
        // Map currency to FX account
        // In production, this would be configurable per tenant
        $accountMap = [
            '02' => '4210', // DIFF. $ (USD)
            '03' => '4211', // DIFF. AED
            '04' => '4212', // DIFF. SAR
            '05' => '4213', // DIFF. EUR
            '06' => '4214', // DIFF. GBP
        ];
        
        return $accountMap[$currencyCode] ?? '4210';
    }

    /**
     * Perform unrealized FX revaluation at period close
     * Req 47.7
     * 
     * @param string $companyCode Company code
     * @param string $finPeriod Financial period (YYYYMM)
     * @param string $closingDate Closing date
     * @return array ['revaluation_entries' => array, 'total_adjustment' => float]
     */
    public function performUnrealizedRevaluation(string $companyCode, string $finPeriod, string $closingDate): array {
        $db = Database::getInstance();
        
        // Get all foreign currency accounts with open balances
        $sql = "SELECT account_code, currency_code, 
                       SUM(debit - credit) as balance_foreign,
                       AVG(exchange_rate) as avg_rate
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE je.tenant_id = ? 
                AND je.company_code = ?
                AND je.fin_period <= ?
                AND jel.currency_code != '01'
                AND je.status = 'posted'
                GROUP BY account_code, currency_code
                HAVING SUM(debit - credit) != 0";
        
        $balances = $db->query($sql, [$this->tenantId, $companyCode, $finPeriod]);
        
        $revaluationEntries = [];
        $totalAdjustment = 0;
        
        foreach ($balances as $bal) {
            $currencyCode = $bal['currency_code'];
            $balanceForeign = (float)$bal['balance_foreign'];
            $avgRate = (float)$bal['avg_rate'];
            
            // Get closing rate
            $closingRate = $this->getRateForDate($currencyCode, $closingDate);
            
            // Calculate revaluation adjustment
            $bookValueEGP = $balanceForeign * $avgRate;
            $marketValueEGP = $balanceForeign * $closingRate;
            $adjustment = $marketValueEGP - $bookValueEGP;
            
            if (abs($adjustment) > 0.01) {
                $jeId = $this->postRevaluationEntry(
                    $companyCode,
                    $finPeriod,
                    $bal['account_code'],
                    $currencyCode,
                    $adjustment,
                    $closingDate
                );
                
                $revaluationEntries[] = [
                    'account_code' => $bal['account_code'],
                    'currency_code' => $currencyCode,
                    'adjustment' => $adjustment,
                    'journal_entry_id' => $jeId
                ];
                
                $totalAdjustment += $adjustment;
            }
        }
        
        return [
            'revaluation_entries' => $revaluationEntries,
            'total_adjustment' => $totalAdjustment
        ];
    }

    /**
     * Post unrealized revaluation journal entry
     * 
     * @param string $companyCode Company code
     * @param string $finPeriod Financial period
     * @param string $accountCode Account code
     * @param string $currencyCode Currency code
     * @param float $adjustment Adjustment amount
     * @param string $date Entry date
     * @return int Journal entry ID
     */
    private function postRevaluationEntry(
        string $companyCode, 
        string $finPeriod, 
        string $accountCode, 
        string $currencyCode, 
        float $adjustment,
        string $date
    ): int {
        $db = Database::getInstance();
        
        // Create journal entry
        $jeId = $db->insert('journal_entries', [
            'tenant_id' => $this->tenantId,
            'company_code' => $companyCode,
            'fin_period' => $finPeriod,
            'entry_date' => $date,
            'description' => "Unrealized FX Revaluation - {$currencyCode}",
            'status' => 'posted',
            'is_revaluation' => true,
            'created_by' => $this->userId ?? 'system',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $revalAccount = $this->getRevaluationAccount($currencyCode);
        
        if ($adjustment > 0) {
            // Debit asset/expense, Credit revaluation reserve
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => $accountCode,
                'debit' => abs($adjustment),
                'credit' => 0
            ]);
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => $revalAccount,
                'debit' => 0,
                'credit' => abs($adjustment)
            ]);
        } else {
            // Credit asset/expense, Debit revaluation reserve
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => $accountCode,
                'debit' => 0,
                'credit' => abs($adjustment)
            ]);
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => $revalAccount,
                'debit' => abs($adjustment),
                'credit' => 0
            ]);
        }
        
        return $jeId;
    }

    /**
     * Auto-reverse revaluation entries at start of new period
     * Req 47.8
     * 
     * @param string $companyCode Company code
     * @param string $priorPeriod Prior financial period
     * @param string $newPeriod New financial period
     * @return int Number of entries reversed
     */
    public function autoReverseRevaluation(string $companyCode, string $priorPeriod, string $newPeriod): int {
        $db = Database::getInstance();
        
        // Find all revaluation entries from prior period
        $sql = "SELECT id FROM journal_entries 
                WHERE tenant_id = ? 
                AND company_code = ?
                AND fin_period = ?
                AND is_revaluation = TRUE
                AND status = 'posted'";
        
        $entries = $db->query($sql, [$this->tenantId, $companyCode, $priorPeriod]);
        
        $reversedCount = 0;
        foreach ($entries as $entry) {
            $this->reverseJournalEntry($entry['id'], $newPeriod);
            $reversedCount++;
        }
        
        return $reversedCount;
    }

    /**
     * Reverse a journal entry
     * 
     * @param int $originalEntryId Original entry ID
     * @param string $newPeriod New period for reversal
     * @return int Reversal entry ID
     */
    private function reverseJournalEntry(int $originalEntryId, string $newPeriod): int {
        $db = Database::getInstance();
        
        // Get original entry
        $original = $db->query(
            "SELECT * FROM journal_entries WHERE id = ?",
            [$originalEntryId]
        );
        
        if (empty($original)) {
            throw new \RuntimeException("Original entry not found");
        }
        
        $orig = $original[0];
        
        // Create reversal entry
        $reversalId = $db->insert('journal_entries', [
            'tenant_id' => $orig['tenant_id'],
            'company_code' => $orig['company_code'],
            'fin_period' => $newPeriod,
            'entry_date' => date('Y-m-d', strtotime($newPeriod . '01')),
            'description' => "Reversal: {$orig['description']}",
            'status' => 'posted',
            'is_reversal' => true,
            'reversed_entry_id' => $originalEntryId,
            'created_by' => 'system',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Get original lines and reverse them
        $lines = $db->query(
            "SELECT * FROM journal_entry_lines WHERE journal_entry_id = ?",
            [$originalEntryId]
        );
        
        foreach ($lines as $line) {
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $reversalId,
                'account_code' => $line['account_code'],
                'debit' => $line['credit'], // Swap debit and credit
                'credit' => $line['debit'],
                'description' => "Reversal: {$line['description']}"
            ]);
        }
        
        return $reversalId;
    }

    /**
     * Get revaluation account for a currency
     * 
     * @param string $currencyCode Currency code
     * @return string Account code
     */
    private function getRevaluationAccount(string $currencyCode): string {
        // Map currency to revaluation account
        $accountMap = [
            '02' => '3210', // DIFF. $ (USD)
            '03' => '3211', // DIFF. AED
            '04' => '3212', // DIFF. SAR
            '05' => '3213', // DIFF. EUR
            '06' => '3214', // DIFF. GBP
        ];
        
        return $accountMap[$currencyCode] ?? '3210';
    }

    /**
     * Generate currency translation report
     * Req 47.9
     * 
     * @param string $companyCode Company code
     * @param string $finPeriod Financial period
     * @param string $fromCurrency Source currency code
     * @param string $toCurrency Target currency code (typically '01' for EGP)
     * @return array Translated financial statement balances
     */
    public function generateCurrencyTranslationReport(
        string $companyCode, 
        string $finPeriod, 
        string $fromCurrency, 
        string $toCurrency = '01'
    ): array {
        $db = Database::getInstance();
        
        // Get closing date for period
        $closingDate = $finPeriod . '28'; // Simplified
        
        // Get closing rate
        $closingRate = $this->getRateForDate($fromCurrency, $closingDate);
        
        // Get all account balances in source currency
        $sql = "SELECT jel.account_code, coa.account_name, coa.account_type,
                       SUM(jel.debit) as total_debit,
                       SUM(jel.credit) as total_credit,
                       SUM(jel.debit - jel.credit) as net_balance
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                JOIN chart_of_accounts coa ON jel.account_code = coa.account_code
                WHERE je.tenant_id = ? 
                AND je.company_code = ?
                AND je.fin_period = ?
                AND jel.currency_code = ?
                AND je.status = 'posted'
                GROUP BY jel.account_code, coa.account_name, coa.account_type
                ORDER BY jel.account_code";
        
        $balances = $db->query($sql, [$this->tenantId, $companyCode, $finPeriod, $fromCurrency]);
        
        $translatedBalances = [];
        foreach ($balances as $bal) {
            $netBalance = (float)$bal['net_balance'];
            $translatedBalance = $netBalance * $closingRate;
            
            $translatedBalances[] = [
                'account_code' => $bal['account_code'],
                'account_name' => $bal['account_name'],
                'account_type' => $bal['account_type'],
                'balance_original' => $netBalance,
                'original_currency' => $fromCurrency,
                'balance_translated' => $translatedBalance,
                'target_currency' => $toCurrency,
                'exchange_rate' => $closingRate
            ];
        }
        
        return [
            'company_code' => $companyCode,
            'fin_period' => $finPeriod,
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'closing_rate' => $closingRate,
            'balances' => $translatedBalances
        ];
    }
}
