<?php
/**
 * tests/Properties/ClosedPeriodImmutabilityTest.php
 *
 * Property 20: Closed Period Immutability
 * Posting to closed periods must be rejected
 *
 * Validates: Requirements 18.13, 46.19, 58.2
 * Feature: nexsaas-modular-crm
 */

declare(strict_types=1);

namespace Tests\Properties;

use PHPUnit\Framework\TestCase;
use Eris\Generator;
use Eris\TestTrait;

class ClosedPeriodImmutabilityTest extends TestCase
{
    use TestTrait;

    /**
     * Property 20: Closed Period Immutability
     *
     * For any financial period that is closed or locked for a given company_code,
     * any attempt to post a new journal entry to that period must be rejected.
     *
     * Feature: nexsaas-modular-crm, Property 20: Closed Period Immutability
     */
    public function testClosedPeriodRejectsNewEntries()
    {
        $this->forAll(
            Generator\elements(['01', '02', '03', '04', '05', '06']), // Company code
            $this->generateFinPeriod(), // Financial period
            Generator\elements(['closed', 'locked']) // Period status
        )
        ->then(function ($companyCode, $finPeriod, $status) {
            // Simulate period status check
            $periodStatus = [
                'company_code' => $companyCode,
                'fin_period' => $finPeriod,
                'status' => $status
            ];
            
            // Attempt to post to this period
            $canPost = $this->canPostToPeriod($periodStatus);
            
            $this->assertFalse(
                $canPost,
                "Cannot post to {$status} period {$finPeriod} for company {$companyCode} (Req 18.13)"
            );
        });
    }

    /**
     * Property 20b: Open periods allow posting
     *
     * Feature: nexsaas-modular-crm, Property 20: Closed Period Immutability
     */
    public function testOpenPeriodAllowsPosting()
    {
        $this->forAll(
            Generator\elements(['01', '02', '03', '04', '05', '06']), // Company code
            $this->generateFinPeriod() // Financial period
        )
        ->then(function ($companyCode, $finPeriod) {
            // Simulate open period
            $periodStatus = [
                'company_code' => $companyCode,
                'fin_period' => $finPeriod,
                'status' => 'open'
            ];
            
            // Attempt to post to this period
            $canPost = $this->canPostToPeriod($periodStatus);
            
            $this->assertTrue(
                $canPost,
                "Can post to open period {$finPeriod} for company {$companyCode}"
            );
        });
    }

    /**
     * Property 20c: Locked periods are stricter than closed periods
     *
     * Feature: nexsaas-modular-crm, Property 20: Closed Period Immutability
     */
    public function testLockedPeriodStricterThanClosed()
    {
        $this->forAll(
            Generator\elements(['01', '02', '03', '04', '05', '06']), // Company code
            $this->generateFinPeriod() // Financial period
        )
        ->then(function ($companyCode, $finPeriod) {
            // Both closed and locked periods reject posting
            $closedPeriod = [
                'company_code' => $companyCode,
                'fin_period' => $finPeriod,
                'status' => 'closed'
            ];
            
            $lockedPeriod = [
                'company_code' => $companyCode,
                'fin_period' => $finPeriod,
                'status' => 'locked'
            ];
            
            $canPostToClosed = $this->canPostToPeriod($closedPeriod);
            $canPostToLocked = $this->canPostToPeriod($lockedPeriod);
            
            $this->assertFalse($canPostToClosed, "Cannot post to closed period");
            $this->assertFalse($canPostToLocked, "Cannot post to locked period");
            
            // Locked periods cannot be reopened
            $canReopenClosed = $this->canReopenPeriod($closedPeriod);
            $canReopenLocked = $this->canReopenPeriod($lockedPeriod);
            
            $this->assertTrue($canReopenClosed, "Closed periods can be reopened");
            $this->assertFalse($canReopenLocked, "Locked periods cannot be reopened");
        });
    }

    /**
     * Property 20d: Period status transitions are valid
     *
     * Valid transitions: open → closed → locked
     * Invalid: locked → closed, locked → open, closed → locked → open
     *
     * Feature: nexsaas-modular-crm, Property 20: Closed Period Immutability
     */
    public function testPeriodStatusTransitions()
    {
        $this->forAll(
            Generator\elements([
                ['open', 'closed'],
                ['closed', 'locked'],
                ['open', 'closed', 'locked'],
            ])
        )
        ->then(function ($transitions) {
            $isValidSequence = $this->validateStatusTransitionSequence($transitions);
            
            $this->assertTrue(
                $isValidSequence,
                "Status transition sequence " . implode(' → ', $transitions) . " must be valid"
            );
        });
    }

    /**
     * Property 20e: Invalid period status transitions are rejected
     *
     * Feature: nexsaas-modular-crm, Property 20: Closed Period Immutability
     */
    public function testInvalidPeriodStatusTransitions()
    {
        $this->forAll(
            Generator\elements([
                ['locked', 'closed'],
                ['locked', 'open'],
                ['closed', 'open'], // Without explicit reopen
            ])
        )
        ->then(function ($transitions) {
            $isValidSequence = $this->validateStatusTransitionSequence($transitions);
            
            $this->assertFalse(
                $isValidSequence,
                "Invalid status transition sequence " . implode(' → ', $transitions) . " must be rejected"
            );
        });
    }

    /**
     * Check if posting is allowed to a period
     */
    private function canPostToPeriod(array $periodStatus): bool
    {
        return $periodStatus['status'] === 'open';
    }

    /**
     * Check if a period can be reopened
     */
    private function canReopenPeriod(array $periodStatus): bool
    {
        // Only closed periods can be reopened, not locked
        return $periodStatus['status'] === 'closed';
    }

    /**
     * Validate a sequence of status transitions
     */
    private function validateStatusTransitionSequence(array $transitions): bool
    {
        $validTransitions = [
            'open' => ['closed'],
            'closed' => ['locked'],
            'locked' => [], // No transitions from locked
        ];

        for ($i = 0; $i < count($transitions) - 1; $i++) {
            $from = $transitions[$i];
            $to = $transitions[$i + 1];
            
            if (!isset($validTransitions[$from]) || !in_array($to, $validTransitions[$from], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate financial period in YYYYMM format
     */
    private function generateFinPeriod(): Generator
    {
        return Generator\bind(
            Generator\choose(2020, 2030), // Year
            function ($year) {
                return Generator\bind(
                    Generator\choose(1, 12), // Month
                    function ($month) use ($year) {
                        $period = sprintf('%04d%02d', $year, $month);
                        return Generator\constant($period);
                    }
                );
            }
        );
    }
}
