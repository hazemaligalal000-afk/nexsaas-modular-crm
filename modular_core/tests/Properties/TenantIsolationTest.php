<?php

declare(strict_types=1);

namespace Tests\Properties;

use Core\BaseModel;
use PHPUnit\Framework\TestCase;

/**
 * Property 1: Tenant Data Isolation
 *
 * Validates: Requirements 1.3, 1.4
 *
 * Property: For any two distinct tenant IDs, a query scoped to tenant A must
 * never return records belonging to tenant B.
 */
class TenantIsolationTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /** Generate a random UUID v4 string. */
    private function randomUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Build a mock ADOdb connection whose Execute() returns a fake recordset
     * containing only rows that match the tenant_id bound in the first parameter.
     *
     * The mock inspects the $params array passed to Execute() and filters the
     * provided $allRows accordingly, simulating correct DB-level isolation.
     *
     * @param  array $allRows  All rows in the "database" (associative arrays).
     * @return object          Mock ADOConnection.
     */
    private function buildMockDb(array $allRows): object
    {
        return new class($allRows) {
            private array $rows;
            private int $affectedRows = 0;

            public function __construct(array $rows)
            {
                $this->rows = $rows;
            }

            /** Simulate Execute: filter rows by tenant_id (first param) and company_code (second param). */
            public function Execute(string $sql, array $params = [])
            {
                // params[0] = tenant_id, params[1] = company_code (injected by buildScopedQuery)
                $tenantId    = $params[0] ?? null;
                $companyCode = $params[1] ?? null;

                $filtered = array_values(array_filter(
                    $this->rows,
                    static fn(array $row): bool =>
                        $row['tenant_id']    === $tenantId &&
                        $row['company_code'] === $companyCode &&
                        $row['deleted_at']   === null
                ));

                return new class($filtered) {
                    private array $rows;
                    private int $cursor = 0;
                    public bool $EOF;
                    public array $fields = [];

                    public function __construct(array $rows)
                    {
                        $this->rows = $rows;
                        $this->EOF  = empty($rows);
                        if (!$this->EOF) {
                            $this->fields = $rows[0];
                        }
                    }

                    public function MoveNext(): void
                    {
                        $this->cursor++;
                        if ($this->cursor >= count($this->rows)) {
                            $this->EOF = true;
                        } else {
                            $this->fields = $this->rows[$this->cursor];
                        }
                    }
                };
            }

            public function ErrorMsg(): string { return ''; }
            public function Affected_Rows(): int { return $this->affectedRows; }
            public function Insert_ID(): int { return 1; }
        };
    }

    /**
     * Concrete subclass of BaseModel so we can instantiate it in tests.
     */
    private function makeModel(object $db, string $tenantId, string $companyCode = '01'): BaseModel
    {
        return new class($db, $tenantId, $companyCode) extends BaseModel {
            protected string $table = 'contacts';
        };
    }

    // ---------------------------------------------------------------------------
    // Property 1: Tenant Data Isolation
    // ---------------------------------------------------------------------------

    /**
     * Core isolation property: scopeQuery for tenant A never returns rows
     * belonging to tenant B, for randomly generated tenant pairs.
     *
     * Validates: Requirements 1.3, 1.4
     */
    public function testTenantIsolationHoldsForRandomTenantPairs(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantA = $this->randomUuid();
            $tenantB = $this->randomUuid();

            // Guarantee distinct tenants (astronomically unlikely to collide, but be safe)
            while ($tenantB === $tenantA) {
                $tenantB = $this->randomUuid();
            }

            // Seed the "database" with rows for both tenants
            $allRows = [
                ['id' => 1, 'tenant_id' => $tenantA, 'company_code' => '01', 'name' => 'Alice', 'deleted_at' => null],
                ['id' => 2, 'tenant_id' => $tenantA, 'company_code' => '01', 'name' => 'Bob',   'deleted_at' => null],
                ['id' => 3, 'tenant_id' => $tenantB, 'company_code' => '01', 'name' => 'Carol', 'deleted_at' => null],
                ['id' => 4, 'tenant_id' => $tenantB, 'company_code' => '01', 'name' => 'Dave',  'deleted_at' => null],
            ];

            $db     = $this->buildMockDb($allRows);
            $modelA = $this->makeModel($db, $tenantA);

            $results = $modelA->scopeQuery('SELECT * FROM contacts');

            // Property: no row in results belongs to tenant B
            foreach ($results as $row) {
                $this->assertSame(
                    $tenantA,
                    $row['tenant_id'],
                    "Iteration $i: scopeQuery for tenant A returned a row belonging to tenant B"
                );
                $this->assertNotSame(
                    $tenantB,
                    $row['tenant_id'],
                    "Iteration $i: tenant B data leaked into tenant A query"
                );
            }
        }
    }

    /**
     * Property: scopeQuery for tenant B never returns rows belonging to tenant A
     * (symmetric check).
     *
     * Validates: Requirements 1.3, 1.4
     */
    public function testTenantIsolationIsSymmetric(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantA = $this->randomUuid();
            $tenantB = $this->randomUuid();
            while ($tenantB === $tenantA) {
                $tenantB = $this->randomUuid();
            }

            $allRows = [
                ['id' => 1, 'tenant_id' => $tenantA, 'company_code' => '01', 'name' => 'Alice', 'deleted_at' => null],
                ['id' => 2, 'tenant_id' => $tenantB, 'company_code' => '01', 'name' => 'Carol', 'deleted_at' => null],
            ];

            $db     = $this->buildMockDb($allRows);
            $modelB = $this->makeModel($db, $tenantB);

            $results = $modelB->scopeQuery('SELECT * FROM contacts');

            foreach ($results as $row) {
                $this->assertSame(
                    $tenantB,
                    $row['tenant_id'],
                    "Iteration $i: scopeQuery for tenant B returned a row belonging to tenant A"
                );
            }
        }
    }

    /**
     * Property: a query without a tenant_id must be rejected.
     *
     * Validates: Requirement 1.4
     */
    public function testEmptyTenantIdIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $db = $this->buildMockDb([]);
        // BaseModel constructor must throw when tenantId is empty
        $this->makeModel($db, '');
    }

    /**
     * Property: results for tenant A contain only tenant A rows even when the
     * database holds rows for many distinct tenants.
     *
     * Validates: Requirements 1.3, 1.4
     */
    public function testIsolationHoldsWithManyTenants(): void
    {
        $iterations = 20;

        for ($i = 0; $i < $iterations; $i++) {
            // Generate N random tenants
            $n       = random_int(3, 10);
            $tenants = array_map(fn() => $this->randomUuid(), range(1, $n));

            // Build rows: 2 rows per tenant
            $allRows = [];
            $rowId   = 1;
            foreach ($tenants as $tid) {
                $allRows[] = ['id' => $rowId++, 'tenant_id' => $tid, 'company_code' => '01', 'name' => "Row-$rowId-A", 'deleted_at' => null];
                $allRows[] = ['id' => $rowId++, 'tenant_id' => $tid, 'company_code' => '01', 'name' => "Row-$rowId-B", 'deleted_at' => null];
            }

            // Pick a random target tenant and query
            $targetTenant = $tenants[array_rand($tenants)];
            $db           = $this->buildMockDb($allRows);
            $model        = $this->makeModel($db, $targetTenant);

            $results = $model->scopeQuery('SELECT * FROM contacts');

            foreach ($results as $row) {
                $this->assertSame(
                    $targetTenant,
                    $row['tenant_id'],
                    "Iteration $i: cross-tenant data leak detected with $n tenants"
                );
            }
        }
    }
}
