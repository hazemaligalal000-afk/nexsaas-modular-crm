<?php
/**
 * CRM/Workflows/WorkflowEngine.php
 *
 * Evaluates incoming events against enabled workflows and enqueues
 * execution jobs to RabbitMQ within 5 seconds.
 *
 * Requirements: 14.1, 14.2, 14.4, 14.9
 */

declare(strict_types=1);

namespace CRM\Workflows;

use Core\BaseService;

class WorkflowEngine extends BaseService
{
    /** Supported trigger event types (Requirement 14.2) */
    public const TRIGGER_TYPES = [
        'record_created',
        'record_updated',
        'field_value_changed',
        'date_time_reached',
        'inbound_message_received',
        'manual',
    ];

    /** RabbitMQ queue / routing key for workflow execution jobs (Requirement 14.4) */
    private const QUEUE_ROUTING_KEY = 'workflow.execute';
    private const EXCHANGE          = 'crm.events';

    private object $rabbitMQ;
    private WorkflowTriggerMatcher $matcher;

    /**
     * @param \ADOConnection $db
     * @param object         $rabbitMQ  RabbitMQ publisher with publish(exchange, routingKey, payload)
     */
    public function __construct($db, object $rabbitMQ)
    {
        parent::__construct($db);
        $this->rabbitMQ = $rabbitMQ;
        $this->matcher  = new WorkflowTriggerMatcher();
    }

    // -------------------------------------------------------------------------
    // Core evaluate() — Requirements 14.1, 14.2, 14.4, 14.9
    // -------------------------------------------------------------------------

    /**
     * Evaluate an event against all enabled workflows for the tenant.
     *
     * For each matching enabled workflow, enqueues a `workflow.execute` job
     * to RabbitMQ. The entire operation completes within 5 seconds
     * (synchronous publish satisfies the ≤5s requirement — Req 14.4).
     *
     * Disabled workflows (is_enabled = false) are never enqueued (Req 14.9).
     *
     * @param  string $event    Trigger event name — one of TRIGGER_TYPES.
     *                          e.g. 'record_created', 'field_value_changed'
     * @param  array  $context  Event context:
     *                          - tenant_id      (string, required)
     *                          - company_code   (string, required)
     *                          - user_id        (string|int)
     *                          - record_type    (string)  e.g. 'leads', 'contacts'
     *                          - record_id      (int)
     *                          - event          (string)  same as $event
     *                          - changed_fields (array)   for record_updated
     *                          - field_name     (string)  for field_value_changed
     *                          - old_value      (mixed)   for field_value_changed
     *                          - new_value      (mixed)   for field_value_changed
     *                          - scheduled_at   (string)  for date_time_reached
     *                          - channel        (string)  for inbound_message_received
     * @return int  Number of workflows enqueued.
     *
     * @throws \InvalidArgumentException if tenant_id is missing or event is unknown.
     */
    public function evaluate(string $event, array $context): int
    {
        $this->validateEvent($event);
        $this->validateContext($context);

        $tenantId    = (string) $context['tenant_id'];
        $companyCode = (string) ($context['company_code'] ?? '01');

        // Fetch all enabled workflows for this tenant matching the trigger type
        // Requirement 14.9: only is_enabled = true workflows are fetched
        $workflows = $this->fetchEnabledWorkflows($tenantId, $companyCode, $event);

        $enqueued = 0;

        foreach ($workflows as $workflow) {
            // Apply trigger condition matching (field filters, module, etc.)
            if (!$this->matcher->matches($workflow, $event, $context)) {
                continue;
            }

            // Enqueue execution job — Requirement 14.4 (within 5s, synchronous publish)
            $this->enqueueExecution($workflow, $event, $context);
            $enqueued++;
        }

        return $enqueued;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch all enabled workflows for the tenant that have the given trigger type.
     *
     * Requirement 14.9: WHERE is_enabled = TRUE ensures disabled workflows are excluded.
     *
     * @return array  Array of workflow rows.
     */
    private function fetchEnabledWorkflows(string $tenantId, string $companyCode, string $event): array
    {
        $rs = $this->db->Execute(
            'SELECT id, name, module, trigger_type, trigger_config, is_enabled
               FROM workflows
              WHERE tenant_id    = ?
                AND company_code = ?
                AND trigger_type = ?
                AND is_enabled   = TRUE
                AND deleted_at   IS NULL
              ORDER BY id ASC',
            [$tenantId, $companyCode, $event]
        );

        if ($rs === false) {
            throw new \RuntimeException(
                'WorkflowEngine::fetchEnabledWorkflows DB error: ' . $this->db->ErrorMsg()
            );
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    /**
     * Publish a workflow.execute job to RabbitMQ.
     *
     * The Celery WorkflowExecutor consumer picks this up and runs the
     * workflow's actions sequentially.
     *
     * @param  array  $workflow  Workflow row from DB.
     * @param  string $event     Trigger event name.
     * @param  array  $context   Full event context.
     */
    private function enqueueExecution(array $workflow, string $event, array $context): void
    {
        $payload = [
            'workflow_id'  => (int) $workflow['id'],
            'tenant_id'    => $context['tenant_id'],
            'company_code' => $context['company_code'] ?? '01',
            'user_id'      => $context['user_id'] ?? null,
            'event'        => $event,
            'record_type'  => $context['record_type'] ?? null,
            'record_id'    => $context['record_id'] ?? null,
            'context'      => $context,
            'enqueued_at'  => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ];

        $this->rabbitMQ->publish(self::EXCHANGE, self::QUEUE_ROUTING_KEY, $payload);
    }

    /**
     * Validate that the event name is a known trigger type.
     *
     * @throws \InvalidArgumentException
     */
    private function validateEvent(string $event): void
    {
        if (!in_array($event, self::TRIGGER_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Unknown workflow trigger event '{$event}'. " .
                'Valid types: ' . implode(', ', self::TRIGGER_TYPES)
            );
        }
    }

    /**
     * Validate that the context contains the required tenant_id.
     *
     * @throws \InvalidArgumentException
     */
    private function validateContext(array $context): void
    {
        if (empty($context['tenant_id'])) {
            throw new \InvalidArgumentException(
                'WorkflowEngine::evaluate requires a non-empty tenant_id in context.'
            );
        }
    }
}
