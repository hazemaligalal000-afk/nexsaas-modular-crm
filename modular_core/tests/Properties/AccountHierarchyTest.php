<?php
/**
 * Property 12: Account Hierarchy Depth Limit
 *
 * Validates: Requirements 9.4
 */

declare(strict_types=1);

namespace Tests\Properties;

use CRM\Accounts\AccountService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CRM\Accounts\AccountService
 */
class AccountHierarchyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function randomUuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Build a mock ADOdb connection that returns a parent account row with
     * the given hierarchy_level when queried by id.
     *
     * @param int|null $parentLevel  hierarchy_level of the parent row (null = parent not found)
     * @param int      $newId        ID returned for INSERT
     */
    private function buildMockDb(?int $parentLevel, int $newId = 1): object
    {
        return new class($parentLevel, $newId) {
            private ?int $parentLevel;
            private int  $newId;
            private int  $affectedRows = 1;

            public function __construct(?int $parentLevel, int $newId)
            {
                $this->parentLevel = $parentLevel;
                $this->newId       = $newId;
            }

            public function Execute(string $sql, array $params = [])
            {
                $lower = strtolower(trim($sql));

                // SELECT for findById (parent lookup)
                if (str_contains($lower, 'from accounts') && str_contains($lower, 'where id = ?')) {
                    if ($this->parentLevel === null) {
                        return $this->emptyRs();
                    }
                    return $this->singleRowRs([
                        'id'              => $params[0],
                        'hierarchy_level' => $this->parentLevel,
                        'name'            => 'Parent Account',
                        'deleted_at'      => null,
                    ]);
                }

                // INSERT
                if (str_starts_with($lower, 'insert into accounts')) {
                    return $this->singleRowRs(['id' => $this->newId]);
                }

                return $this->emptyRs();
            }

            public function ErrorMsg(): string  { return ''; }
            public function Affected_Rows(): int { return $this->affectedRows; }
            public function Insert_ID(): int     { return $this->newId; }
            public function BeginTrans(): void   {}
            public function CommitTrans(): void  {}
            public function RollbackTrans(): void {}

            private function emptyRs(): object
            {
                return new class {
                    public bool  $EOF    = true;
                    public array $fields = [];
                    public function MoveNext(): void {}
                };
            }

            private function singleRowRs(array $row): object
            {
                return new class($row) {
                    public bool  $EOF    = false;
                    public array $fields;
                    public function __construct(array $row) { $this->fields = $row; }
                    public function MoveNext(): void { $this->EOF = true; }
                };
            }
        };
    }

    private function makeService(object $db, ?string $tenantId = null): AccountService
    {
        return new AccountService($db, $tenantId ?? $this->randomUuid(), '01');
    }

    // =========================================================================
    // Property 12: Account Hierarchy Depth Limit
    // Validates: Requirements 9.4
    // =========================================================================

    /**
     * **Validates: Requirements 9.4**
     *
     * Property: for any account whose parent has hierarchy_depth >= MAX_DEPTH (4, i.e. level 5),
     * AccountService::create() MUST throw an InvalidArgumentException.
     * A child cannot be added to a level-5 (or deeper) account.
     *
     * The mock returns `hierarchy_level` in the row; AccountService falls back to
     * `$parent['hierarchy_depth'] ?? $parent['hierarchy_level']`, so passing
     * hierarchy_level values >= 4 (0-based MAX_DEPTH) triggers the guard.
     */
    public function testCreateThrowsWhenParentAtMaxDepth(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            // hierarchy_depth is 0-based; MAX_DEPTH = 4 (level 5).
            // Pass values 4–9 so the service reads parentDepth >= 4 and throws.
            $parentLevel = random_int(4, 9);
            $parentId    = random_int(1, 9999);
            $newId       = random_int(10000, 99999);

            $db      = $this->buildMockDb($parentLevel, $newId);
            $service = $this->makeService($db);

            try {
                $service->create([
                    'name'              => 'Child Account ' . $i,
                    'parent_account_id' => $parentId,
                ], 1);

                $this->fail(
                    "Iteration $i: create() must throw when parent hierarchy_depth={$parentLevel} >= 4 (level 5)"
                );
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString(
                    '5',
                    $e->getMessage(),
                    "Iteration $i: exception message must mention the depth limit (5)"
                );
            }
        }
    }

    /**
     * **Validates: Requirements 9.4**
     *
     * Property: for any account whose parent has hierarchy_depth in [0, 3] (levels 1–4),
     * AccountService::create() MUST succeed and return a positive integer ID.
     * The child will be at depth 1–4 (levels 2–5), still within the 5-level limit.
     */
    public function testCreateSucceedsWhenParentBelowMaxDepth(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            // hierarchy_depth 0–3 means parent is at levels 1–4; child will be 1–4 (depth), still valid.
            $parentLevel = random_int(0, 3);
            $parentId    = random_int(1, 9999);
            $newId       = random_int(10000, 99999);

            $db      = $this->buildMockDb($parentLevel, $newId);
            $service = $this->makeService($db);

            $id = $service->create([
                'name'              => 'Child Account ' . $i,
                'parent_account_id' => $parentId,
            ], 1);

            $this->assertIsInt($id, "Iteration $i: create() must return an integer ID");
            $this->assertGreaterThan(0, $id, "Iteration $i: returned ID must be positive");
        }
    }

    /**
     * **Validates: Requirements 9.4**
     *
     * Property: creating a root account (no parent_account_id) must always
     * succeed regardless of any other accounts in the system.
     */
    public function testCreateRootAccountAlwaysSucceeds(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $newId   = random_int(1, 99999);
            // parentLevel=null means no parent row will be returned
            $db      = $this->buildMockDb(null, $newId);
            $service = $this->makeService($db);

            $id = $service->create([
                'name'    => 'Root Account ' . $i,
                'industry' => 'Technology',
            ], 1);

            $this->assertIsInt($id, "Iteration $i: root account create() must return an integer ID");
            $this->assertGreaterThan(0, $id, "Iteration $i: returned ID must be positive");
        }
    }

    /**
     * **Validates: Requirements 9.4**
     *
     * Property: the hierarchy_depth of a created child account must equal
     * parent.hierarchy_depth + 1 for all valid parent depths [0, 3].
     *
     * We verify this by inspecting the INSERT parameters captured by the mock.
     * AccountService stores hierarchy_depth (0-based) as the 5th INSERT param (index 4).
     */
    public function testChildHierarchyLevelIsParentPlusOne(): void
    {
        $iterations = 40;

        for ($i = 0; $i < $iterations; $i++) {
            // 0-based depth; valid parent depths are 0–3 so child depth 1–4 stays within MAX_DEPTH=4
            $parentLevel = random_int(0, 3);
            $parentId    = random_int(1, 9999);
            $newId       = random_int(10000, 99999);

            // Extended mock that captures INSERT params
            $capturedParams = null;
            $db = new class($parentLevel, $newId, $capturedParams) {
                private int   $parentLevel;
                private int   $newId;
                public ?array $insertParams = null;

                public function __construct(int $parentLevel, int $newId, mixed &$ref)
                {
                    $this->parentLevel = $parentLevel;
                    $this->newId       = $newId;
                }

                public function Execute(string $sql, array $params = [])
                {
                    $lower = strtolower(trim($sql));

                    if (str_contains($lower, 'from accounts') && str_contains($lower, 'where id = ?')) {
                        return $this->singleRowRs([
                            'id'              => $params[0],
                            'hierarchy_level' => $this->parentLevel,
                            'name'            => 'Parent',
                            'deleted_at'      => null,
                        ]);
                    }

                    if (str_starts_with($lower, 'insert into accounts')) {
                        $this->insertParams = $params;
                        return $this->singleRowRs(['id' => $this->newId]);
                    }

                    return $this->emptyRs();
                }

                public function ErrorMsg(): string   { return ''; }
                public function Affected_Rows(): int { return 1; }
                public function Insert_ID(): int     { return $this->newId; }
                public function BeginTrans(): void   {}
                public function CommitTrans(): void  {}
                public function RollbackTrans(): void {}

                private function emptyRs(): object
                {
                    return new class {
                        public bool  $EOF    = true;
                        public array $fields = [];
                        public function MoveNext(): void {}
                    };
                }

                private function singleRowRs(array $row): object
                {
                    return new class($row) {
                        public bool  $EOF    = false;
                        public array $fields;
                        public function __construct(array $row) { $this->fields = $row; }
                        public function MoveNext(): void { $this->EOF = true; }
                    };
                }
            };

            $service = $this->makeService($db);
            $service->create([
                'name'              => 'Child ' . $i,
                'parent_account_id' => $parentId,
            ], 1);

            // hierarchy_depth is the 5th positional param in the INSERT (index 4)
            $insertedDepth = $db->insertParams[4] ?? null;

            $this->assertSame(
                $parentLevel + 1,
                $insertedDepth,
                "Iteration $i: child hierarchy_depth must be parent_depth({$parentLevel}) + 1"
            );
        }
    }
}
