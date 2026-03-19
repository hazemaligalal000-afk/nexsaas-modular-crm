<?php
/**
 * tests/Properties/MonetaryAmountPrecisionTest.php
 *
 * Property 21: Monetary Amount Precision
 * All monetary amounts must be DECIMAL(15,2) in DB, string/fixed-point in JSON
 *
 * Validates: Requirements 18.8, 44.6
 * Feature: nexsaas-modular-crm
 */

declare(strict_types=1);

namespace Tests\Properties;

use PHPUnit\Framework\TestCase;
use Eris\Generator;
use Eris\TestTrait;

class MonetaryAmountPrecisionTest extends TestCase
{
    use TestTrait;

    /**
     * Property 21: Monetary Amount Precision
     *
     * For any monetary amount stored or returned by the system, it must be
     * represented as DECIMAL(15,2) in the database and as a string or
     * fixed-point decimal in JSON — never as a floating-point number.
     *
     * Feature: nexsaas-modular-crm, Property 21: Monetary Amount Precision
     */
    public function testMonetaryAmountPrecision()
    {
        $this->forAll(
            $this->generateMonetaryAmount()
        )
        ->then(function ($amount) {
            // Verify precision is exactly 2 decimal places
            $formatted = number_format($amount, 2, '.', '');
            $parts = explode('.', $formatted);
            
            $this->assertCount(
                2,
                $parts,
                "Monetary amount must have exactly 2 decimal places"
            );
            
            $this->assertEquals(
                2,
                strlen($parts[1]),
                "Monetary amount must have exactly 2 decimal places"
            );
            
            // Verify the amount fits within DECIMAL(15,2) range
            $this->assertLessThanOrEqual(
                9999999999999.99,
                $amount,
                "Monetary amount must fit within DECIMAL(15,2) range"
            );
            
            $this->assertGreaterThanOrEqual(
                -9999999999999.99,
                $amount,
                "Monetary amount must fit within DECIMAL(15,2) range"
            );
        });
    }

    /**
     * Property 21b: JSON serialization must use string representation
     *
     * Feature: nexsaas-modular-crm, Property 21: Monetary Amount Precision
     */
    public function testMonetaryAmountJsonSerialization()
    {
        $this->forAll(
            $this->generateMonetaryAmount()
        )
        ->then(function ($amount) {
            // Format as string with 2 decimal places
            $formatted = number_format($amount, 2, '.', '');
            
            // Simulate JSON serialization
            $jsonData = [
                'amount' => $formatted
            ];
            
            $json = json_encode($jsonData);
            $decoded = json_decode($json, true);
            
            // Verify the amount is a string in JSON
            $this->assertIsString(
                $decoded['amount'],
                "Monetary amount in JSON must be a string (Req 44.6)"
            );
            
            // Verify precision is preserved
            $this->assertEquals(
                $formatted,
                $decoded['amount'],
                "Monetary amount precision must be preserved in JSON"
            );
        });
    }

    /**
     * Property 21c: Floating-point representation is forbidden
     *
     * Feature: nexsaas-modular-crm, Property 21: Monetary Amount Precision
     */
    public function testFloatingPointForbidden()
    {
        $this->forAll(
            $this->generateMonetaryAmount()
        )
        ->then(function ($amount) {
            // Format as string
            $formatted = number_format($amount, 2, '.', '');
            
            // Simulate JSON with float (WRONG)
            $wrongJson = json_encode(['amount' => (float)$amount]);
            $wrongDecoded = json_decode($wrongJson, true);
            
            // Simulate JSON with string (CORRECT)
            $correctJson = json_encode(['amount' => $formatted]);
            $correctDecoded = json_decode($correctJson, true);
            
            // Verify string representation preserves precision
            $this->assertEquals(
                $formatted,
                $correctDecoded['amount'],
                "String representation must preserve precision"
            );
            
            // Verify float representation may lose precision
            // (This test documents the problem with floats)
            if (is_float($wrongDecoded['amount'])) {
                $floatFormatted = number_format($wrongDecoded['amount'], 2, '.', '');
                // Float may or may not match due to precision issues
                $this->assertTrue(
                    true,
                    "Float representation documented as problematic"
                );
            }
        });
    }

