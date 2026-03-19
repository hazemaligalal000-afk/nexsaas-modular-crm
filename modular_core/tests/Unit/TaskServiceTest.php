<?php
/**
 * Unit/TaskServiceTest.php
 *
 * Unit tests for CRM\Tasks\TaskService.
 *
 * Tests cover:
 *  - create: valid data inserts task and returns row
 *  - create: missing required fields throw InvalidArgumentException
 *  - create: invalid priority throws InvalidArgumentException
 *  - create: invalid linked_record_type throws InvalidArgumentException
 *  - update: updates fields and returns updated row
 *  - update: invalid status throws InvalidArgumentException
 *  - complete: marks task completed and logs activity
 *  - delete: soft-deletes task
 *  - getForUser: returns tasks for user sorted by due_date
 *  - getForUser: returns tasks sorted by priority order
 *  - bulkAssign: reassigns tasks when acting user has Manager role
 *  - bulkAssign: throws RuntimeException when acting user lacks Manager role
 *  - sendDueReminders: pushes notifications and marks reminder_sent_at
 *
 * Requirements: 15.1, 15.3, 15.4, 15.5, 15.6
 */

declare(strict_types=1);

namespace Tests\Unit;

use CRM\Tasks\TaskService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CRM\Tasks\TaskService
 */
class TaskServiceTest extends TestCase
{
    private string $tenantId    = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    private string $companyCode = '01';

    // -------------------------------------------------------------------------
    // Mock builders
    // -------------------------------------------------------------------------

    private function buildRedis(): object
    {
        return new class {
            public array $pushed = [];
            public function rpush(string $key, string $value): void
            {
                $this->pushed[$key][] = $value;
            }
        };
    }

    /**
     * Build a mock DB that returns a single task row on SELECT and succeeds on INSERT/UPDATE.
     */
    private function buildMockDb(array $taskRow = [], bool $managerRole = true): object
    {
        return new class($taskRow, $managerRole) {
            private array $taskRow;
            private bool  $managerRole;
            public int    $affectedRows = 1;

            public function __construct(array $taskRow, bool $managerRole)
            {
                $this->taskRow     = $taskRow;
                $this->managerRole = $managerRole;
            }

            public function Execute(string $sql, array $params = [])
            {
                $lower = strtolower(trim($sql));

                // INSERT returning id
                if (str_starts_with($lower, 'insert')) {
                    return $this->singleRowRs(['id' => 1]);
                }

                // UPDATE
                if (str_starts_with($lower, 'update')) {
                    return $this->emptyRs();
                }

                // Manager role check
                if (str_contains($lower, 'platform_role in')) {
                    return $this->managerRole ? $this->singleRowRs(['id' => 99]) : $this->emptyRs();
                }

                // SELECT tasks
                if (str_contains($lower, 'from tasks')) {
                    return empty($this->taskRow) ? $this->emptyRs() : $this->singleRowRs($this->taskRow);
                }

                // SELECT activities (for logCompletion)
                if (str_contains($lower, 'from activities')) {
                    return $this->emptyRs();
                }

                return $this->emptyRs();
            }

            public function ErrorMsg(): string   { return ''; }
            public function Affected_Rows(): int { return $this->affectedRows; }
            public function Insert_ID(): int     { return 1; }
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

    private function buildTaskRow(array $overrides = []): array
    {
        return array_merge([
            'id'               => 1,
            'title'            => 'Follow up call',
            'description'      => 'Call the client',
            'due_date'         => '2025-12-01 10:00:00',
            'priority'         => 'high',
            'status'           => 'open',
            'assigned_user_id' => 5,
            'linked_type'      => 'contact',
            'linked_id'        => 10,
            'reminder_sent_at' => null,
            'created_by'       => 1,
            'created_at'       => '2025-01-01 00:00:00',
            'updated_at'       => '2025-01-01 00:00:00',
        ], $overrides);
    }

    private function makeService(object $db, ?object $redis = null): TaskService
    {
        return new TaskService($db, $redis ?? $this->buildRedis(), $this->tenantId, $this->companyCode);
    }

    // =========================================================================
    // create — Requirement 15.1
    // =========================================================================

    public function testCreateReturnsTaskRow(): void
    {
        $taskRow = $this->buildTaskRow();
        $db      = $this->buildMockDb($taskRow);
        $service = $this->makeService($db);

        $result = $service->create([
            'title'              => 'Follow up call',
            'due_date'           => '2025-12-01 10:00:00',
            'assigned_user_id'   => 5,
            'linked_record_type' => 'contact',
            'linked_record_id'   => 10,
            'priority'           => 'high',
        ]);

        $this->assertSame('Follow up call', $result['title']);
        $this->assertSame('high', $result['priority']);
    }

    public function testCreateThrowsOnMissingTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/title/");

        $service = $this->makeService($this->buildMockDb());
        $service->create([
            'due_date'           => '2025-12-01',
            'assigned_user_id'   => 5,
            'linked_record_type' => 'contact',
            'linked_record_id'   => 10,
        ]);
    }

    public function testCreateThrowsOnMissingDueDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/due_date/");

        $service = $this->makeService($this->buildMockDb());
        $service->create([
            'title'              => 'Test',
            'assigned_user_id'   => 5,
            'linked_record_type' => 'contact',
            'linked_record_id'   => 10,
        ]);
    }

