<?php
/**
 * CRM/Workflows/WorkflowService.php
 *
 * CRUD + enable/disable/clone operations for Workflow records.
 * Full action-step logic is handled by the Python WorkflowExecutor (task 15.2).
 *
 * Requirements: 14.1, 14.8, 14.9
 */

declare(strict_types=1);

namespace CRM\Workflows;

use Core\BaseService;

class WorkflowService extends BaseService
{
    private string $tenantId;
    private string $companyCode;

    public function __construct($db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    // -------------------------------------------------------------------------
    // Find single
    // -------------------------------------------------------------------------

    public function find(int $id): ?array
    {
        $rs = $this->db->Execute(
            'SELECT w.*, COALESCE(
                json_agg(
                    json_build_object(
                        \'id\', a.id,
                        \'action_order\', a.action_order,
                        \'action_type\', a.action_type,
                        \'action_config\', a.action_config
                    ) ORDER BY a.action_order
                ) FILTER (WHERE a.id IS NOT NULL),
                \'[]\'
            ) AS actions
             FROM workflows w
             LEFT JOIN workflow_actions a
               ON a.workflow_id = w.id AND a.tenant_id = w.tenant_id AND a.deleted_at IS NULL
            WHERE w.id = ? AND w.tenant_id = ? AND w.company_code = ? AND w.deleted_at IS NULL
            GROUP BY w.id',
            [$id, $this->tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        $row = $rs->fields;
        if (is_string($row['actions'])) {
            $row['actions'] = json_decode($row['actions'], true) ?? [];
        }
        if (is_string($row['trigger_config'])) {
            $row['trigger_config'] = json_decode($row['trigger_config'], true) ?? [];
        }

        return $row;
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function list(int $limit = 50, int $offset = 0): array
    {
        $rs = $this->db->Execute(
            'SELECT id, name, module, trigger_type, trigger_config, is_enabled, created_at, updated_at
               FROM workflows
              WHERE tenant_id = ? AND company_code = ? AND deleted_at IS NULL
              ORDER BY created_at DESC
              LIMIT ? OFFSET ?',
            [$this->tenantId, $this->companyCode, $limit, $offset]
        );

        if ($rs === false) {
            throw new \RuntimeException('WorkflowService::list failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function create(array $data, int $createdBy): int
    {
        $this->validateWorkflowData($data);

        $now = $this->now();
        $rs  = $this->db->Execute(
            'INSERT INTO workflows
                (tenant_id, company_code, name, module, trigger_type, trigger_config, is_enabled, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             RETURNING id',
            [
                $this->tenantId,
                $this->companyCode,
                $data['name'],
                $data['module'],
                $data['trigger_type'],
                json_encode($data['trigger_config'] ?? []),
                isset($data['is_enabled']) ? (bool) $data['is_enabled'] : true,
                $createdBy ?: null,
                $now,
                $now,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('WorkflowService::create failed: ' . $this->db->ErrorMsg());
        }

        return (!$rs->EOF) ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function update(int $id, array $data): bool
    {
        unset($data['id'], $data['tenant_id'], $data['company_code'], $data['created_at'], $data['created_by']);

        if (isset($data['trigger_config']) && is_array($data['trigger_config'])) {
            $data['trigger_config'] = json_encode($data['trigger_config']);
        }

        $data['updated_at'] = $this->now();

        $setClauses = [];
        $values     = [];
        foreach ($data as $col => $val) {
            $setClauses[] = "{$col} = ?";
            $values[]     = $val;
        }

        $values[] = $id;
        $values[] = $this->tenantId;
        $values[] = $this->companyCode;

        $result = $this->db->Execute(
            sprintf(
                'UPDATE workflows SET %s WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                implode(', ', $setClauses)
            ),
            $values
        );

        if ($result === false) {
            throw new \RuntimeException('WorkflowService::update failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Soft delete
    // -------------------------------------------------------------------------

    public function delete(int $id): bool
    {
        $now    = $this->now();
        $result = $this->db->Execute(
            'UPDATE workflows SET deleted_at = ?, updated_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$now, $now, $id, $this->tenantId, $this->companyCode]
        );

        if ($result === false) {
            throw new \RuntimeException('WorkflowService::delete failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Enable / Disable — Requirement 14.9
    // -------------------------------------------------------------------------

    public function setEnabled(int $id, bool $enabled): bool
    {
        $now    = $this->now();
        $result = $this->db->Execute(
            'UPDATE workflows SET is_enabled = ?, updated_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$enabled, $now, $id, $this->tenantId, $this->companyCode]
        );

        if ($result === false) {
            throw new \RuntimeException('WorkflowService::setEnabled failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Clone — Requirement 14.8
    // -------------------------------------------------------------------------

    /**
     * Clone a workflow (and its actions) as a new disabled draft.
     */
    public function clone(int $sourceId, int $createdBy): int
    {
        return $this->transaction(function () use ($sourceId, $createdBy): int {
            // Fetch source workflow
            $rs = $this->db->Execute(
                'SELECT * FROM workflows WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                [$sourceId, $this->tenantId, $this->companyCode]
            );

            if ($rs === false || $rs->EOF) {
                throw new \InvalidArgumentException("Workflow {$sourceId} not found.");
            }

            $source = $rs->fields;
            $now    = $this->now();

            // Insert cloned workflow (disabled by default)
            $cloneRs = $this->db->Execute(
                'INSERT INTO workflows (tenant_id, company_code, name, module, trigger_type, trigger_config, is_enabled, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, FALSE, ?, ?, ?)
                 RETURNING id',
                [
                    $this->tenantId,
                    $this->companyCode,
                    'Copy of ' . $source['name'],
                    $source['module'],
                    $source['trigger_type'],
                    $source['trigger_config'],
                    $createdBy ?: null,
                    $now,
                    $now,
                ]
            );

            if ($cloneRs === false) {
                throw new \RuntimeException('WorkflowService::clone insert failed: ' . $this->db->ErrorMsg());
            }

            $newId = (!$cloneRs->EOF) ? (int) $cloneRs->fields['id'] : (int) $this->db->Insert_ID();

            // Clone actions
            $actionsRs = $this->db->Execute(
                'SELECT * FROM workflow_actions WHERE workflow_id = ? AND tenant_id = ? AND deleted_at IS NULL ORDER BY action_order ASC',
                [$sourceId, $this->tenantId]
            );

            if ($actionsRs !== false) {
                while (!$actionsRs->EOF) {
                    $action = $actionsRs->fields;
                    $this->db->Execute(
                        'INSERT INTO workflow_actions (tenant_id, company_code, workflow_id, action_order, action_type, action_config, created_by, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [
                            $this->tenantId,
                            $this->companyCode,
                            $newId,
                            $action['action_order'],
                            $action['action_type'],
                            $action['action_config'],
                            $createdBy ?: null,
                            $now,
                            $now,
                        ]
                    );
                    $actionsRs->MoveNext();
                }
            }

            return $newId;
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function validateWorkflowData(array $data): void
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Workflow name is required.');
        }

        if (empty($data['module'])) {
            throw new \InvalidArgumentException('Workflow module is required.');
        }

        if (empty($data['trigger_type'])) {
            throw new \InvalidArgumentException('Workflow trigger_type is required.');
        }

        if (!in_array($data['trigger_type'], WorkflowEngine::TRIGGER_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid trigger_type '{$data['trigger_type']}'. " .
                'Valid: ' . implode(', ', WorkflowEngine::TRIGGER_TYPES)
            );
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
