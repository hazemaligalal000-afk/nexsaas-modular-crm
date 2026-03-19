<?php
/**
 * Property Test 23: Realized FX Gain/Loss Calculation
 * 
 * Task: 31.6
 * Validates: Requirements 47.6
 * 
 * Property: WHEN a foreign-currency invoice is settled at a different exchange rate,
 * THE realized FX gain/loss MUST equal the difference between invoice-date and 
 * settlement-date valuations in base currency.
 */

namespace Tests\Properties;

use PHPUnit\Framework\TestCase;
use Modules\Accounting\FX\FXService;
use Core\Database;

class RealizedFXGainLossTest extends TestCase
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
     * Test realized FX gain scenario
     */
    public function testRealizedFXGain(): void
    {
        // Arrange: Create invoice at rate 50.00
        $invoiceRate = 50.00;
        $paymentRate = 52.00; // Rate increased
        $foreignAmount = 1000.00; // $1000 USD
        
        $invoiceId = $this->createTestInvoice($foreignAmount, $invoiceRate, '2024-01-15');
        $paymentId = $this->createTestPayment($invoiceId, $foreignAmount, $paymentRate, '2024-02-15');
        
        // Act: Calculate realized gain/loss
        $result = $this->fxService->computeRealizedGainLoss($invoiceId, $paymentId);
        
        // Assert: Gain = (1000 * 52.00) - (1000 * 50.00) = 2000 EGP
        $expectedGain = ($foreignAmount * $paymentRate) - ($foreignAmount * $invoiceRate);
        
        $this->assertEquals($expectedGain, $result['gain_loss_amount'], 
            "Realized FX gain should be {$expectedGain} EGP", 0.01);
        
        $this->assertGreaterThan(0, $result['gain_loss_amount'], 
            "Should recognize a gain when payment rate > invoice rate");
        
        $this->assertNotNull($result['journal_entry_id'], 
            "Should create journal entry for significant gain/loss");
    }

    /**
     * Test realized FX loss scenario
     */
    public function testRealizedFXLoss(): void
    {
        // Arrange: Create invoice at rate 52.00
        $invoiceRate = 52.00;
        $paymentRate = 50.00; // Rate decreased
        $foreignAmount = 1000.00; // $1000 USD
        
        $invoiceId = $this->createTestInvoice($foreignAmount, $invoiceRate, '2024-01-15');
        $paymentId = $this->createTestPayment($invoiceId, $foreignAmount, $paymentRate, '2024-02-15');
        
        // Act: Calculate realized gain/loss
        $result = $this->fxService->computeRealizedGainLoss($invoiceId, $paymentId);
        
        // Assert: Loss = (1000 * 50.00) - (1000 * 52.00) = -2000 EGP
        $expectedLoss = ($foreignAmount * $paymentRate) - ($foreignAmount * $invoiceRate);
        
        $this->assertEquals($expectedLoss, $result['gain_loss_amount'], 
            "Realized FX loss should be {$expectedLoss} EGP", 0.01);
        
        $this->assertLessThan(0, $result['gain_loss_amount'], 
            "Should recognize a loss when payment rate < invoice rate");
        
        $this->assertNotNull($result['journal_entry_id'], 
            "Should create journal entry for significant gain/loss");
    }

    /**
     * Test no gain/loss when rates are identical
     */
    public function testNoGainLossWhenRatesIdentical(): void
    {
        // Arrange: Same rate for invoice and payment
        $rate = 50.00;
        $foreignAmount = 1000.00;
        
        $invoiceId = $this->createTestInvoice($foreignAmount, $rate, '2024-01-15');
        $paymentId = $this->createTestPayment($invoiceId, $foreignAmount, $rate, '2024-02-15');
        
        // Act
        $result = $this->fxService->computeRealizedGainLoss($invoiceId, $paymentId);
        
        // Assert: No gain or loss
        $this->assertEquals(0, $result['gain_loss_amount'], 
            "Should have zero gain/loss when rates are identical", 0.01);
        
        $this->assertNull($result['journal_entry_id'], 
            "Should not create journal entry for insignificant amounts");
    }

    /**
     * Test property: Gain/Loss calculation is symmetric
     */
    public function testGainLossSymmetry(): void
    {
        // Property: If scenario A produces gain X, then swapping rates should produce loss -X
        
        $rate1 = 50.00;
        $rate2 = 52.00;
        $foreignAmount = 1000.00;
        
        // Scenario A: Invoice at rate1, payment at rate2
        $invoiceId1 = $this->createTestInvoice($foreignAmount, $rate1, '2024-01-15');
        $paymentId1 = $this->createTestPayment($invoiceId1, $foreignAmount, $rate2, '2024-02-15');
        $result1 = $this->fxService->computeRealizedGainLoss($invoiceId1, $paymentId1);
        
        // Scenario B: Invoice at rate2, payment at rate1
        $invoiceId2 = $this->createTestInvoice($foreignAmount, $rate2, '2024-01-15');
        $paymentId2 = $this->createTestPayment($invoiceId2, $foreignAmount, $rate1, '2024-02-15');
        $result2 = $this->fxService->computeRealizedGainLoss($invoiceId2, $paymentId2);
        
        // Assert: result1 = -result2
        $this->assertEquals(
            -$result1['gain_loss_amount'], 
            $result2['gain_loss_amount'], 
            "Swapping rates should produce opposite gain/loss", 
            0.01
        );
    }

    /**
     * Test property: Gain/Loss scales linearly with amount
     */
    public function testGainLossScalesLinearly(): void
    {
        $invoiceRate = 50.00;
        $paymentRate = 52.00;
        
        // Test with amount X
        $amount1 = 1000.00;
        $invoiceId1 = $this->createTestInvoice($amount1, $invoiceRate, '2024-01-15');
        $paymentId1 = $this->createTestPayment($invoiceId1, $amount1, $paymentRate, '2024-02-15');
        $result1 = $this->fxService->computeRealizedGainLoss($invoiceId1, $paymentId1);
        
        // Test with amount 2X
        $amount2 = 2000.00;
        $invoiceId2 = $this->createTestInvoice($amount2, $invoiceRate, '2024-01-15');
        $paymentId2 = $this->createTestPayment($invoiceId2, $amount2, $paymentRate, '2024-02-15');
        $result2 = $this->fxService->computeRealizedGainLoss($invoiceId2, $paymentId2);
        
        // Assert: result2 = 2 * result1
        $this->assertEquals(
            2 * $result1['gain_loss_amount'], 
            $result2['gain_loss_amount'], 
            "Gain/loss should scale linearly with amount", 
            0.01
        );
    }

    // Helper methods

    private function createTestInvoice(float $foreignAmount, float $exchangeRate, string $date): int
    {
        return $this->db->insert('ar_invoices', [
            'tenant_id' => $this->tenantId,
            'company_code' => $this->companyCode,
            'currency_code' => '02', // USD
            'amount_foreign' => $foreignAmount,
            'exchange_rate' => $exchangeRate,
            'invoice_date' => $date,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function createTestPayment(int $invoiceId, float $amount, float $exchangeRate, string $date): int
    {
        return $this->db->insert('payments', [
            'tenant_id' => $this->tenantId,
            'company_code' => $this->companyCode,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'exchange_rate' => $exchangeRate,
            'payment_date' => $date,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    protected function tearDown(): void
    {
        // Cleanup test data
        $this->db->execute("DELETE FROM payments WHERE tenant_id = ?", [$this->tenantId]);
        $this->db->execute("DELETE FROM ar_invoices WHERE tenant_id = ?", [$this->tenantId]);
        $this->db->execute("DELETE FROM journal_entries WHERE tenant_id = ?", [$this->tenantId]);
        
        parent::tearDown();
    }
}
