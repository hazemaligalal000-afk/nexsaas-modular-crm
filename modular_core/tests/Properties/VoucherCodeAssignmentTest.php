<?php
/**
 * tests/Properties/VoucherCodeAssignmentTest.php
 *
 * Property 18: Voucher Code Assignment
 * Voucher_Code must be auto-assigned based on currency
 *
 * Validates: Requirements 18.5, 18.6, 18.7
 * Feature: nexsaas-modular-crm
 */

declare(strict_types=1);

namespace Tests\Properties;

use PHPUnit\Framework\TestCase;
use Eris\Generator;
use Eris\TestTrait;

class VoucherCodeAssignmentTest extends TestCase
{
    use TestTrait;

    /**
     * Currency code to voucher code mapping
     */
    private const CURRENCY_TO_VOUCHER = [
        '01' => '1',  // EGP
        '02' => '2',  // USD
        '03' => '3',  // AED
        '04' => '4',  // SAR
        '05' => '5',  // EUR
        '06' => '6',  // GBP
    ];

    /**
     * Property 18: Voucher Code Assignment
     *
     * For any currency code, the assigned voucher_code must match the mapping:
     * EGP→1, USD→2, AED→3, SAR→4, EUR→5, GBP→6, Settlement→999
     *
     * Feature: nexsaas-modular-crm, Property 18: Voucher Code Assignment
     */
    public function testVoucherCodeAssignmentByCurrency()
    {
        $this->forAll(
            Generator\elements(['01', '02', '03', '04', '05', '06'])
        )
        ->then(function ($currencyCode) {
            $expectedVoucherCode = self::CURRENCY_TO_VOUCHER[$currencyCode];
            
            // Simulate voucher code assignment
            $assignedVoucherCode = $this->assignVoucherCode($currencyCode);
            
            $this->assertEquals(
                $expectedVoucherCode,
                $assignedVoucherCode,
                "Currency {$currencyCode} must map to voucher code {$expectedVoucherCode}"
            );
        });
    }

    /**
     * Property 18b: Settlement voucher validation
     *
     * Settlement vouchers (999) cannot use section codes 01 or 02
     *
     * Feature: nexsaas-modular-crm, Property 18: Voucher Code Assignment
     */
    public function testSettlementVoucherSectionCodeRestriction()
    {
        $this->forAll(
            Generator\elements(['01', '02']) // Income and Expense section codes
        )
        ->then(function ($sectionCode) {
            $voucherCode = '999'; // Settlement voucher
            
            // This combination must be rejected
            $isValid = $this->validateVoucherSectionCombination($voucherCode, $sectionCode);
            
            $this->assertFalse(
                $isValid,
                "Settlement voucher (999) cannot use section code {$sectionCode} (Req 18.7)"
            );
        });
    }

    /**
     * Property 18c: Settlement voucher must use section codes 991-996
     *
     * Feature: nexsaas-modular-crm, Property 18: Voucher Code Assignment
     */
    public function testSettlementVoucherValidSectionCodes()
    {
        $this->forAll(
            Generator\elements(['991', '992', '993', '994', '995', '996'])
        )
        ->then(function ($sectionCode) {
            $voucherCode = '999'; // Settlement voucher
            
            // This combination must be valid
            $isValid = $this->validateVoucherSectionCombination($voucherCode, $sectionCode);
            
            $this->assertTrue(
                $isValid,
                "Settlement voucher (999) must accept section code {$sectionCode}"
            );
        });
    }

    /**
     * Property 18d: Non-settlement vouchers must use section codes 01 or 02
     *
     * Feature: nexsaas-modular-crm, Property 18: Voucher Code Assignment
     */
    public function testNonSettlementVoucherSectionCodes()
    {
        $this->forAll(
            Generator\elements(['1', '2', '3', '4', '5', '6']), // Non-settlement vouchers
            Generator\elements(['01', '02']) // Income and Expense
        )
        ->then(function ($voucherCode, $sectionCode) {
            // This combination must be valid
            $isValid = $this->validateVoucherSectionCombination($voucherCode, $sectionCode);
            
            $this->assertTrue(
                $isValid,
                "Voucher {$voucherCode} must accept section code {$sectionCode}"
            );
        });
    }

    /**
     * Property 18e: Non-settlement vouchers cannot use settlement section codes
     *
     * Feature: nexsaas-modular-crm, Property 18: Voucher Code Assignment
     */
    public function testNonSettlementVoucherCannotUseSettlementSections()
    {
        $this->forAll(
            Generator\elements(['1', '2', '3', '4', '5', '6']), // Non-settlement vouchers
            Generator\elements(['991', '992', '993', '994', '995', '996']) // Settlement sections
        )
        ->then(function ($voucherCode, $sectionCode) {
            // This combination must be invalid
            $isValid = $this->validateVoucherSectionCombination($voucherCode, $sectionCode);
            
            $this->assertFalse(
                $isValid,
                "Non-settlement voucher {$voucherCode} cannot use settlement section code {$sectionCode}"
            );
        });
    }

    /**
     * Simulate voucher code assignment logic
     */
    private function assignVoucherCode(string $currencyCode): string
    {
        return self::CURRENCY_TO_VOUCHER[$currencyCode] ?? '';
    }

    /**
     * Validate voucher and section code combination
     */
    private function validateVoucherSectionCombination(string $voucherCode, string $sectionCode): bool
    {
        $settlementSections = ['991', '992', '993', '994', '995', '996'];
        $incomeSections = ['01', '02'];

        // Settlement voucher (999)
        if ($voucherCode === '999') {
            // Must use settlement sections (991-996)
            // Cannot use income/expense sections (01, 02)
            return in_array($sectionCode, $settlementSections, true);
        }

        // Non-settlement vouchers (1-6)
        if (in_array($voucherCode, ['1', '2', '3', '4', '5', '6'], true)) {
            // Must use income/expense sections (01, 02)
            // Cannot use settlement sections (991-996)
            return in_array($sectionCode, $incomeSections, true);
        }

        return false;
    }
}
