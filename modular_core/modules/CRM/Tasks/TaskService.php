<?php
/**
 * CRM/Tasks/TaskService.php
 *
 * Business logic for task management: creation, updates, completion,
 * bulk assignment, and due-date reminder notifications.
 *
 * Requirements: 15.1, 15.3, 15.4, 15.5, 15.6
 */

declare(strict_types=1);

namespace CRM\Tasks;

use Core\BaseService;

class TaskService extends BaseService
{
    private object $redis;
    private string $tenantId;
    private string $companyCode;

    private const VALID_PRIORITIES    = ['low', 'medium', 'high', 'urgent'];
    private const VALID_STATUSES      = ['open', 'in_progress', 'completed', 'cancelled'];
    private const VALID_LINKED_TYPES  = ['contact', 'lead', 'deal', 'account'];
    private const MANAGER_ROLES       = ['Owner', 'Admin', 'Manager'];

    /**
     * @param \ADOConnection $db
     * @param object         $redis        Redis client (rpush, setex, etc.)
     * @param string         $tenantId     Current tenant UUID
     * @param string         $companyCode  Two-digit company code
     */
    public function __construct($db, object $redis, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->redis       = $redis;
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    // -------------------------------------------------------------------------
    // create — Requirement 15.1
    // -------------------------------------------------------------------------

    /**
     * Create a new task.
     *
     * Required fields: title, due_date, assigned_user_id, linked_record_type, linked_record_id.
     *
     * @param  array $data  Task fields
     * @return array        Created task row
     *
     * @throws \InvalidArgumentException on missing/invalid fields
     * @throws \RuntimeException         on DB error
     */
    public function create(array $data): array
    {
        $this->validateRequired($data, ['title', 'due_date', 'assigned_user_id', 'linked_record_type', 'linked_record_id']);

        $linkedType = $data['linked_record_type'];
        if (!in_array($linkedType, self::VALID_LINKED_TYPES, true)) {
            throw new \InvalidArgumentException(
                "linked_record_type must be one of: " . implode(', ', self::VALID_LINKED_TYPES)
            );
        }

        $priority = $data['priority'] ?? 'medium';
        if (!in_array($priority, self::VALID_PRIORITIES, true)) {
            throw new \InvalidArgumentException(
                "priority must be one of: " . implode(', ', self::VALID_PRIORITIES)
            );
        }

        $now = $this->now();

        $rs = $this->db->Execute(
            <<<SQL
            INSERT INTO tasks
                (tenant_id, company_code, title, description, due_date, priority, status,
                 assigned_user_id, linked_type, linked_id, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, ?, ?, ?)
            RETURNING id
            SQL,
            [
                $this->tenantId,
                $this->companyCode,
                trim($data['title']),
                $data['description'] ?? null,
                $data['due_date'],
                $priority,
                (int) $data['assigned_user_id'],
                $linkedType,
                (int) $data['linked_record_id'],
                $data['created_by'] ?? null,
                $now,
                $now,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('TaskService::create failed: ' . $this->db->ErrorMsg());
        }

        $id = !$rs->EOF ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();
        return $this->findById($id) ?? [];
    }

    // -------------------------------------------------------------------------
    // update — Requirement 15.1
    // -------------------------------------------------------------------------

    /**
     * Update task fields.
     *
     * @param  int    $id
     * @param  array  $data
     * @param  string $tenantId
     * @return array  Updated task row
     *
     * @throws \InvalidArgumentException if task not found
     * @throws \RuntimeException         on DB error
     */
    public function update(int $id, array $data, string $tenantId): array
    {
        unset($data['id'], $data['tenant_id'], $data['company_code'], $data['created_at']);

        if (isset($data['priority']) && !in_array($data['priority'], self::VALID_PRIORITIES, true)) {
            throw new \InvalidArgumentException(
                "priority must be one of: " . implode(', ', self::VALID_PRIORITIES)
            );
        }

        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(
                "status must be one of: " . implode(', ', self::VALID_STATUSES)
            );
        }

        if (isset($data['linked_record_type']) && !in_array($data['linked_record_type'], self::VALID_LINKED_TYPES, true)) {
            throw new \InvalidArgumentException(
                "linked_record_type must be one of: " . implode(', ', self::VALID_LINKED_TYPES)
            );
        }

        // Map linked_record_type/id to DB column names
        if (isset($data['linked_record_type'])) {
            $data['linked_type'] = $data['linked_record_type'];
            unset($data['linked_record_type']);
        }
        if (isset($data['linked_record_id'])) {
            $data['linked_id'] = (int) $data['linked_record_id'];
            unset($data['linked_record_id']);
        }

        if (empty($data)) {
            return $this->findById($id) ?? [];
        }

        $data['updated_at'] = $this->now();

        $setClauses = [];
        $values     = [];
        foreach ($data as $col => $val) {
            $setClauses[] = "{$col} = ?";
            $values[]     = $val;
        }

        $values[] = $id;
        $values[] = $tenantId;
        $values[] = $this->companyCode;

        $result = $this->db->Execute(
            sprintf(
                'UPDATE tasks SET %s WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                implode(', ', $setClauses)
            ),
            $values
        );

        if ($result === false) {
            throw new \RuntimeException('TaskService::update failed: ' . $this->db->ErrorMsg());
        }

        return $this->findById($id) ?? [];
    }

    // -------------------------------------------------------------------------
    // complete — Requirements 15.5
    // -------------------------------------------------------------------------

    /**
     * Mark a task as completed and log the completion activity to the timeline.
     *
     * @param  int    $id
     * @param  string $tenantId
     * @param  int    $userId
     * @return array  Updated task row
     *
     * @throws \InvalidArgumentException if task not found
     * @throws \RuntimeException         on DB error
     */
    public function complete(int $id, string $tenantId, int $userId): array
    {
        return $this->transaction(function () use ($id, $tenantId, $userId): array {
            $task = $this->findById($id);
            if ($task === null) {
                throw new \InvalidArgumentException("Task {$id} not found.");
            }

            $now = $this->now();

            $result = $this->db->Execute(
                'UPDATE tasks SET status = \'completed\', updated_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                [$now, $id, $tenantId, $this->companyCode]
            );

            if ($result === false) {
                throw new \RuntimeException('TaskService::complete failed: ' . $this->db->ErrorMsg());
            }

            // Requirement 15.5 — auto-log activity to linked record timeline
            $activityService = new ActivityService($this->db, $this->tenantId, $this->companyCode);
            $activityService->logCompletion($id, $tenantId, $userId);

            return $this->findById($id) ?? [];
        });
    }

    // -------------------------------------------------------------------------
    // delete — soft delete
    // -------------------------------------------------------------------------

    /**
     * Soft-delete a task.
     *
     * @param  int    $id
     * @param  string $tenantId
     * @return bool
     *
     * @throws \RuntimeException on DB error
     */
    public function delete(int $id, string $tenantId): bool
    {
        $result = $this->db->Execute(
            'UPDATE tasks SET deleted_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$this->now(), $id, $tenantId, $this->companyCode]
        );

        if ($result === false) {
            throw new \RuntimeException('TaskService::delete failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // getForUser — Requirement 15.4
    // -------------------------------------------------------------------------

    /**
     * Get tasks assigned to a user, sortable by due_date and priority.
     *
     * Supported filters: status, linked_record_type, linked_record_id, priority
     * Supported sort: due_date, priority (mapped to priority order), created_at
     *
     * @param  int    $userId
     * @param  string $tenantId
     * @param  array  $filters  Optional: status, sort, dir, limit, offset
     * @return array
     */
    public function getForUser(int $userId, string $tenantId, array $filters = []): array
    {
        $where  = [
            'tenant_id = ?',
            'company_code = ?',
            'assigned_user_id = ?',
            'deleted_at IS NULL',
        ];
        $params = [$tenantId, $this->companyCode, $userId];

        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $where[]  = 'priority = ?';
            $params[] = $filters['priority'];
        }

        if (!empty($filters['linked_record_type'])) {
            $where[]  = 'linked_type = ?';
            $params[] = $filters['linked_record_type'];
        }

        // Sort handling — Requirement 15.4
        $sort = $filters['sort'] ?? 'due_date';
        $dir  = strtoupper($filters['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $orderBy = match ($sort) {
            'priority' => "CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END {$dir}",
            'due_date' => "due_date {$dir} NULLS LAST",
            default    => "created_at {$dir}",
        };

        $limit  = max(1, min(200, (int) ($filters['limit']  ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $params[] = $limit;
        $params[] = $offset;

        $sql = sprintf(
            'SELECT id, title, description, due_date, priority, status, assigned_user_id,
                    linked_type, linked_id, reminder_sent_at, created_by, created_at, updated_at
             FROM tasks
             WHERE %s
             ORDER BY %s
             LIMIT ? OFFSET ?',
            implode(' AND ', $where),
            $orderBy
        );

        $rs = $this->db->Execute($sql, $params);

        if ($rs === false) {
            throw new \RuntimeException('TaskService::getForUser failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // bulkAssign — Requirement 15.6
    // -------------------------------------------------------------------------

    /**
     * Bulk reassign tasks to a new user. Requires Manager role or above.
     *
     * @param  array  $taskIds      IDs of tasks to reassign
     * @param  int    $newUserId    Target user to assign tasks to
     * @param  string $tenantId     Tenant UUID
     * @param  int    $actingUserId User performing the bulk assign
     * @return array  { reassigned: int, task_ids: int[] }
     *
     * @throws \RuntimeException on permission denied or DB error
     */
    public function bulkAssign(array $taskIds, int $newUserId, string $tenantId, int $actingUserId): array
    {
        // Requirement 15.6 — check Manager role
        if (!$this->hasManagerRole($actingUserId, $tenantId)) {
            throw new \RuntimeException('Bulk assignment requires Manager role or above. (Req 15.6)');
        }

        if (empty($taskIds)) {
            return ['reassigned' => 0, 'task_ids' => []];
        }

        $taskIds      = array_map('intval', $taskIds);
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $params       = array_merge([$newUserId, $this->now(), $tenantId, $this->companyCode], $taskIds);

        $result = $this->db->Execute(
            sprintf(
                'UPDATE tasks SET assigned_user_id = ?, updated_at = ?
                 WHERE id IN (%s) AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                $placeholders
            ),
            $params
        );

        if ($result === false) {
            throw new \RuntimeException('TaskService::bulkAssign failed: ' . $this->db->ErrorMsg());
        }

        $affected = $this->db->Affected_Rows();

        return [
            'reassigned' => $affected,
            'task_ids'   => $taskIds,
            'new_user_id' => $newUserId,
        ];
    }

    // -------------------------------------------------------------------------
    // sendDueReminders — Requirement 15.3
    // -------------------------------------------------------------------------

    /**
     * Find tasks due within the next hour that haven't had a reminder sent,
     * push a WebSocket notification via Redis, and mark reminder_sent_at.
     *
     * Called by cron every 15 minutes.
     *
     * @return int  Number of reminders sent
     */
    public function sendDueReminders(): int
    {
        $sql = <<<SQL
            SELECT t.id, t.title, t.due_date, t.assigned_user_id, t.tenant_id,
                   t.linked_type, t.linked_id
            FROM tasks t
            WHERE t.status NOT IN ('completed', 'cancelled')
              AND t.reminder_sent_at IS NULL
              AND t.deleted_at IS NULL
              AND t.due_date <= NOW() + INTERVAL '1 hour'
              AND t.due_date >= NOW() - INTERVAL '1 day'
        SQL;

        $rs = $this->db->Execute($sql);

        if ($rs === false) {
            throw new \RuntimeException('TaskService::sendDueReminders query failed: ' . $this->db->ErrorMsg());
        }

        $count = 0;
        $now   = $this->now();

        while (!$rs->EOF) {
            $task   = $rs->fields;
            $taskId = (int) $task['id'];
            $userId = $task['assigned_user_id'];

            if ($userId !== null) {
                $this->pushDueReminderNotification($task);
            }

            // Mark reminder sent
            $this->db->Execute(
                'UPDATE tasks SET reminder_sent_at = ? WHERE id = ? AND deleted_at IS NULL',
                [$now, $taskId]
            );

            $count++;
            $rs->MoveNext();
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    /**
     * Find a single active task by primary key, scoped to tenant.
     *
     * @param  int        $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $rs = $this->db->Execute(
            'SELECT id, title, description, due_date, priority, status, assigned_user_id,
                    linked_type, linked_id, reminder_sent_at, created_by, created_at, updated_at
             FROM tasks
             WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$id, $this->tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Check if a user has Manager role or above.
     *
     * @param  int    $userId
     * @param  string $tenantId
     * @return bool
     */
    private function hasManagerRole(int $userId, string $tenantId): bool
    {
        $placeholders = implode(',', array_fill(0, count(self::MANAGER_ROLES), '?'));
        $params = array_merge([$userId, $tenantId], self::MANAGER_ROLES);

        $rs = $this->db->Execute(
            "SELECT id FROM users WHERE id = ? AND tenant_id = ? AND platform_role IN ({$placeholders}) AND deleted_at IS NULL",
            $params
        );

        return $rs !== false && !$rs->EOF;
    }

    /**
     * Push a due-date reminder notification to Redis for WebSocket delivery.
     *
     * Redis key: notifications:pending:{user_id}
     *
     * @param  array $task
     */
    private function pushDueReminderNotification(array $task): void
    {
        $userId = $task['assigned_user_id'];
        if ($userId === null) {
            return;
        }

        $payload = json_encode([
            'type'        => 'task_due_reminder',
            'task_id'     => (int) $task['id'],
            'title'       => $task['title'],
            'due_date'    => $task['due_date'],
            'linked_type' => $task['linked_type'],
            'linked_id'   => $task['linked_id'],
            'tenant_id'   => $task['tenant_id'],
        ]);

        $this->redis->rpush("notifications:pending:{$userId}", $payload);
    }

    /**
     * Validate that required fields are present and non-empty.
     *
     * @param  array    $data
     * @param  string[] $fields
     *
     * @throws \InvalidArgumentException
     */
    private function validateRequired(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                throw new \InvalidArgumentException("Field '{$field}' is required.");
            }
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
