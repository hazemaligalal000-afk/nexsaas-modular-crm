<?php
/**
 * Unit/ActivityServiceTest.php
 *
 * Unit tests for CRM\Tasks\ActivityService.
 *
 * Tests cover:
 *  - log: valid data creates activity and returns row
 *  - log: invalid type throws InvalidArgumentException
 *  - log: invalid linked_record_type throws InvalidArgumentException
 *  - getTimeline: returns activities ordered by activity_date DESC
 *  - getTimeline: invalid recordType throws InvalidArgumentException
 *  - logCompletion: creates task-type activity linked to the task's record
 *  - logCompletion: throws when task not found
 *
 * Requirements: 15.2, 15.5
 */

declare(strict_types=1);

namespace Tests\Unit;

use CRM\Tasks\ActivityService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CRM\Tasks\ActivityService
 */
class ActivityServiceTest extends TestCase
{
    private string $tenantId    = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    private string $companyCode = '01';

    // -------------------------------------------------------------------------
    // Mock builders
    // -------------------------------------------------------------------------

    private function buildActivityRow(array $overrides = []): array
    {
        return array_merge([
            'id'               => 1,
            'type'             => 'call',
            'subject'          => 'Discovery call',
            'body'             => 'Discussed requirements',
            'outcome'          => 'Positive',
            'duration_minutes' => 30,
            'activity_date'    => '2025-01-15 10:00:00',
            'linked_type'      => 'contact',
            'linked_id'        => 10,
            'task_id'          => null,
            'performed_by'     => 1,
            'created_by'       => 1,
            'created_at'       => '2025-01-15 10:00:00',
            'updated_at'       => '2025-01-15 10:00:00',
        ], $overrides);
    }

    private function buildTaskRow(array $overrides = []): array
    {
        return array_merge([
            'id'          => 5,
            'title'       => 'Follow up',
            'linked_type' => 'contact',
            'linked_id'   => 10,
        ], $overrides);
    }

    /**
     * Build a mock DB that returns given rows for SELECT and succeeds on INSERT.
     */
    private function buildMockDb(array $activityRow = [], ?array $taskRow = null): object
    {
        return new class($activityRow, $taskRow) {
            private array  $activityRow;
            private ?array $taskRow;

            public function __construct(array $activityRow, ?array $taskRow)
            {
                $this->activityRow = $activityRow;
                $this->taskRow     = $taskRow;
            }

            public function Execute(string $sql, array $params = [])
            {
                $lower = strtolower(trim($sql));

                if (str_starts_with($lower, 'insert')) {
                    return $this->singleRowRs(['id' => 1]);
                }

                if (str_contains($lower, 'from tasks')) {
                    return $this->taskRow !== null
                        ? $this->singleRowRs($this->taskRow)
                        : $this->emptyRs();
                }

                if (str_contains($lower, 'from activities')) {
                    return empty($this->activityRow)
                        ? $this->emptyRs()
                        : $this->singleRowRs($this->activityRow);
                }

                return $this->emptyRs();
            }

            public function ErrorMsg(): string   { return ''; }
            public function Affected_Rows(): int { return 1; }
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

    private function makeService(object $db): ActivityService
    {
        return new ActivityService($db, $this->tenantId, $this->companyCode);
    }

    // =========================================================================
    // log — Requirement 15.2
    // =========================================================================

    public function testLogCreatesActivityAndReturnsRow(): void
    {
        $activityRow = $this->buildActivityRow();
        $db          = $this->buildMockDb($activityRow);
        $service     = $this->makeService($db);

        $result = $service->log([
            'type'               => 'call',
            'subject'            => 'Discovery call',
            'linked_record_type' => 'contact',
            'linked_record_id'   => 10,
            'performed_by'       => 1,
        ]);

        $this->assertSame('call', $result['type']);
        $this->assertSame('Discovery call', $result['subject']);
    }

    public function testLogThrowsOnInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/type/i");

        $service = $this->makeService($this->buildMockDb());
        $service->log([
            'type'               => 'sms',  // invalid
            'linked_record_type' => 'contact',
            'linked_record_id'   => 10,
        ]);
    }

    public function testLogThrowsOnInvalidLinkedRecordType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/linked_record_type/i");

        $service = $this->makeService($this->buildMockDb());
        $service->log([
            'type'               => 'call',
            'linked_record_type' => 'invoice',  // invalid
            'linked_record_id'   => 10,
        ]);
    }

    public function testLogAcceptsAllValidTypes(): void
    {
        $validTypes = ['call', 'email', 'meeting', 'note', 'task'];

        foreach ($validTypes as $type) {
            $activityRow = $this->buildActivityRow(['type' => $type]);
            $db          = $this->buildMockDb($activityRow);
            $service     = $this->makeService($db);

            $result = $service->log([
                'type'               => $type,
                'linked_record_type' => 'contact',
                'linked_record_id'   => 10,
            ]);

            $this->assertSame($type, $result['type'], "Type '{$type}' should be accepted");
        }
    }

    // =========================================================================
    // getTimeline — Requirement 15.5
    // =========================================================================

    public function testGetTimelineReturnsActivities(): void
    {
        $rows = [
            $this->buildActivityRow(['id' => 2, 'activity_date' => '2025-01-15 12:00:00']),
            $this->buildActivityRow(['id' => 1, 'activity_date' => '2025-01-15 10:00:00']),
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

        $service  = $this->makeService($db);
        $timeline = $service->getTimeline('contact', 10, $this->tenantId);

        $this->assertCount(2, $timeline);
        // First entry should be the most recent (DB returns DESC order)
        $this->assertSame(2, (int) $timeline[0]['id']);
    }

    public function testGetTimelineThrowsOnInvalidRecordType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/recordType/i");

        $service = $this->makeService($this->buildMockDb());
        $service->getTimeline('invoice', 1, $this->tenantId);
    }

    // =========================================================================
    // logCompletion — Requirement 15.5
    // =========================================================================

    public function testLogCompletionCreatesTaskActivity(): void
    {
        $taskRow     = $this->buildTaskRow();
        $activityRow = $this->buildActivityRow(['type' => 'task', 'task_id' => 5]);
        $db          = $this->buildMockDb($activityRow, $taskRow);
        $service     = $this->makeService($db);

        $result = $service->logCompletion(5, $this->tenantId, 1);

        $this->assertSame('task', $result['type']);
    }

    public function testLogCompletionThrowsWhenTaskNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Task 999 not found/");

        $db      = $this->buildMockDb([], null);  // no task row
        $service = $this->makeService($db);

        $service->logCompletion(999, $this->tenantId, 1);
    }
}
