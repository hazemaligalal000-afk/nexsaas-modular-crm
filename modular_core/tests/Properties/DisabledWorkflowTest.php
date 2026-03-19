<?php
/**
 * Property 16: Disabled Workflow Not Executed
 *
 * Validates: Requirements 14.9
 *
 * Properties verified:
 *   P16-a  A disabled workflow is never enqueued when its trigger event fires.
 *   P16-b  An enabled workflow IS enqueued when its trigger event fires.
 *   P16-c  Mixed pool: only enabled workflows are enqueued; disabled ones are skipped.
 *   P16-d  Disabling a previously-enabled workflow stops future enqueues.
 *   P16-e  Re-enabling a disabled workflow resumes enqueuing.
 */

declare(strict_types=1);

namespace Tests\Properties;

use CRM\Workflows\WorkflowEngine;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CRM\Workflows\WorkflowEngine
 */
class DisabledWorkflowTest extends TestCase
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
     * Build a mock RabbitMQ publisher that records every published message.
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
     * Build a mock ADOdb connection that returns a configurable list of workflow rows.
     *
     * The DB mock simulates the WHERE is_enabled = TRUE filter that
     * WorkflowEngine::fetchEnabledWorkflows() applies (Req 14.9).
     *
     * @param  array  $workflows  Full list of workflow rows (with is_enabled flag).
     */
    private function buildMockDb(array $workflows): object
    {
        return new class($workflows) {
            private array $workflows;

            public function __construct(array $workflows)
            {
                $this->workflows = $workflows;
            }

            public function Execute(string $sql, array $params = [])
            {
                $lower = strtolower(trim($sql));

                if (str_contains($lower, 'from workflows')) {
                    // Simulate WHERE is_enabled = TRUE (as the real query does)
                    $enabled = array_values(
                        array_filter($this->workflows, fn($w) => (bool) $w['is_enabled'] === true)
                    );
                    return $this->multiRowRs($enabled);
                }

                return $this->emptyRs();
            }

            public function ErrorMsg(): string    { return ''; }
            public function Affected_Rows(): int  { return 0; }
            public function Insert_ID(): int      { return 0; }
            public function BeginTrans(): void    {}
            public function CommitTrans(): void   {}
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
     * Build a minimal workflow row.
     *
     * @param  int    $id
     * @param  bool   $isEnabled
     * @param  string $triggerType
     * @param  string $tenantId
     * @param  string $companyCode
     */
    private function makeWorkflow(
        int    $id,
        bool   $isEnabled,
        string $triggerType,
        string $tenantId,
        string $companyCode = '01'
    ): array {
        return [
            'id'             => $id,
            'name'           => "Workflow $id",
            'module'         => 'CRM',
            'trigger_type'   => $triggerType,
            'trigger_config' => '{}',
            'is_enabled'     => $isEnabled,
            'tenant_id'      => $tenantId,
            'company_code'   => $companyCode,
        ];
    }

    /**
     * Build a minimal event context.
     */
    private function makeContext(string $tenantId, string $companyCode = '01'): array
    {
        return [
            'tenant_id'    => $tenantId,
            'company_code' => $companyCode,
            'user_id'      => 1,
            'record_type'  => 'leads',
            'record_id'    => random_int(1, 9999),
            'event'        => 'record_created',
        ];
    }

    private function makeEngine(array $workflows, object $mq): WorkflowEngine
    {
        return new WorkflowEngine($this->buildMockDb($workflows), $mq);
    }

    // =========================================================================
    // P16-a: A disabled workflow is NEVER enqueued
    // Validates: Requirements 14.9
    // =========================================================================

    /**
     * **Validates: Requirements 14.9**
     *
     * Property: for any disabled workflow, firing its trigger event must
     * result in zero messages published to RabbitMQ.
     *
     * Tests with 60 random disabled workflows across random trigger types.
     */
    public function testDisabledWorkflowIsNeverEnqueued(): void
    {
        $triggerTypes = WorkflowEngine::TRIGGER_TYPES;
        $iterations   = 60;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId    = $this->randomUuid();
            $triggerType = $triggerTypes[array_rand($triggerTypes)];
            $mq          = $this->buildMockRabbitMQ();

            $workflow = $this->makeWorkflow(
                id: random_int(1, 9999),
                isEnabled: false,
                triggerType: $triggerType,
                tenantId: $tenantId
            );

            $engine  = $this->makeEngine([$workflow], $mq);
            $context = $this->makeContext($tenantId);
            $context['event'] = $triggerType;

            $enqueued = $engine->evaluate($triggerType, $context);

            $this->assertSame(
                0,
                $enqueued,
                "Iteration $i: disabled workflow must not be enqueued (trigger=$triggerType)"
            );

            $this->assertEmpty(
                $mq->published,
                "Iteration $i: RabbitMQ must receive no messages for a disabled workflow"
            );
        }
    }

    // =========================================================================
    // P16-b: An enabled workflow IS enqueued when its trigger fires
    // Validates: Requirements 14.9 (contrast case)
    // =========================================================================

    /**
     * **Validates: Requirements 14.9**
     *
     * Property: for any enabled workflow, firing its trigger event must
     * result in exactly one message published to RabbitMQ.
     *
     * Tests with 60 random enabled workflows.
     */
    public function testEnabledWorkflowIsEnqueued(): void
    {
        $triggerTypes = WorkflowEngine::TRIGGER_TYPES;
        $iterations   = 60;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId    = $this->randomUuid();
            $triggerType = $triggerTypes[array_rand($triggerTypes)];
            $mq          = $this->buildMockRabbitMQ();

            $workflow = $this->makeWorkflow(
                id: random_int(1, 9999),
                isEnabled: true,
                triggerType: $triggerType,
                tenantId: $tenantId
            );

            $engine  = $this->makeEngine([$workflow], $mq);
            $context = $this->makeContext($tenantId);
            $context['event'] = $triggerType;

            $enqueued = $engine->evaluate($triggerType, $context);

            $this->assertSame(
                1,
                $enqueued,
                "Iteration $i: enabled workflow must be enqueued exactly once (trigger=$triggerType)"
            );

            $this->assertCount(
                1,
                $mq->published,
                "Iteration $i: RabbitMQ must receive exactly one message for an enabled workflow"
            );

            $this->assertSame(
                (int) $workflow['id'],
                $mq->published[0]['payload']['workflow_id'],
                "Iteration $i: published payload must reference the correct workflow_id"
            );
        }
    }

    // =========================================================================
    // P16-c: Mixed pool — only enabled workflows are enqueued
    // Validates: Requirements 14.9
    // =========================================================================

    /**
     * **Validates: Requirements 14.9**
     *
     * Property: given a pool of N workflows where K are enabled and (N-K)
     * are disabled, exactly K messages are published to RabbitMQ.
     *
     * Tests with 50 random mixed pools.
     */
    public function testOnlyEnabledWorkflowsEnqueuedFromMixedPool(): void
    {
        $triggerType = 'record_created';
        $iterations  = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId = $this->randomUuid();
            $total    = random_int(2, 10);
            $mq       = $this->buildMockRabbitMQ();

            // Randomly assign enabled/disabled to each workflow
            $workflows      = [];
            $enabledCount   = 0;
            $disabledIds    = [];

            for ($j = 1; $j <= $total; $j++) {
                $isEnabled = (bool) random_int(0, 1);
                if ($isEnabled) {
                    $enabledCount++;
                } else {
                    $disabledIds[] = $j;
                }
                $workflows[] = $this->makeWorkflow(
                    id: $j,
                    isEnabled: $isEnabled,
                    triggerType: $triggerType,
                    tenantId: $tenantId
                );
            }

            $engine  = $this->makeEngine($workflows, $mq);
            $context = $this->makeContext($tenantId);

            $enqueued = $engine->evaluate($triggerType, $context);

            $this->assertSame(
                $enabledCount,
                $enqueued,
                "Iteration $i: expected $enabledCount enqueued (total=$total, disabled=" .
                count($disabledIds) . ")"
            );

            $this->assertCount(
                $enabledCount,
                $mq->published,
                "Iteration $i: RabbitMQ message count must equal enabled workflow count"
            );

            // Verify no disabled workflow ID appears in published payloads
            $publishedIds = array_column(array_column($mq->published, 'payload'), 'workflow_id');
            foreach ($disabledIds as $disabledId) {
                $this->assertNotContains(
                    $disabledId,
                    $publishedIds,
                    "Iteration $i: disabled workflow $disabledId must not appear in published messages"
                );
            }
        }
    }

    // =========================================================================
    // P16-d: Disabling a workflow stops future enqueues
    // Validates: Requirements 14.9
    // =========================================================================

    /**
     * **Validates: Requirements 14.9**
     *
     * Property: a workflow that was enabled on the first evaluate() call
     * and disabled before the second call must produce zero enqueues on
     * the second call.
     *
     * Tests with 40 random workflows.
     */
    public function testDisablingWorkflowStopsFutureEnqueues(): void
    {
        $triggerType = 'record_created';
        $iterations  = 40;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId   = $this->randomUuid();
            $workflowId = random_int(1, 9999);
            $context    = $this->makeContext($tenantId);

            // --- First call: workflow is enabled ---
            $mq1      = $this->buildMockRabbitMQ();
            $enabled  = $this->makeWorkflow($workflowId, true, $triggerType, $tenantId);
            $engine1  = $this->makeEngine([$enabled], $mq1);

            $enqueued1 = $engine1->evaluate($triggerType, $context);

            $this->assertSame(1, $enqueued1, "Iteration $i: enabled workflow must enqueue once");
            $this->assertCount(1, $mq1->published, "Iteration $i: one MQ message expected when enabled");

            // --- Second call: same workflow is now disabled ---
            $mq2      = $this->buildMockRabbitMQ();
            $disabled = $this->makeWorkflow($workflowId, false, $triggerType, $tenantId);
            $engine2  = $this->makeEngine([$disabled], $mq2);

            $enqueued2 = $engine2->evaluate($triggerType, $context);

            $this->assertSame(0, $enqueued2, "Iteration $i: disabled workflow must not enqueue");
            $this->assertEmpty($mq2->published, "Iteration $i: no MQ messages expected when disabled");
        }
    }

    // =========================================================================
    // P16-e: Re-enabling a disabled workflow resumes enqueuing
    // Validates: Requirements 14.9
    // =========================================================================

    /**
     * **Validates: Requirements 14.9**
     *
     * Property: a workflow that was disabled on the first evaluate() call
     * and re-enabled before the second call must produce exactly one enqueue
     * on the second call.
     *
     * Tests with 40 random workflows.
     */
    public function testReEnablingWorkflowResumesEnqueuing(): void
    {
        $triggerType = 'record_created';
        $iterations  = 40;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId   = $this->randomUuid();
            $workflowId = random_int(1, 9999);
            $context    = $this->makeContext($tenantId);

            // --- First call: workflow is disabled ---
            $mq1      = $this->buildMockRabbitMQ();
            $disabled = $this->makeWorkflow($workflowId, false, $triggerType, $tenantId);
            $engine1  = $this->makeEngine([$disabled], $mq1);

            $enqueued1 = $engine1->evaluate($triggerType, $context);

            $this->assertSame(0, $enqueued1, "Iteration $i: disabled workflow must not enqueue");
            $this->assertEmpty($mq1->published, "Iteration $i: no MQ messages expected when disabled");

            // --- Second call: same workflow is now re-enabled ---
            $mq2     = $this->buildMockRabbitMQ();
            $enabled = $this->makeWorkflow($workflowId, true, $triggerType, $tenantId);
            $engine2 = $this->makeEngine([$enabled], $mq2);

            $enqueued2 = $engine2->evaluate($triggerType, $context);

            $this->assertSame(1, $enqueued2, "Iteration $i: re-enabled workflow must enqueue once");
            $this->assertCount(1, $mq2->published, "Iteration $i: one MQ message expected after re-enable");

            $this->assertSame(
                $workflowId,
                $mq2->published[0]['payload']['workflow_id'],
                "Iteration $i: published payload must reference the correct workflow_id after re-enable"
            );
        }
    }
}
