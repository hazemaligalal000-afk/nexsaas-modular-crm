<?php
/**
 * CRM/Analytics/ReportScheduleService.php
 *
 * Manages scheduled report delivery configuration.
 *
 * Requirements: 17.4
 */

declare(strict_types=1);

namespace CRM\Analytics;

use Core\BaseService;

class ReportScheduleService extends BaseService
{
    private string $tenantId;
    private string $companyCode;
    private string $userId;

    private const VALID_FREQUENCIES = ['daily', 'weekly', 'monthly'];
    private const VALID_FORMATS     = ['csv', 'pdf'];

    public function __construct($db, string $tenantId, string $companyCode, string $userId = '')
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
        $this->userId      = $userId;
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a new schedule for a report.
     *
     * @param  array $data  Must include: report_id, frequency, next_run_at, recipients.
     *                      Optional: format (csv|pdf), is_active.
     * @return array  Created schedule row.
     */
    public function createSchedule(array $data): array
    {
        $this->validateFrequency($data['frequency'] ?? '');
        $this->validateFormat($data['format'] ?? 'csv');

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $payload = [
            'tenant_id'    => $this->tenantId,
            'company_code' => $this->companyCode,
            'report_id'    => (int) $data['report_id'],
            'frequency'    => $data['frequency'],
            'next_run_at'  => $data['next_run_at'],
            'recipients'   => json_encode($data['recipients'] ?? []),
            'format'       => $data['format'] ?? 'csv',
            'is_active'    => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'created_by'   => (int) ($data['created_by'] ?? $this->userId),
            'created_at'   => $now,
            'updated_at'   => $now,
        ];

        $cols   = implode(', ', array_keys($payload));
        $places = implode(', ', array_fill(0, count($payload), '?'));
        $sql    = "INSERT INTO report_schedules ({$cols}) VALUES ({$places})";

        $rs = $this->db->Execute($sql, array_values($payload));
        if ($rs === false) {
            throw new \RuntimeException('createSchedule failed: ' . $this->db->ErrorMsg());
        }

        $id = (int) $this->db->Insert_ID();
        return $this->findSchedule($id);
    }

