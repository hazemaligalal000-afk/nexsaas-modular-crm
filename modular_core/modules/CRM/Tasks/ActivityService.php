<?php
/**
 * CRM/Tasks/ActivityService.php
 *
 * Business logic for activity logging and timeline retrieval.
 *
 * Requirements: 15.2, 15.5
 */

declare(strict_types=1);

namespace CRM\Tasks;

use Core\BaseService;

class ActivityService extends BaseService
{
    private string $tenantId;
    private string $companyCode;

    private const VALID_TYPES        = ['call', 'email', 'meeting', 'note', 'task'];
    private const VALID_LINKED_TYPES = ['contact', 'lead', 'deal', 'account'];

    /**
     * @param \ADOConnection $db
     * @param string         $tenantId
     * @param string         $companyCode
     */
    public function __construct($db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    // -------------------------------------------------------------------------
    // log — Requirement 15.2
    // -------------------------------------------------------------------------

    /**
     * Log an activity record linked to a CRM record.
     *
     * Required: type, linked_record_type, linked_record_id
     *
     * @param  array $data  Activity fields
     * @return array        Created activity row
     *
     * @throws \InvalidArgumentException on invalid type or linked_record_type
     * @throws \RuntimeException         on DB error
     */
    public function log(array $data): array
    {
        $type = $data['type'] ?? '';
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Activity type must be one of: " . implode(', ', self::VALID_TYPES)
            );
        }

        $linkedType = $data['linked_record_type'] ?? $data['linked_type'] ?? null;
        if ($linkedType !== null && !in_array($linkedType, self::VALID_LINKED_TYPES, true)) {
            throw new \InvalidArgumentException(
                "linked_record_type must be one of: " . implode(', ', self::VALID_LINKED_TYPES)
            );
        }

        $linkedId    = isset($data['linked_record_id']) ? (int) $data['linked_record_id'] : (isset($data['linked_id']) ? (int) $data['linked_id'] : null);
        $now         = $this->now();
        $activityDate = $data['activity_date'] ?? $now;

