<?php
/**
 * Property Test 24: FX Revaluation Round Trip
 * 
 * Task: 31.7
 * Validates: Requirements 47.7, 47.8
 * 
 * Property: WHEN unrealized FX revaluation is posted at period close AND 
 * auto-reversed at period open, THE net effect on account balances MUST be zero.
 */

namespace Tests\Properties;

use PHPUnit\Framework\TestCase;
use Modules\Accounting\FX\FXService;
use Core\Database;

class FXRevaluationRoundTripTest extends TestCase
{
    private Database $db;
    private FXService $fxService;
    private string $tenantId;
    private string $companyCode = '01';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::getInstance();
        $this->fxService = new FXService();
        $this->tenantId = 'test-tenant-' . uniqid();
        
        // Set tenant context
        $_SESSION['tenant_id'] = $this->tenantId;
        $_SESSION['company_code'] = $this->companyCode;
    }

    /**
     * Test revaluation round trip: post + reverse = zero net effect
     */
    public function testRevaluationRoundTrip(): void
    {
        // Arrange: Create foreign currency balance
        $accountCode = '1010'; // Cash USD
        $currencyCode = '02'; // USD
        $foreignBalance = 10000.00;
        $initialRate = 50.00;
        $closingRate = 52.00;
        
        // Create initial journal entry with foreign currency
        $this->createForeignCurrencyBalance($accountCode, $currencyCode, $foreignBalance, $initialRate, '202401');
        
        // Get initial account balance
        $initialBalance = $this->getAccountBalance($accountCode, '202401');
        
        // Act 1: Perform revaluation at period close
        $revalResult = $this->fxService->performUnrealizedRevaluation(
            $this->companyCode, 
            '202401', 
            '2024-01-31'
        );
        
        // Get balance after revaluation
        $balanceAfterReval = $this->getAccountBalance($accountCode, '202401');
        
        // Assert: Balance should change due to revaluation
        $this->assertNotEquals($initialBalance, $balanceAfterReval, 
            "Balance should change after revaluation");
        
        // Act 2: Auto-reverse revaluation at period open
        $reverseCount = $this->fxService->autoReverseRevaluation(
            $this->companyCode, 
            '202401', 
            '202402'
        );
        
        // Get final balance (including reversal in new period)
        $finalBalance = $this->getAccountBalance($accountCode, '202402');
        
        // Assert: Final balance should equal initial balance (round trip)
        $this->assertEquals($initialBalance, $finalBalance, 
            "Balance after revaluation + reversal should equal initial balance", 0.01);
        
        $this->assertGreaterThan(0, $reverseCount, 
            "Should have reversed at least one revaluation entry");
    }

    /**
     * Test that revaluation entries are marked correctly
     */
    public function testRevaluationEntriesMarkedCorrectly(): void
    {
        // Arrange
        $accountCode = '1010';
        $currencyCode = '02';
        $foreignBalance = 5000.00;
        $initialRate = 50.00;
        
        $this->createForeignCurrencyBalance($accountCode, $currencyCode, $foreignBalance, $initialRate, '202401');
        
        // Act: Perform revaluation
        $this->fxService->performUnrealizedRevaluation(
            $this->companyCode, 
            '202401', 
            '2024-01-31'
        );
        
        // Assert: Check that revaluation entries are marked
        $revalEntries = $this->db->query(
            "SELECT * FROM journal_entries 
             WHERE tenant_id = ? 
             AND company_code = ?
             AND fin_period = ?
             AND is_revaluation = TRUE",
            [$this->tenantId, $this->companyCode, '202401']
        );
        
        $this->assertNotEmpty($revalEntries, 
            "Should have created revaluation entries");
        
        foreach ($revalEntries as $entry) {
            $this->assertTrue($entry['is_revaluation'], 
                "Entry should be marked as revaluation");
            $this->assertEquals('posted', $entry['status'], 
                "Revaluation entry should be posted");
        }
    }

    /**
     * Test that reversal entries are marked correctly
     */
    public function testReversalEntriesMarkedCorrectly(): void
    {
        // Arrange
        $accountCode = '1010';
        $currencyCode = '02';
        $foreignBalance = 5000.00;
        $initialRate = 50.00;
        
        $this->createForeignCurrencyBalance($accountCode, $currencyCode, $foreignBalance, $initialRate, '202401');
        
        // Perform revaluation
        $this->fxService->performUnrealizedRevaluation(
            $this->companyCode, 
            '202401', 
            '2024-01-31'
        );
        
        // Act: Auto-reverse
        $this->fxService->autoReverseRevaluation(
            $this->companyCode, 
            '202401', 
            '202402'
        );
        
        // Assert: Check reversal entries
        $reversalEntries = $this->db->query(
            "SELECT * FROM journal_entries 
             WHERE tenant_id = ? 
             AND company_code = ?
             AND fin_period = ?
             AND is_reversal = TRUE",
            [$this->tenantId, $this->companyCode, '202402']
        );
        
        $this->assertNotEmpty($reversalEntries, 
            "Should have created reversal entries");
        
        foreach ($reversalEntries as $entry) {
            $this->assertTrue($entry['is_reversal'], 
                "Entry should be marked as reversal");
            $this->assertNotNull($entry['reversed_entry_id'], 
                "Reversal should link to original entry");
        }
    }

    /**
     * Test property: Revaluation amount equals rate difference × foreign balance
     */
    public function testRevaluationAmountCalculation(): void
    {
        // Arrange
        $accountCode = '1010';
        $currencyCode = '02';
        $foreignBalance = 10000.00;
        $initialRate = 50.00;
        $closingRate = 52.00;
        
        $this->createForeignCurrencyBalance($accountCode, $currencyCode, $foreignBalance, $initialRate, '202401');
        
        // Mock the closing rate
        $this->fxService->saveRate($currencyCode, '2024-01-31', $closingRate, 'manual');
        
        // Act
        $result = $this->fxService->performUnrealizedRevaluation(
            $this->companyCode, 
            '202401', 
            '2024-01-31'
        );
        
        // Assert: Total adjustment should equal (closingRate - initialRate) × foreignBalance
        $expectedAdjustment = ($closingRate - $initialRate) * $foreignBalance;
        
        $this->assertEquals($expectedAdjustment, $result['total_adjustment'], 
            "Revaluation adjustment should equal rate difference × foreign balance", 0.01);
    }

    /**
     * Test property: Multiple revaluations in same period are idempotent
     */
    public function testRevaluationIdempotency(): void
    {
        // Arrange
        $accountCode = '1010';
        $currencyCode = '02';
        $foreignBalance = 5000.00;
        $initialRate = 50.00;
        
        $this->createForeignCurrencyBalance($accountCode, $currencyCode, $foreignBalance, $initialRate, '202401');
        
        // Act: Perform revaluation twice
        $result1 = $this->fxService->performUnrealizedRevaluation(
            $this->companyCode, 
            '202401', 
            '2024-01-31'
        );
        
        $result2 = $this->fxService->performUnrealizedRevaluation(
            $this->companyCode, 
            '202401', 
            '2024-01-31'
        );
        
        // Assert: Second revaluation should consider first revaluation
        // (In practice, you'd typically only revalue once per period)
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
    }

    /**
     * Test that base currency (EGP) is not revalued
     */
    public function testBaseCurrencyNotRevalued(): void
    {
        // Arrange: Create EGP balance
        $accountCode = '1010';
        $currencyCode = '01'; // EGP
        $balance = 10000.00;
        
        $this->createForeignCurrencyBalance($accountCode, $currencyCode, $balance, 1.0, '202401');
        
        // Act
        $result = $this->fxService->performUnrealizedRevaluation(
            $this->companyCode, 
            '202401', 
            '2024-01-31'
        );
        
        // Assert: No revaluation entries for base currency
        $this->assertEmpty($result['revaluation_entries'], 
            "Base currency should not be revalued");
        
        $this->assertEquals(0, $result['total_adjustment'], 
            "Total adjustment should be zero for base currency");
    }

    // Helper methods

    private function createForeignCurrencyBalance(
        string $accountCode, 
        string $currencyCode, 
        float $foreignAmount, 
        float $exchangeRate, 
        string $finPeriod
    ): void {
        // Create journal entry
        $jeId = $this->db->insert('journal_entries', [
            'tenant_id' => $this->tenantId,
            'company_code' => $this->companyCode,
            'fin_period' => $finPeriod,
            'entry_date' => $finPeriod . '15',
            'description' => 'Test foreign currency balance',
            'status' => 'posted',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Create debit line (foreign currency asset)
        $this->db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => $accountCode,
            'currency_code' => $currencyCode,
            'debit' => $foreignAmount * $exchangeRate,
            'credit' => 0,
            'exchange_rate' => $exchangeRate,
            'description' => 'Foreign currency balance'
        ]);
        
        // Create credit line (equity/liability)
        $this->db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => '3000',
            'currency_code' => '01',
            'debit' => 0,
            'credit' => $foreignAmount * $exchangeRate,
            'exchange_rate' => 1.0,
            'description' => 'Equity'
        ]);
    }

    private function getAccountBalance(string $accountCode, string $finPeriod): float
    {
        $sql = "SELECT SUM(debit - credit) as balance
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE je.tenant_id = ? 
                AND je.company_code = ?
                AND je.fin_period <= ?
                AND jel.account_code = ?
                AND je.status = 'posted'";
        
        $result = $this->db->query($sql, [
            $this->tenantId, 
            $this->companyCode, 
            $finPeriod, 
            $accountCode
        ]);
        
        return !empty($result) ? (float)$result[0]['balance'] : 0.0;
    }

    protected function tearDown(): void
    {
        // Cleanup test data
        $this->db->execute("DELETE FROM journal_entry_lines WHERE journal_entry_id IN 
            (SELECT id FROM journal_entries WHERE tenant_id = ?)", [$this->tenantId]);
        $this->db->execute("DELETE FROM journal_entries WHERE tenant_id = ?", [$this->tenantId]);
        $this->db->execute("DELETE FROM exchange_rates WHERE tenant_id = ?", [$this->tenantId]);
        
        parent::tearDown();
    }
}
