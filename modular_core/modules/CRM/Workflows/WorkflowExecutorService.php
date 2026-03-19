<?php
/**
 * CRM/Workflows/WorkflowExecutorService.php
 *
 * Bridges the PHP WorkflowEngine (which enqueues) with the Python
 * Celery WorkflowExecutor (which executes).
 *
 * Responsibilities:
 *   - enqueueExecution(): publish a workflow.execute message to RabbitMQ
 *   - getExecutionHistory(): return execution logs with per-step details
 *
 * Requirements: 14.3, 14.5, 14.6, 14.7
 */

declare(strict_types=1);

namespace CRM\Workflows;

use Core\BaseService;

class WorkflowExecutorService extends BaseService
{
    private const EXCHANGE    = 'crm.events';
    private const ROUTING_KEY = 'workflow.execute';

    private string $tenantId;
    private string $companyCode;
    private object $rabbitMQ;

    /**
     * @param \ADOConnection $db
     * @param object         $rabbitMQ  Publisher with publish(exchange, routingKey, payload)
     * @param string         $tenantId
     * @param string         $companyCode
     */
    public function __construct($db, object $rabbitMQ, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->rabbitMQ    = $rabbitMQ;
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    // -------------------------------------------------------------------------
    // Enqueue — Requirement 14.4
    // -------------------------------------------------------------------------

    /**
     * Publish a workflow execution job to RabbitMQ.
     *
     * The Celery WorkflowExecutor consumer picks this up and runs the
     * workflow's actions sequentially.
     *
     * @param  int    $workflowId  ID of the workflow to execute.
     * @param  array  $context     Trigger context (tenant_id, record_type, record_id, event, …).
     *
     * @throws \InvalidArgumentException if workflowId is invalid.
     * @throws \RuntimeException         if the publish fails.
     */
    public function enqueueExecution(int $workflowId, array $context): void
    {
        if ($workflowId <= 0) {
            throw new \InvalidArgumentException('WorkflowExecutorService: workflowId must be a positive integer.');
        }

        $payload = [
            'workflow_id'   => $workflowId,
            'tenant_id'     => $this->tenantId,
            'company_code'  => $this->companyCode,
            'trigger_event' => $context['event'] ?? '',
            'context'       => $context,
            'enqueued_at'   => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ];

        $this->rabbitMQ->publish(self::EXCHANGE, self::ROUTING_KEY, $payload);
    }

    // -------------------------------------------------------------------------
    // Execution history — Requirement 14.7
    // -------------------------------------------------------------------------

    /**
     * Return execution logs for a workflow, including per-step details.
     *
     * @param  int    $workflowId
     * @param  int    $limit
     * @param  int    $offset
     * @return array  Array of execution rows, each with a 'steps' sub-array.
     *
     * @throws \RuntimeException on DB error.
     */
    public function getExecutionHistory(int $workflowId, int $limit = 20, int $offset = 0): array
    {
        // Fetch executions scoped to tenant + workflow
        $rs = $this->db->Execute(
            'SELECT id, workflow_id, status, trigger_event, context,
                    started_at, completed_at, created_at, updated_at
               FROM workflow_executions
              WHERE workflow_id  = ?
                AND tenant_id   = ?
                AND company_code = ?
                AND deleted_at  IS NULL
              ORDER BY created_at DESC
              LIMIT ? OFFSET ?',
            [$workflowId, $this->tenantId, $this->companyCode, $limit, $offset]
        );

        if ($rs === false) {
            throw new \RuntimeException(
                'WorkflowExecutorService::getExecutionHistory failed: ' . $this->db->ErrorMsg()
            );
        }

        $executions = [];
        while (!$rs->EOF) {
            $row = $rs->fields;
            $row['steps'] = $this->loadSteps((int) $row['id']);
            $executions[] = $row;
            $rs->MoveNext();
        }

        return $executions;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load all steps for a given execution, ordered by action_order.
     *
     * @param  int   $executionId
     * @return array
     */
    private function loadSteps(int $executionId): array
    {
        $rs = $this->db->Execute(
            'SELECT id, action_id, action_order, action_type, status,
                    result, error_message, retry_count, started_at, completed_at
               FROM workflow_execution_steps
              WHERE execution_id = ?
                AND tenant_id   = ?
                AND deleted_at  IS NULL
              ORDER BY action_order ASC',
            [$executionId, $this->tenantId]
        );

        if ($rs === false) {
            return [];
        }

        $steps = [];
        while (!$rs->EOF) {
            $steps[] = $rs->fields;
            $rs->MoveNext();
        }

        return $steps;
    }
}