        $rs = $this->db->Execute(
            <<<SQL
            INSERT INTO activities
                (tenant_id, company_code, type, subject, body, outcome, duration_minutes,
                 activity_date, linked_type, linked_id, task_id, performed_by, created_by,
                 created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
            SQL,
            [
                $this->tenantId,
                $this->companyCode,
                $type,
                $data['subject']          ?? null,
                $data['body']             ?? null,
                $data['outcome']          ?? null,
                isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
                $activityDate,
                $linkedType,
                $linkedId,
                isset($data['task_id']) ? (int) $data['task_id'] : null,
                isset($data['performed_by']) ? (int) $data['performed_by'] : null,
                $data['created_by']       ?? null,
                $now,
                $now,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('ActivityService::log failed: ' . $this->db->ErrorMsg());
        }

        $id       = !$rs->EOF ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();
        $activity = $this->findById($id) ?? [];

        // Enqueue calendar push for meeting activities (Requirement 16.3: within 30s)
        if ($type === 'meeting' && $id > 0) {
            $this->enqueueCeleryCalendarPush($id);
        }

        return $activity;
    }

    // -------------------------------------------------------------------------
    // getTimeline — Requirement 15.5
    // -------------------------------------------------------------------------

    /**
     * Get all activities for a linked record, ordered by activity_date DESC.
     *
     * @param  string $recordType  contact|lead|deal|account
     * @param  int    $recordId
     * @param  string $tenantId
     * @return array
     *
     * @throws \InvalidArgumentException on invalid recordType
     * @throws \RuntimeException         on DB error
     */
    public function getTimeline(string $recordType, int $recordId, string $tenantId): array
    {
        if (!in_array($recordType, self::VALID_LINKED_TYPES, true)) {
            throw new \InvalidArgumentException(
                "recordType must be one of: " . implode(', ', self::VALID_LINKED_TYPES)
            );
        }

        $rs = $this->db->Execute(
            <<<SQL
            SELECT id, type, subject, body, outcome, duration_minutes, activity_date,
                   linked_type, linked_id, task_id, performed_by, created_by, created_at, updated_at
            FROM activities
            WHERE linked_type = ?
              AND linked_id   = ?
              AND tenant_id   = ?
              AND company_code = ?
              AND deleted_at  IS NULL
            ORDER BY activity_date DESC
            SQL,
            [$recordType, $recordId, $tenantId, $this->companyCode]
        );

        if ($rs === false) {
            throw new \RuntimeException('ActivityService::getTimeline failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // logCompletion — Requirement 15.5
    // -------------------------------------------------------------------------

    /**
     * Auto-log a 'task' type activity when a task is completed.
     *
     * Reads the task record to get linked_type/linked_id, then inserts
     * an activity entry on the linked record's timeline.
     *
     * @param  int    $taskId
     * @param  string $tenantId
     * @param  int    $userId
     * @return array  Created activity row
     *
     * @throws \InvalidArgumentException if task not found
     * @throws \RuntimeException         on DB error
     */
    public function logCompletion(int $taskId, string $tenantId, int $userId): array
    {
        // Load the task to get linked record info
        $rs = $this->db->Execute(
            'SELECT id, title, linked_type, linked_id FROM tasks WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$taskId, $tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            throw new \InvalidArgumentException("Task {$taskId} not found for activity logging.");
        }

        $task = $rs->fields;

        return $this->log([
            'type'              => 'task',
            'subject'           => 'Task completed: ' . $task['title'],
            'body'              => "Task #{$taskId} was marked as completed.",
            'activity_date'     => $this->now(),
            'linked_record_type' => $task['linked_type'],
            'linked_record_id'  => (int) $task['linked_id'],
            'task_id'           => $taskId,
            'performed_by'      => $userId,
            'created_by'        => $userId,
        ]);
    }

    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    /**
     * Find a single activity by primary key, scoped to tenant.
     *
     * @param  int        $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $rs = $this->db->Execute(
            'SELECT id, type, subject, body, outcome, duration_minutes, activity_date,
                    linked_type, linked_id, task_id, performed_by, created_by, created_at, updated_at
             FROM activities
             WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$id, $this->tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    // -------------------------------------------------------------------------
    // list
    // -------------------------------------------------------------------------

    /**
     * List activities for the current tenant with optional filters.
     *
     * @param  array $filters  Optional: type, linked_record_type, linked_record_id, limit, offset
     * @return array
     */
    public function list(array $filters = []): array
    {
        $where  = ['tenant_id = ?', 'company_code = ?', 'deleted_at IS NULL'];
        $params = [$this->tenantId, $this->companyCode];

        if (!empty($filters['type'])) {
            $where[]  = 'type = ?';
            $params[] = $filters['type'];
        }

        if (!empty($filters['linked_record_type'])) {
            $where[]  = 'linked_type = ?';
            $params[] = $filters['linked_record_type'];
        }

        if (!empty($filters['linked_record_id'])) {
            $where[]  = 'linked_id = ?';
            $params[] = (int) $filters['linked_record_id'];
        }

        $limit  = max(1, min(200, (int) ($filters['limit']  ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $params[] = $limit;
        $params[] = $offset;

        $sql = sprintf(
            'SELECT id, type, subject, body, outcome, duration_minutes, activity_date,
                    linked_type, linked_id, task_id, performed_by, created_by, created_at, updated_at
             FROM activities
             WHERE %s
             ORDER BY activity_date DESC
             LIMIT ? OFFSET ?',
            implode(' AND ', $where)
        );

        $rs = $this->db->Execute($sql, $params);

        if ($rs === false) {
            throw new \RuntimeException('ActivityService::list failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    /**
     * Enqueue a Celery calendar push task via Redis.
     * Fires-and-forgets; failure is logged but does not block the main request.
     * Requirement 16.3: push must happen within 30s of meeting creation.
     */
    private function enqueueCeleryCalendarPush(int $activityId): void
    {
        $redisUrl = $_ENV['REDIS_URL'] ?? getenv('REDIS_URL') ?: 'redis://redis:6379/0';

        $message = json_encode([
            'id'      => bin2hex(random_bytes(16)),
            'task'    => 'calendar_sync.push_calendar_event',
            'args'    => [],
            'kwargs'  => ['activity_id' => $activityId],
            'retries' => 0,
        ]);

        try {
            $redis = new \Redis();
            $parts = parse_url($redisUrl);
            $redis->connect($parts['host'] ?? 'redis', (int) ($parts['port'] ?? 6379));
            $redis->rpush('celery', $message);
        } catch (\Throwable $e) {
            error_log("ActivityService::enqueueCeleryCalendarPush failed: " . $e->getMessage());
        }
    }
}