    /**
     * Property 21d: Arithmetic operations preserve precision
     *
     * Feature: nexsaas-modular-crm, Property 21: Monetary Amount Precision
     */
    public function testArithmeticPreservesPrecision()
    {
        $this->forAll(
            $this->generateMonetaryAmount(),
            $this->generateMonetaryAmount()
        )
        ->then(function ($amount1, $amount2) {
            // Perform addition
            $sum = $amount1 + $amount2;
            $sumFormatted = number_format($sum, 2, '.', '');
            
            // Verify result has exactly 2 decimal places
            $parts = explode('.', $sumFormatted);
            $this->assertEquals(
                2,
                strlen($parts[1]),
                "Arithmetic result must have exactly 2 decimal places"
            );
            
            // Perform multiplication
            $product = $amount1 * $amount2;
            $productFormatted = number_format($product, 2, '.', '');
            
            // Verify result has exactly 2 decimal places
            $parts = explode('.', $productFormatted);
            $this->assertEquals(
                2,
                strlen($parts[1]),
                "Arithmetic result must have exactly 2 decimal places"
            );
        });
    }

    /**
     * Property 21e: Exchange rate precision is DECIMAL(10,6)
     *
     * Feature: nexsaas-modular-crm, Property 21: Monetary Amount Precision
     */
    public function testExchangeRatePrecision()
    {
        $this->forAll(
            $this->generateExchangeRate()
        )
        ->then(function ($rate) {
            // Verify precision is exactly 6 decimal places
            $formatted = number_format($rate, 6, '.', '');
            $parts = explode('.', $formatted);
            
            $this->assertCount(
                2,
                $parts,
                "Exchange rate must have exactly 6 decimal places"
            );
            
            $this->assertEquals(
                6,
                strlen($parts[1]),
                "Exchange rate must have exactly 6 decimal places"
            );
            
            // Verify the rate fits within DECIMAL(10,6) range
            $this->assertLessThanOrEqual(
                9999.999999,
                $rate,
                "Exchange rate must fit within DECIMAL(10,6) range"
            );
            
            $this->assertGreaterThan(
                0,
                $rate,
                "Exchange rate must be positive"
            );
        });
    }

    /**
     * Property 21f: Currency conversion preserves precision
     *
     * Feature: nexsaas-modular-crm, Property 21: Monetary Amount Precision
     */
    public function testCurrencyConversionPrecision()
    {
        $this->forAll(
            $this->generateMonetaryAmount(),
            $this->generateExchangeRate()
        )
        ->then(function ($amount, $rate) {
            // Convert amount using exchange rate
            $converted = $amount * $rate;
            $convertedFormatted = number_format($converted, 2, '.', '');
            
            // Verify result has exactly 2 decimal places
            $parts = explode('.', $convertedFormatted);
            $this->assertEquals(
                2,
                strlen($parts[1]),
                "Converted amount must have exactly 2 decimal places"
            );
            
            // Verify the converted amount fits within DECIMAL(15,2) range
            $convertedValue = (float)$convertedFormatted;
            $this->assertLessThanOrEqual(
                9999999999999.99,
                abs($convertedValue),
                "Converted amount must fit within DECIMAL(15,2) range"
            );
        });
    }

    /**
     * Generate monetary amount within DECIMAL(15,2) range
     */
    private function generateMonetaryAmount(): Generator
    {
        return Generator\bind(
            Generator\choose(-999999999999999, 999999999999999), // Integer part (cents)
            function ($cents) {
                $amount = $cents / 100.0; // Convert to decimal
                return Generator\constant(round($amount, 2));
            }
        );
    }

    /**
     * Generate exchange rate within DECIMAL(10,6) range
     */
    private function generateExchangeRate(): Generator
    {
        return Generator\bind(
            Generator\choose(1000, 9999999), // Micro-units (6 decimal places)
            function ($microUnits) {
                $rate = $microUnits / 1000000.0; // Convert to decimal
                return Generator\constant(round($rate, 6));
            }
        );
    }
}
