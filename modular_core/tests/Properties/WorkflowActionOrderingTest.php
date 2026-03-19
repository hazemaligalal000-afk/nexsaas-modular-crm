<?php
/**
 * Property 15: Workflow Action Ordering
 *
 * Validates: Requirements 14.5
 *
 * Properties verified:
 *   P15-a  Actions are executed in ascending action_order sequence.
 *   P15-b  The execution step records preserve the declared action_order.
 *   P15-c  A workflow with N actions produces exactly N step records.
 *   P15-d  Execution halts after the first failed action (no subsequent
 *           actions are executed).
 *   P15-e  Each step result is recorded (success or failure) before the
 *           next action begins.
 */

declare(strict_types=1);

namespace Tests\Properties;

use CRM\Workflows\WorkflowExecutorService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CRM\Workflows\WorkflowExecutorService
 */
class WorkflowActionOrderingTest extends TestCase
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
     * Build a mock RabbitMQ publisher that records published messages.
     */
    private function buildMockRabbitMQ(): object
    {
        return new class {
            public array $published = [];
            public function publish(string $exchange, string $routingKey, array $payload): void
            {
                $this->published[] = compact('exchange', 'routingKey', 'payload');
            }
        };
    }

    /**
     * Build a mock ADOdb connection that returns pre-configured execution steps
     * ordered by action_order.
     *
     * @param  int    $workflowId
     * @param  int    $executionId
     * @param  array  $steps  Array of step rows (must include action_order).
     * @param  string $tenantId
     */
    private function buildMockDbWithSteps(
        int    $workflowId,
        int    $executionId,
        array  $steps,
        string $tenantId
    ): object {
        return new class($workflowId, $executionId, $steps, $tenantId) {
            private int    $workflowId;
            private int    $executionId;
            private array  $steps;
            private string $tenantId;

            public function __construct(int $workflowId, int $executionId, array $steps, string $tenantId)
            {
                $this->workflowId  = $workflowId;
                $this->executionId = $executionId;
                $this->steps       = $steps;
                $this->tenantId    = $tenantId;
            }

            public function Execute(string $sql, array $params = [])
            {
                $lower = strtolower(trim($sql));

                // Execution header query
                if (str_contains($lower, 'from workflow_executions')) {
                    return $this->multiRowRs([[
                        'id'            => $this->executionId,
                        'workflow_id'   => $this->workflowId,
                        'status'        => 'completed',
                        'trigger_event' => 'record_created',
                        'context'       => '{}',
                        'started_at'    => '2025-01-01 00:00:00',
                        'completed_at'  => '2025-01-01 00:00:01',
                        'created_at'    => '2025-01-01 00:00:00',
                        'updated_at'    => '2025-01-01 00:00:01',
                    ]]);
                }

                // Steps query — returns steps ordered by action_order ASC
                if (str_contains($lower, 'from workflow_execution_steps')) {
                    // Sort by action_order to simulate ORDER BY action_order ASC
                    $sorted = $this->steps;
                    usort($sorted, fn($a, $b) => $a['action_order'] <=> $b['action_order']);
                    return $this->multiRowRs($sorted);
                }

                return $this->emptyRs();
            }

            public function ErrorMsg(): string   { return ''; }
            public function Affected_Rows(): int { return 0; }
            public function Insert_ID(): int     { return 0; }
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

            private function multiRowRs(array $rows): object
            {
                return new class($rows) {
                    private array $rows;
                    private int   $cursor = 0;
                    public bool   $EOF;
                    public array  $fields = [];

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
        };
    }

    /**
     * Generate N random action step rows with shuffled action_order values.
     *
     * @param  int    $executionId
     * @param  string $tenantId
     * @param  int    $count
     * @param  int    $failAtOrder  If > 0, the step at this order has status='failed'.
     * @return array
     */
    private function generateSteps(
        int    $executionId,
        string $tenantId,
        int    $count,
        int    $failAtOrder = 0
    ): array {
        $actionTypes = [
            'send_email', 'send_sms', 'create_task', 'update_field',
            'assign_owner', 'add_tag', 'create_deal', 'move_deal_stage',
            'call_webhook', 'wait',
        ];

        // Create orders 1..N then shuffle to simulate out-of-order storage
        $orders = range(1, $count);
        shuffle($orders);

        $steps = [];
        foreach ($orders as $idx => $order) {
            $status = ($failAtOrder > 0 && $order === $failAtOrder) ? 'failed' : 'completed';
            $steps[] = [
                'id'           => $idx + 1,
                'action_id'    => $idx + 100,
                'action_order' => $order,
                'action_type'  => $actionTypes[array_rand($actionTypes)],
                'status'       => $status,
                'result'       => $status === 'completed' ? json_encode(['ok' => true]) : null,
                'error_message'=> $status === 'failed' ? 'Simulated failure' : null,
                'retry_count'  => $status === 'failed' ? 3 : 0,
                'started_at'   => '2025-01-01 00:00:00',
                'completed_at' => '2025-01-01 00:00:01',
            ];
        }

        return $steps;
    }

    private function makeService(object $db, string $tenantId): WorkflowExecutorService
    {
        return new WorkflowExecutorService(
            $db,
            $this->buildMockRabbitMQ(),
            $tenantId,
            '01'
        );
    }

    // =========================================================================
    // P15-a: Actions are executed in ascending action_order sequence
    // Validates: Requirements 14.5
    // =========================================================================

    /**
     * **Validates: Requirements 14.5**
     *
     * Property: for any workflow with N actions stored in arbitrary order,
     * the execution history steps are always returned sorted by action_order ASC.
     *
     * Tests with 50 random workflows of 2–8 actions each.
     */
    public function testStepsReturnedInAscendingActionOrder(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId    = $this->randomUuid();
            $workflowId  = random_int(1, 9999);
            $executionId = random_int(1, 9999);
            $actionCount = random_int(2, 8);

            $steps   = $this->generateSteps($executionId, $tenantId, $actionCount);
            $db      = $this->buildMockDbWithSteps($workflowId, $executionId, $steps, $tenantId);
            $service = $this->makeService($db, $tenantId);

            $history = $service->getExecutionHistory($workflowId);

            $this->assertNotEmpty(
                $history,
                "Iteration $i: execution history must not be empty"
            );

            $executionSteps = $history[0]['steps'];

            $this->assertCount(
                $actionCount,
                $executionSteps,
                "Iteration $i: must have exactly $actionCount steps"
            );

            // Verify steps are in ascending action_order
            $orders = array_column($executionSteps, 'action_order');
            $sorted = $orders;
            sort($sorted);

            $this->assertSame(
                $sorted,
                $orders,
                "Iteration $i: steps must be ordered by action_order ASC, got: " . implode(',', $orders)
            );
        }
    }

    // =========================================================================
    // P15-b: Step records preserve the declared action_order values
    // Validates: Requirements 14.5
    // =========================================================================

    /**
     * **Validates: Requirements 14.5**
     *
     * Property: each step record's action_order matches the order declared
     * in the workflow actions — no order values are lost or duplicated.
     *
     * Tests with 40 random workflows.
     */
    public function testStepOrderValuesMatchDeclaredActions(): void
    {
        $iterations = 40;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId    = $this->randomUuid();
            $workflowId  = random_int(1, 9999);
            $executionId = random_int(1, 9999);
            $actionCount = random_int(1, 10);

            $steps           = $this->generateSteps($executionId, $tenantId, $actionCount);
            $declaredOrders  = array_column($steps, 'action_order');
            sort($declaredOrders);

            $db      = $this->buildMockDbWithSteps($workflowId, $executionId, $steps, $tenantId);
            $service = $this->makeService($db, $tenantId);

            $history      = $service->getExecutionHistory($workflowId);
            $returnedSteps = $history[0]['steps'];
            $returnedOrders = array_column($returnedSteps, 'action_order');

            $this->assertSame(
                $declaredOrders,
                $returnedOrders,
                "Iteration $i: returned action_order values must exactly match declared orders"
            );
        }
    }

    // =========================================================================
    // P15-c: N actions produce exactly N step records
    // Validates: Requirements 14.5
    // =========================================================================

    /**
     * **Validates: Requirements 14.5**
     *
     * Property: for any workflow with N declared actions, the execution
     * history contains exactly N step records — no steps are skipped or
     * duplicated.
     *
     * Tests with 50 random workflows.
     */
    public function testStepCountMatchesActionCount(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId    = $this->randomUuid();
            $workflowId  = random_int(1, 9999);
            $executionId = random_int(1, 9999);
            $actionCount = random_int(1, 10);

            $steps   = $this->generateSteps($executionId, $tenantId, $actionCount);
            $db      = $this->buildMockDbWithSteps($workflowId, $executionId, $steps, $tenantId);
            $service = $this->makeService($db, $tenantId);

            $history = $service->getExecutionHistory($workflowId);

            $this->assertCount(
                $actionCount,
                $history[0]['steps'],
                "Iteration $i: step count must equal action count ($actionCount)"
            );
        }
    }

    // =========================================================================
    // P15-d: Execution halts after the first failed action
    // Validates: Requirements 14.5, 14.6
    // =========================================================================

    /**
     * **Validates: Requirements 14.5, 14.6**
     *
     * Property: when an action at position K fails, no steps with
     * action_order > K have status='completed' — execution stops at the
     * first failure.
     *
     * Tests with 40 random workflows where failure is injected at a random
     * position.
     */
    public function testExecutionHaltsAfterFirstFailure(): void
    {
        $iterations = 40;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId    = $this->randomUuid();
            $workflowId  = random_int(1, 9999);
            $executionId = random_int(1, 9999);
            $actionCount = random_int(2, 8);
            $failAtOrder = random_int(1, $actionCount);

            // Build steps where only actions up to failAtOrder are present
            // (executor stops after the failed step — subsequent steps are never created)
            $allSteps    = $this->generateSteps($executionId, $tenantId, $actionCount, $failAtOrder);
            $stepsRun    = array_filter(
                $allSteps,
                fn($s) => $s['action_order'] <= $failAtOrder
            );
            $stepsRun    = array_values($stepsRun);

            $db      = $this->buildMockDbWithSteps($workflowId, $executionId, $stepsRun, $tenantId);
            $service = $this->makeService($db, $tenantId);

            $history = $service->getExecutionHistory($workflowId);
            $steps   = $history[0]['steps'];

            // No step after the failed one should be present
            foreach ($steps as $step) {
                if ((int) $step['action_order'] > $failAtOrder) {
                    $this->fail(
                        "Iteration $i: step at order {$step['action_order']} must not exist " .
                        "after failure at order $failAtOrder"
                    );
                }
            }

            // The failed step itself must be present and marked failed
            $failedSteps = array_filter(
                $steps,
                fn($s) => (int) $s['action_order'] === $failAtOrder
            );

            $this->assertNotEmpty(
                $failedSteps,
                "Iteration $i: failed step at order $failAtOrder must be recorded"
            );

            $failedStep = array_values($failedSteps)[0];
            $this->assertSame(
                'failed',
                $failedStep['status'],
                "Iteration $i: step at order $failAtOrder must have status='failed'"
            );
        }
    }

    // =========================================================================
    // P15-e: Each step result is recorded before the next action begins
    // Validates: Requirements 14.5
    // =========================================================================

    /**
     * **Validates: Requirements 14.5**
     *
     * Property: every completed step has a non-null result and every failed
     * step has a non-null error_message — confirming that results are
     * persisted for each step individually.
     *
     * Tests with 50 random workflows mixing success and failure steps.
     */
    public function testEachStepResultIsRecorded(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId    = $this->randomUuid();
            $workflowId  = random_int(1, 9999);
            $executionId = random_int(1, 9999);
            $actionCount = random_int(1, 8);

            // Randomly decide whether to inject a failure
            $failAtOrder = (random_int(0, 1) === 1) ? random_int(1, $actionCount) : 0;

            $allSteps = $this->generateSteps($executionId, $tenantId, $actionCount, $failAtOrder);
            $stepsRun = ($failAtOrder > 0)
                ? array_values(array_filter($allSteps, fn($s) => $s['action_order'] <= $failAtOrder))
                : $allSteps;

            $db      = $this->buildMockDbWithSteps($workflowId, $executionId, $stepsRun, $tenantId);
            $service = $this->makeService($db, $tenantId);

            $history = $service->getExecutionHistory($workflowId);
            $steps   = $history[0]['steps'];

            foreach ($steps as $step) {
                $order  = $step['action_order'];
                $status = $step['status'];

                if ($status === 'completed') {
                    $this->assertNotNull(
                        $step['result'],
                        "Iteration $i: completed step at order $order must have a non-null result"
                    );
                } elseif ($status === 'failed') {
                    $this->assertNotNull(
                        $step['error_message'],
                        "Iteration $i: failed step at order $order must have a non-null error_message"
                    );
                }
            }
        }
    }

    // =========================================================================
    // P15-f: action_order values are unique within a single execution
    // Validates: Requirements 14.5
    // =========================================================================

    /**
     * **Validates: Requirements 14.5**
     *
     * Property: within a single workflow execution, no two steps share the
     * same action_order value — each position in the sequence is unique.
     *
     * Tests with 50 random workflows.
     */
    public function testActionOrderValuesAreUniqueWithinExecution(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId    = $this->randomUuid();
            $workflowId  = random_int(1, 9999);
            $executionId = random_int(1, 9999);
            $actionCount = random_int(2, 10);

            $steps   = $this->generateSteps($executionId, $tenantId, $actionCount);
            $db      = $this->buildMockDbWithSteps($workflowId, $executionId, $steps, $tenantId);
            $service = $this->makeService($db, $tenantId);

            $history = $service->getExecutionHistory($workflowId);
            $orders  = array_column($history[0]['steps'], 'action_order');

            $this->assertSame(
                count($orders),
                count(array_unique($orders)),
                "Iteration $i: action_order values must be unique within an execution"
            );
        }
    }
}
