<?php

declare(strict_types=1);

namespace Tests\Properties;

use Core\BaseModel;
use PHPUnit\Framework\TestCase;

/**
 * Property 2: Soft Delete Round Trip
 *
 * Validates: Requirements 1.5, 1.6
 *
 * Property: After softDelete(id), the record must not appear in scopeQuery
 * results, but must still exist in the DB with deleted_at set.
 */
class SoftDeleteRoundTripTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function randomUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Build a stateful mock ADOdb connection that maintains an in-memory row store.
     *
     * - Execute() with SELECT: returns rows matching tenant_id + company_code
     *   where deleted_at IS NULL (simulating scopeQuery behaviour).
     * - Execute() with UPDATE … SET deleted_at: marks the matching row deleted.
     *
     * The mock also exposes getRawRows() so tests can inspect the underlying
     * store and verify the row still exists with deleted_at set.
     *
     * @param  array $initialRows  Seed rows (must include 'deleted_at' => null).
     * @return object              Mock ADOConnection with getRawRows().
     */
    private function buildStatefulMockDb(array $initialRows): object
    {
        return new class($initialRows) {
            /** @var array<int, array> Keyed by row id */
            private array $store;
            private int $lastAffected = 0;

            public function __construct(array $rows)
            {
                foreach ($rows as $row) {
                    $this->store[$row['id']] = $row;
                }
            }

            public function Execute(string $sql, array $params = [])
            {
                $upperSql = strtoupper(ltrim($sql));

                // ---- UPDATE (soft-delete) ----
                // Pattern: UPDATE <table> SET deleted_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL
                if (str_starts_with($upperSql, 'UPDATE')) {
                    // params: [deleted_at_value, id, tenant_id, company_code]
                    [$deletedAt, $id, $tenantId, $companyCode] = $params;

                    $this->lastAffected = 0;
                    if (
                        isset($this->store[$id]) &&
                        $this->store[$id]['tenant_id']    === $tenantId &&
                        $this->store[$id]['company_code'] === $companyCode &&
                        $this->store[$id]['deleted_at']   === null
                    ) {
                        $this->store[$id]['deleted_at'] = $deletedAt;
                        $this->lastAffected = 1;
                    }

                    return true; // non-false = success
                }

                // ---- SELECT (scopeQuery) ----
                // params[0] = tenant_id, params[1] = company_code (injected by buildScopedQuery)
                $tenantId    = $params[0] ?? null;
                $companyCode = $params[1] ?? null;

                $filtered = array_values(array_filter(
                    $this->store,
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
            public function Affected_Rows(): int { return $this->lastAffected; }
            public function Insert_ID(): int { return 0; }

            /** Expose raw store for post-delete assertions. */
            public function getRawRows(): array { return $this->store; }
        };
    }

    private function makeModel(object $db, string $tenantId, string $companyCode = '01'): BaseModel
    {
        return new class($db, $tenantId, $companyCode) extends BaseModel {
            protected string $table = 'contacts';

            /** Expose the underlying db so tests can call getRawRows(). */
            public function getDb(): object { return $this->db; }
        };
    }

    // ---------------------------------------------------------------------------
    // Property 2: Soft Delete Round Trip
    // ---------------------------------------------------------------------------

    /**
     * Core round-trip property: after softDelete(id), the record is absent from
     * scopeQuery results but still present in the store with deleted_at set.
     *
     * Validates: Requirements 1.5, 1.6
     */
    public function testSoftDeletedRecordIsExcludedFromScopeQuery(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId = $this->randomUuid();
            $recordId = random_int(1, 1000);

            $initialRows = [
                [
                    'id'           => $recordId,
                    'tenant_id'    => $tenantId,
                    'company_code' => '01',
                    'name'         => "Record-$recordId",
                    'deleted_at'   => null,
                ],
            ];

            $db    = $this->buildStatefulMockDb($initialRows);
            $model = $this->makeModel($db, $tenantId);

            // Pre-condition: record is visible before delete
            $before = $model->scopeQuery('SELECT * FROM contacts');
            $this->assertCount(1, $before, "Iteration $i: record should be visible before soft-delete");

            // Act: soft-delete the record
            $deleted = $model->softDelete($recordId);
            $this->assertTrue($deleted, "Iteration $i: softDelete should return true for an existing record");

            // Post-condition 1 (Req 1.6): record no longer appears in scopeQuery
            $after = $model->scopeQuery('SELECT * FROM contacts');
            $this->assertCount(0, $after, "Iteration $i: soft-deleted record must not appear in scopeQuery results");

            // Post-condition 2 (Req 1.5): row still exists in the store with deleted_at set
            $rawRows = $db->getRawRows();
            $this->assertArrayHasKey($recordId, $rawRows, "Iteration $i: row must still exist in DB after soft-delete");
            $this->assertNotNull(
                $rawRows[$recordId]['deleted_at'],
                "Iteration $i: deleted_at must be set after soft-delete"
            );
        }
    }

    /**
     * Property: softDelete on a non-existent (or already-deleted) record returns false.
     *
     * Validates: Requirement 1.5
     */
    public function testSoftDeleteReturnsFalseForMissingRecord(): void
    {
        $iterations = 30;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId  = $this->randomUuid();
            $realId    = random_int(1, 500);
            $wrongId   = $realId + random_int(1, 500); // guaranteed different

            $initialRows = [
                ['id' => $realId, 'tenant_id' => $tenantId, 'company_code' => '01', 'name' => 'Existing', 'deleted_at' => null],
            ];

            $db    = $this->buildStatefulMockDb($initialRows);
            $model = $this->makeModel($db, $tenantId);

            $result = $model->softDelete($wrongId);
            $this->assertFalse($result, "Iteration $i: softDelete on non-existent id must return false");
        }
    }

    /**
     * Property: softDelete is idempotent — calling it twice on the same record
     * returns false on the second call (already deleted).
     *
     * Validates: Requirement 1.5
     */
    public function testSoftDeleteIsIdempotent(): void
    {
        $iterations = 30;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId = $this->randomUuid();
            $recordId = random_int(1, 1000);

            $initialRows = [
                ['id' => $recordId, 'tenant_id' => $tenantId, 'company_code' => '01', 'name' => 'Target', 'deleted_at' => null],
            ];

            $db    = $this->buildStatefulMockDb($initialRows);
            $model = $this->makeModel($db, $tenantId);

            $first  = $model->softDelete($recordId);
            $second = $model->softDelete($recordId);

            $this->assertTrue($first,  "Iteration $i: first softDelete must return true");
            $this->assertFalse($second, "Iteration $i: second softDelete on already-deleted record must return false");
        }
    }

    /**
     * Property: softDelete is tenant-scoped — a model for tenant A cannot
     * soft-delete a record belonging to tenant B.
     *
     * Validates: Requirements 1.3, 1.5
     */
    public function testSoftDeleteCannotDeleteAnotherTenantsRecord(): void
    {
        $iterations = 30;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantA  = $this->randomUuid();
            $tenantB  = $this->randomUuid();
            while ($tenantB === $tenantA) {
                $tenantB = $this->randomUuid();
            }

            $recordId = random_int(1, 1000);

            // Row belongs to tenant B
            $initialRows = [
                ['id' => $recordId, 'tenant_id' => $tenantB, 'company_code' => '01', 'name' => 'TenantB-Record', 'deleted_at' => null],
            ];

            $db     = $this->buildStatefulMockDb($initialRows);
            $modelA = $this->makeModel($db, $tenantA); // model scoped to tenant A

            $result = $modelA->softDelete($recordId);

            // Tenant A must not be able to delete tenant B's record
            $this->assertFalse($result, "Iteration $i: tenant A must not soft-delete tenant B's record");

            // Row must still be active
            $rawRows = $db->getRawRows();
            $this->assertNull(
                $rawRows[$recordId]['deleted_at'],
                "Iteration $i: tenant B's record must remain active after tenant A's delete attempt"
            );
        }
    }
}
