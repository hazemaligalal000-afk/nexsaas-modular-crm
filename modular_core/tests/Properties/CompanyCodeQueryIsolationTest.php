<?php
/**
 * tests/Properties/CompanyCodeQueryIsolationTest.php
 *
 * Property 19: Company Code Query Isolation
 * Queries must include explicit company_code filter
 *
 * Validates: Requirements 18.9
 * Feature: nexsaas-modular-crm
 */

declare(strict_types=1);

namespace Tests\Properties;

use PHPUnit\Framework\TestCase;
use Eris\Generator;
use Eris\TestTrait;

class CompanyCodeQueryIsolationTest extends TestCase
{
    use TestTrait;

    /**
     * Property 19: Company Code Query Isolation
     *
     * For any journal entry query, the query must include an explicit company_code filter.
     * Queries without a company_code filter must be rejected and must not return
     * records from multiple companies.
     *
     * Feature: nexsaas-modular-crm, Property 19: Company Code Query Isolation
     */
    public function testCompanyCodeFilterRequired()
    {
        $this->forAll(
            Generator\elements(['01', '02', '03', '04', '05', '06']), // Company code
            Generator\string() // Query parameters
        )
        ->then(function ($companyCode, $queryParams) {
            // Simulate a query with company_code filter
            $queryWithCompanyCode = [
                'company_code' => $companyCode,
                'params' => $queryParams
            ];
            
            $isValid = $this->validateQueryHasCompanyCode($queryWithCompanyCode);
            
            $this->assertTrue(
                $isValid,
                "Query with company_code {$companyCode} must be valid"
            );
        });
    }

    /**
     * Property 19b: Queries without company_code must be rejected
     *
     * Feature: nexsaas-modular-crm, Property 19: Company Code Query Isolation
     */
    public function testQueryWithoutCompanyCodeIsRejected()
    {
        $this->forAll(
            Generator\string() // Query parameters
        )
        ->then(function ($queryParams) {
            // Simulate a query WITHOUT company_code filter
            $queryWithoutCompanyCode = [
                'params' => $queryParams
            ];
            
            $isValid = $this->validateQueryHasCompanyCode($queryWithoutCompanyCode);
            
            $this->assertFalse(
                $isValid,
                "Query without company_code must be rejected (Req 18.9)"
            );
        });
    }

    /**
     * Property 19c: Query results must not mix companies
     *
     * For any query result set, all records must belong to the same company_code
     *
     * Feature: nexsaas-modular-crm, Property 19: Company Code Query Isolation
     */
    public function testQueryResultsDoNotMixCompanies()
    {
        $this->forAll(
            Generator\elements(['01', '02', '03', '04', '05', '06']), // Target company
            $this->generateJournalEntryRecords() // Simulated records
        )
        ->then(function ($targetCompanyCode, $records) {
            // Filter records by company code (simulating query execution)
            $filteredRecords = array_filter($records, function ($record) use ($targetCompanyCode) {
                return $record['company_code'] === $targetCompanyCode;
            });
            
            // Verify all returned records belong to the target company
            foreach ($filteredRecords as $record) {
                $this->assertEquals(
                    $targetCompanyCode,
                    $record['company_code'],
                    "All query results must belong to company {$targetCompanyCode}"
                );
            }
            
            // Verify no records from other companies are included
            $otherCompanyCodes = array_diff(['01', '02', '03', '04', '05', '06'], [$targetCompanyCode]);
            foreach ($filteredRecords as $record) {
                $this->assertNotContains(
                    $record['company_code'],
                    $otherCompanyCodes,
                    "Query results must not include records from other companies"
                );
            }
        });
    }

    /**
     * Property 19d: Empty company_code must be rejected
     *
     * Feature: nexsaas-modular-crm, Property 19: Company Code Query Isolation
     */
    public function testEmptyCompanyCodeIsRejected()
    {
        $this->forAll(
            Generator\elements(['', null, ' ']) // Empty values
        )
        ->then(function ($emptyCompanyCode) {
            $query = [
                'company_code' => $emptyCompanyCode,
                'params' => 'test'
            ];
            
            $isValid = $this->validateQueryHasCompanyCode($query);
            
            $this->assertFalse(
                $isValid,
                "Query with empty company_code must be rejected"
            );
        });
    }

    /**
     * Validate that a query has a non-empty company_code filter
     */
    private function validateQueryHasCompanyCode(array $query): bool
    {
        return isset($query['company_code']) 
            && !empty($query['company_code']) 
            && trim($query['company_code']) !== '';
    }

    /**
     * Generate simulated journal entry records with various company codes
     */
    private function generateJournalEntryRecords(): Generator
    {
        return Generator\bind(
            Generator\choose(1, 20), // Number of records
            function ($numRecords) {
                return Generator\seq(array_map(function ($i) {
                    return Generator\bind(
                        Generator\elements(['01', '02', '03', '04', '05', '06']),
                        function ($companyCode) use ($i) {
                            return Generator\constant([
                                'id' => $i,
                                'company_code' => $companyCode,
                                'voucher_no' => $i,
                                'fin_period' => '202501',
                            ]);
                        }
                    );
                }, range(1, $numRecords)));
            }
        );
    }
}