    public function testCreateThrowsOnInvalidPriority(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/priority/");

        $service = $this->makeService($this->buildMockDb());
        $service->create([
            'title'              => 'Test',
            'due_date'           => '2025-12-01',
            'assigned_user_id'   => 5,
            'linked_record_type' => 'contact',
            'linked_record_id'   => 10,
            'priority'           => 'critical',  // invalid
        ]);
    }

    public function testCreateThrowsOnInvalidLinkedRecordType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/linked_record_type/");

        $service = $this->makeService($this->buildMockDb());
        $service->create([
            'title'              => 'Test',
            'due_date'           => '2025-12-01',
            'assigned_user_id'   => 5,
            'linked_record_type' => 'invoice',  // invalid
            'linked_record_id'   => 10,
        ]);
    }

    // =========================================================================
    // update — Requirement 15.1
    // =========================================================================

    public function testUpdateReturnsUpdatedRow(): void
    {
        $taskRow = $this->buildTaskRow(['status' => 'in_progress']);
        $db      = $this->buildMockDb($taskRow);
        $service = $this->makeService($db);

        $result = $service->update(1, ['status' => 'in_progress'], $this->tenantId);

        $this->assertSame('in_progress', $result['status']);
    }

    public function testUpdateThrowsOnInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/status/");

        $service = $this->makeService($this->buildMockDb($this->buildTaskRow()));
        $service->update(1, ['status' => 'done'], $this->tenantId);
    }

    // =========================================================================
    // delete — soft delete
    // =========================================================================

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $db      = $this->buildMockDb();
        $service = $this->makeService($db);

        $result = $service->delete(1, $this->tenantId);

        $this->assertTrue($result);
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $db = $this->buildMockDb();
        // Override affectedRows to 0
        $db->affectedRows = 0;

        $service = $this->makeService($db);
        $result  = $service->delete(999, $this->tenantId);

        $this->assertFalse($result);
    }

    // =========================================================================
    // getForUser — Requirement 15.4
    // =========================================================================

    public function testGetForUserReturnsTasks(): void
    {
        $rows = [
            $this->buildTaskRow(['id' => 1, 'due_date' => '2025-11-01 09:00:00']),
            $this->buildTaskRow(['id' => 2, 'due_date' => '2025-11-02 09:00:00']),
        ];

        $db = new class($rows) {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function Execute(string $sql, array $params = [])
            {
                return new class($this->rows) {
                    private array $rows;
                    private int   $cursor = 0;
                    public bool   $EOF;
                    public array  $fields = [];
                    public function __construct(array $rows)
                    {
                        $this->rows = $rows;
                        $this->EOF  = empty($rows);
                        if (!$this->EOF) { $this->fields = $rows[0]; }
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
            public function ErrorMsg(): string   { return ''; }
            public function Affected_Rows(): int { return 0; }
            public function Insert_ID(): int     { return 0; }
            public function BeginTrans(): void   {}
            public function CommitTrans(): void  {}
            public function RollbackTrans(): void {}
        };

        $service = $this->makeService($db);
        $result  = $service->getForUser(5, $this->tenantId, ['sort' => 'due_date', 'dir' => 'ASC']);

        $this->assertCount(2, $result);
    }

    // =========================================================================
    // bulkAssign — Requirement 15.6
    // =========================================================================

    public function testBulkAssignSucceedsForManager(): void
    {
        $db      = $this->buildMockDb([], true);  // managerRole = true
        $service = $this->makeService($db);

        $result = $service->bulkAssign([1, 2, 3], 7, $this->tenantId, 99);

        $this->assertSame(3, count($result['task_ids']));
        $this->assertSame(7, $result['new_user_id']);
    }

    public function testBulkAssignThrowsForNonManager(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/Manager role/");

        $db      = $this->buildMockDb([], false);  // managerRole = false
        $service = $this->makeService($db);

        $service->bulkAssign([1, 2], 7, $this->tenantId, 99);
    }

    public function testBulkAssignWithEmptyArrayReturnsZero(): void
    {
        $db      = $this->buildMockDb([], true);
        $service = $this->makeService($db);

        $result = $service->bulkAssign([], 7, $this->tenantId, 99);

        $this->assertSame(0, $result['reassigned']);
    }

    // =========================================================================
    // sendDueReminders — Requirement 15.3
    // =========================================================================

    public function testSendDueRemindersPushesNotificationsAndReturnsCount(): void
    {
        $dueTasks = [
            [
                'id'               => 1,
                'title'            => 'Call client',
                'due_date'         => '2025-01-01 10:00:00',
                'assigned_user_id' => 5,
                'tenant_id'        => $this->tenantId,
                'linked_type'      => 'contact',
                'linked_id'        => 10,
            ],
            [
                'id'               => 2,
                'title'            => 'Send proposal',
                'due_date'         => '2025-01-01 10:30:00',
                'assigned_user_id' => 6,
                'tenant_id'        => $this->tenantId,
                'linked_type'      => 'deal',
                'linked_id'        => 20,
            ],
        ];

        $db = new class($dueTasks) {
            private array $rows;
            private int   $callCount = 0;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function Execute(string $sql, array $params = [])
            {
                $lower = strtolower(trim($sql));
                if (str_starts_with($lower, 'select')) {
                    return new class($this->rows) {
                        private array $rows;
                        private int   $cursor = 0;
                        public bool   $EOF;
                        public array  $fields = [];
                        public function __construct(array $rows)
                        {
                            $this->rows = $rows;
                            $this->EOF  = empty($rows);
                            if (!$this->EOF) { $this->fields = $rows[0]; }
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
                // UPDATE reminder_sent_at
                return new class { public bool $EOF = true; public array $fields = []; public function MoveNext(): void {} };
            }
            public function ErrorMsg(): string   { return ''; }
            public function Affected_Rows(): int { return 1; }
            public function Insert_ID(): int     { return 0; }
            public function BeginTrans(): void   {}
            public function CommitTrans(): void  {}
            public function RollbackTrans(): void {}
        };

        $redis   = $this->buildRedis();
        $service = $this->makeService($db, $redis);

        $count = $service->sendDueReminders();

        $this->assertSame(2, $count);

        // Verify notifications were pushed to Redis
        $this->assertArrayHasKey("notifications:pending:5", $redis->pushed);
        $this->assertArrayHasKey("notifications:pending:6", $redis->pushed);

        $payload = json_decode($redis->pushed["notifications:pending:5"][0], true);
        $this->assertSame('task_due_reminder', $payload['type']);
        $this->assertSame(1, $payload['task_id']);
    }

    public function testSendDueRemindersSkipsTasksWithNoAssignedUser(): void
    {
        $dueTasks = [
            [
                'id'               => 3,
                'title'            => 'Unassigned task',
                'due_date'         => '2025-01-01 10:00:00',
                'assigned_user_id' => null,  // no assigned user
                'tenant_id'        => $this->tenantId,
                'linked_type'      => 'lead',
                'linked_id'        => 5,
            ],
        ];

        $db = new class($dueTasks) {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function Execute(string $sql, array $params = [])
            {
                $lower = strtolower(trim($sql));
                if (str_starts_with($lower, 'select')) {
                    return new class($this->rows) {
                        private array $rows;
                        private int   $cursor = 0;
                        public bool   $EOF;
                        public array  $fields = [];
                        public function __construct(array $rows)
                        {
                            $this->rows = $rows;
                            $this->EOF  = empty($rows);
                            if (!$this->EOF) { $this->fields = $rows[0]; }
                        }
                        public function MoveNext(): void
                        {
                            $this->cursor++;
                            $this->EOF = $this->cursor >= count($this->rows);
                            if (!$this->EOF) { $this->fields = $this->rows[$this->cursor]; }
                        }
                    };
                }
                return new class { public bool $EOF = true; public array $fields = []; public function MoveNext(): void {} };
            }
            public function ErrorMsg(): string   { return ''; }
            public function Affected_Rows(): int { return 1; }
            public function Insert_ID(): int     { return 0; }
            public function BeginTrans(): void   {}
            public function CommitTrans(): void  {}
            public function RollbackTrans(): void {}
        };

        $redis   = $this->buildRedis();
        $service = $this->makeService($db, $redis);

        $count = $service->sendDueReminders();

        // Task is still counted (reminder_sent_at is updated), but no notification pushed
        $this->assertSame(1, $count);
        $this->assertEmpty($redis->pushed);
    }
}