    /**
     * Update an existing schedule.
     *
     * @param  int   $scheduleId
     * @param  array $data
     * @return array  Updated schedule row.
     */
    public function updateSchedule(int $scheduleId, array $data): array
    {
        if (isset($data['frequency'])) {
            $this->validateFrequency($data['frequency']);
        }
        if (isset($data['format'])) {
            $this->validateFormat($data['format']);
        }

        $allowed = ['frequency', 'next_run_at', 'recipients', 'format', 'is_active'];
        $payload = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = ($field === 'recipients')
                    ? json_encode($data[$field])
                    : $data[$field];
            }
        }

        if (empty($payload)) {
            return $this->findSchedule($scheduleId);
        }

        $payload['updated_at'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $setClauses = [];
        $values     = [];
        foreach ($payload as $col => $val) {
            $setClauses[] = "{$col} = ?";
            $values[]     = $val;
        }
        $values[] = $scheduleId;
        $values[] = $this->tenantId;
        $values[] = $this->companyCode;

        $sql = "UPDATE report_schedules SET " . implode(', ', $setClauses)
             . " WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL";

        $rs = $this->db->Execute($sql, $values);
        if ($rs === false) {
            throw new \RuntimeException('updateSchedule failed: ' . $this->db->ErrorMsg());
        }

        return $this->findSchedule($scheduleId);
    }

    /**
     * Soft-delete a schedule.
     *
     * @param  int $scheduleId
     * @return bool
     */
    public function deleteSchedule(int $scheduleId): bool
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $sql = "UPDATE report_schedules SET deleted_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL";
        $rs  = $this->db->Execute($sql, [$now, $scheduleId, $this->tenantId, $this->companyCode]);

        if ($rs === false) {
            throw new \RuntimeException('deleteSchedule failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    /**
     * List all active schedules for a report.
     *
     * @param  int $reportId
     * @return array
     */
    public function listSchedules(int $reportId): array
    {
        $sql = <<<SQL
            SELECT *
            FROM report_schedules
            WHERE report_id    = ?
              AND tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
            ORDER BY created_at DESC
        SQL;

        $rows = [];
        $rs   = $this->db->Execute($sql, [$reportId, $this->tenantId, $this->companyCode]);

        if ($rs === false) {
            throw new \RuntimeException('listSchedules query failed: ' . $this->db->ErrorMsg());
        }

        while (!$rs->EOF) {
            $rows[] = $this->decodeJsonFields($rs->fields);
            $rs->MoveNext();
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Scheduler helpers — used by the Celery cron task
    // -------------------------------------------------------------------------

    /**
     * Return all schedules that are due (next_run_at <= NOW() and is_active = true).
     * No tenant scoping — this is called by the system-level cron task.
     *
     * @return array
     */
    public function getDueSchedules(): array
    {
        $sql = <<<SQL
            SELECT rs.*, cr.name AS report_name, cr.data_source,
                   cr.dimensions, cr.metrics, cr.filters, cr.sort_config
            FROM report_schedules rs
            JOIN custom_reports cr
                ON cr.id           = rs.report_id
               AND cr.deleted_at   IS NULL
            WHERE rs.next_run_at  <= NOW()
              AND rs.is_active     = TRUE
              AND rs.deleted_at    IS NULL
            ORDER BY rs.next_run_at ASC
        SQL;

        $rows = [];
        $rs   = $this->db->Execute($sql, []);

        if ($rs === false) {
            throw new \RuntimeException('getDueSchedules query failed: ' . $this->db->ErrorMsg());
        }

        while (!$rs->EOF) {
            $rows[] = $this->decodeJsonFields($rs->fields);
            $rs->MoveNext();
        }

        return $rows;
    }

    /**
     * Advance next_run_at based on frequency after a successful run.
     *
     * @param  int $scheduleId
     * @return void
     */
    public function markRan(int $scheduleId): void
    {
        // Fetch current frequency
        $sql = "SELECT frequency FROM report_schedules WHERE id = ? AND deleted_at IS NULL";
        $rs  = $this->db->Execute($sql, [$scheduleId]);

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Schedule {$scheduleId} not found.");
        }

        $frequency = $rs->fields['frequency'];

        $intervalMap = [
            'daily'   => '+1 day',
            'weekly'  => '+7 days',
            'monthly' => '+1 month',
        ];

        $interval    = $intervalMap[$frequency] ?? '+1 day';
        $nextRunAt   = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                            ->modify($interval)
                            ->format('Y-m-d H:i:s');
        $now         = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $updateSql = "UPDATE report_schedules SET next_run_at = ?, updated_at = ? WHERE id = ?";
        $this->db->Execute($updateSql, [$nextRunAt, $now, $scheduleId]);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function findSchedule(int $id): array
    {
        $sql = "SELECT * FROM report_schedules WHERE id = ? AND deleted_at IS NULL";
        $rs  = $this->db->Execute($sql, [$id]);

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Schedule {$id} not found.");
        }

        return $this->decodeJsonFields($rs->fields);
    }

    private function validateFrequency(string $freq): void
    {
        if (!in_array($freq, self::VALID_FREQUENCIES, true)) {
            throw new \InvalidArgumentException("Invalid frequency '{$freq}'. Must be one of: " . implode(', ', self::VALID_FREQUENCIES));
        }
    }

    private function validateFormat(string $format): void
    {
        if (!in_array($format, self::VALID_FORMATS, true)) {
            throw new \InvalidArgumentException("Invalid format '{$format}'. Must be one of: " . implode(', ', self::VALID_FORMATS));
        }
    }

    private function decodeJsonFields(array $row): array
    {
        foreach (['recipients', 'dimensions', 'metrics', 'filters', 'sort_config'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = json_decode($row[$field], true) ?? [];
            }
        }
        return $row;
    }
}
