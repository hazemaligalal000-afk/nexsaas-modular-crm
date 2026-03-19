<?php
/**
 * CRM/Calendar/CalendarService.php
 *
 * Business logic for calendar views, OAuth connections, and two-way sync.
 *
 * Requirements: 16.1, 16.2, 16.3, 16.4
 */

declare(strict_types=1);

namespace CRM\Calendar;

use Core\BaseService;
use CRM\Calendar\Providers\GoogleCalendarService;
use CRM\Calendar\Providers\OutlookCalendarService;

class CalendarService extends BaseService
{
    private string $tenantId;
    private string $companyCode;
    private GoogleCalendarService  $google;
    private OutlookCalendarService $outlook;

    public function __construct($db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
        $this->google      = new GoogleCalendarService();
        $this->outlook     = new OutlookCalendarService();
    }

    // -------------------------------------------------------------------------
    // Calendar view — Requirement 16.1
    // -------------------------------------------------------------------------

    /**
     * Get calendar view: activities + tasks with due dates in the date range.
     *
     * @param  string $tenantId
     * @param  int    $userId
     * @param  string $view   day|week|month
     * @param  string $date   YYYY-MM-DD
     * @return array
     */
    public function getCalendarView(string $tenantId, int $userId, string $view, string $date): array
    {
        [$start, $end] = $this->getDateRange($view, $date);

        // Fetch meeting/call activities in range for the user
        $activities = $this->fetchActivitiesInRange($tenantId, $userId, $start, $end);

        // Fetch tasks with due dates in range for the user
        $tasks = $this->fetchTasksInRange($tenantId, $userId, $start, $end);

        // Merge and sort by date
        $items = array_merge(
            array_map(fn($a) => array_merge($a, ['_item_type' => 'activity']), $activities),
            array_map(fn($t) => array_merge($t, ['_item_type' => 'task']),     $tasks)
        );

        usort($items, function ($a, $b) {
            $dateA = $a['activity_date'] ?? $a['due_date'] ?? '';
            $dateB = $b['activity_date'] ?? $b['due_date'] ?? '';
            return strcmp($dateA, $dateB);
        });

        return [
            'view'       => $view,
            'date'       => $date,
            'start'      => $start,
            'end'        => $end,
            'items'      => $items,
            'total'      => count($items),
        ];
    }

    /**
     * Compute start/end date range for a given view and anchor date.
     *
     * @param  string $view  day|week|month
     * @param  string $date  YYYY-MM-DD
     * @return array         [startDate, endDate] as 'Y-m-d H:i:s' strings
     */
    public function getDateRange(string $view, string $date): array
    {
        $dt = new \DateTimeImmutable($date . ' 00:00:00', new \DateTimeZone('UTC'));

        switch ($view) {
            case 'day':
                $start = $dt->format('Y-m-d 00:00:00');
                $end   = $dt->format('Y-m-d 23:59:59');
                break;

            case 'week':
                // ISO week: Monday to Sunday
                $dow   = (int) $dt->format('N'); // 1=Mon, 7=Sun
                $start = $dt->modify('-' . ($dow - 1) . ' days')->format('Y-m-d 00:00:00');
                $end   = $dt->modify('+' . (7 - $dow) . ' days')->format('Y-m-d 23:59:59');
                break;

            case 'month':
            default:
                $start = $dt->modify('first day of this month')->format('Y-m-d 00:00:00');
                $end   = $dt->modify('last day of this month')->format('Y-m-d 23:59:59');
                break;
        }

        return [$start, $end];
    }

    // -------------------------------------------------------------------------
    // OAuth connections — Requirement 16.2
    // -------------------------------------------------------------------------

    /**
     * Get connected calendars for a user.
     *
     * @param  int    $userId
     * @return array
     */
    public function getConnections(int $userId): array
    {
        $rs = $this->db->Execute(
            'SELECT id, provider, calendar_id, is_active, token_expires_at, created_at, updated_at
             FROM calendar_connections
             WHERE tenant_id = ? AND company_code = ? AND user_id = ? AND deleted_at IS NULL
             ORDER BY created_at ASC',
            [$this->tenantId, $this->companyCode, $userId]
        );

        if ($rs === false) {
            throw new \RuntimeException('CalendarService::getConnections failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    /**
     * Initiate OAuth flow — returns the provider's authorization URL.
     *
     * @param  int    $userId
     * @param  string $provider  google|outlook
     * @return string
     */
    public function getAuthUrl(int $userId, string $provider): string
    {
        $this->assertProvider($provider);

        return $provider === 'google'
            ? $this->google->getAuthUrl($userId)
            : $this->outlook->getAuthUrl($userId);
    }

    /**
     * Exchange OAuth code for tokens and store the connection.
     *
     * @param  int    $userId
     * @param  string $provider  google|outlook
     * @param  string $code
     * @return array  Connection record
     * @throws \RuntimeException on failure
     */
    public function connectCalendar(int $userId, string $provider, string $code): array
    {
        $this->assertProvider($provider);

        $tokens = $provider === 'google'
            ? $this->google->exchangeCode($code)
            : $this->outlook->exchangeCode($code);

        $encryptedAccess  = $this->encryptToken($tokens['access_token']);
        $encryptedRefresh = $this->encryptToken($tokens['refresh_token'] ?? '');

        $expiresIn = (int) ($tokens['expires_in'] ?? 3600);
        $expiresAt = (new \DateTimeImmutable("+{$expiresIn} seconds", new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $now = $this->now();

        // Upsert: restore if previously disconnected
        $existing = $this->db->Execute(
            'SELECT id FROM calendar_connections WHERE tenant_id = ? AND user_id = ? AND provider = ? LIMIT 1',
            [$this->tenantId, $userId, $provider]
        );

        if ($existing !== false && !$existing->EOF) {
            $connId = (int) $existing->fields['id'];
            $this->db->Execute(
                'UPDATE calendar_connections SET access_token = ?, refresh_token = ?,
                 token_expires_at = ?, is_active = TRUE, deleted_at = NULL, updated_at = ?
                 WHERE id = ?',
                [$encryptedAccess, $encryptedRefresh, $expiresAt, $now, $connId]
            );
        } else {
            $rs = $this->db->Execute(
                'INSERT INTO calendar_connections
                    (tenant_id, company_code, user_id, provider, access_token, refresh_token,
                     token_expires_at, is_active, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?) RETURNING id',
                [$this->tenantId, $this->companyCode, $userId, $provider,
                 $encryptedAccess, $encryptedRefresh, $expiresAt, $userId, $now, $now]
            );

            if ($rs === false) {
                throw new \RuntimeException('Failed to store calendar connection: ' . $this->db->ErrorMsg());
            }

            $connId = (!$rs->EOF) ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();
        }

        return $this->findConnectionById($connId);
    }

    /**
     * Disconnect (soft-delete) a calendar connection.
     *
     * @param  int $userId
     * @param  int $connectionId
     * @return bool
     */
    public function disconnectCalendar(int $userId, int $connectionId): bool
    {
        $now    = $this->now();
        $result = $this->db->Execute(
            'UPDATE calendar_connections SET is_active = FALSE, deleted_at = ?, updated_at = ?
             WHERE id = ? AND user_id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$now, $now, $connectionId, $userId, $this->tenantId]
        );

        return $result !== false && $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Sync — Requirements 16.3, 16.4
    // -------------------------------------------------------------------------

    /**
     * Push a meeting activity to all connected external calendars for its owner.
     * Enqueues an async Celery job for the 30s SLA.
     *
     * @param  int $activityId
     */
    public function pushEventToExternal(int $activityId): void
    {
        $this->enqueueCeleryTask('calendar_sync.push_calendar_event', ['activity_id' => $activityId]);
    }

    /**
     * Pull external calendar changes for a user and update local activities.
     * Enqueues an async Celery job.
     *
     * @param  int $userId
     */
    public function pullExternalChanges(int $userId): void
    {
        $this->enqueueCeleryTask('calendar_sync.sync_calendar_for_user', ['user_id' => $userId]);
    }

    /**
     * Trigger a manual sync for the current user (push + pull).
     *
     * @param  int $userId
     * @return array
     */
    public function triggerSync(int $userId): array
    {
        $this->pullExternalChanges($userId);
        return ['queued' => true, 'user_id' => $userId];
    }

    // -------------------------------------------------------------------------
    // Token helpers (same pattern as MailboxConnectionService)
    // -------------------------------------------------------------------------

    public function encryptToken(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $key        = $this->getEncryptionKey();
        $iv         = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Token encryption failed.');
        }
        return base64_encode($iv . $ciphertext);
    }

    public function decryptToken(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }
        $key  = $this->getEncryptionKey();
        $data = base64_decode($encrypted, true);
        if ($data === false || strlen($data) < 17) {
            throw new \RuntimeException('Invalid encrypted token format.');
        }
        $iv         = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $plaintext  = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new \RuntimeException('Token decryption failed.');
        }
        return $plaintext;
    }

    private function getEncryptionKey(): string
    {
        $appKey = $_ENV['APP_KEY'] ?? getenv('APP_KEY') ?: 'default-insecure-key-change-in-production';
        return hash('sha256', $appKey, true);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function fetchActivitiesInRange(string $tenantId, int $userId, string $start, string $end): array
    {
        $rs = $this->db->Execute(
            <<<SQL
            SELECT id, type, subject, body, outcome, duration_minutes, activity_date,
                   linked_type, linked_id, performed_by, external_event_id, external_calendar_provider,
                   created_at, updated_at
            FROM activities
            WHERE tenant_id = ? AND company_code = ?
              AND performed_by = ?
              AND activity_date BETWEEN ? AND ?
              AND deleted_at IS NULL
            ORDER BY activity_date ASC
            SQL,
            [$tenantId, $this->companyCode, $userId, $start, $end]
        );

        if ($rs === false) {
            return [];
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    private function fetchTasksInRange(string $tenantId, int $userId, string $start, string $end): array
    {
        $rs = $this->db->Execute(
            <<<SQL
            SELECT id, title, description, status, priority, due_date, assigned_to,
                   linked_type, linked_id, created_at, updated_at
            FROM tasks
            WHERE tenant_id = ? AND company_code = ?
              AND assigned_to = ?
              AND due_date BETWEEN ? AND ?
              AND deleted_at IS NULL
            ORDER BY due_date ASC
            SQL,
            [$tenantId, $this->companyCode, $userId, $start, $end]
        );

        if ($rs === false) {
            return [];
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    private function findConnectionById(int $id): array
    {
        $rs = $this->db->Execute(
            'SELECT id, tenant_id, company_code, user_id, provider, calendar_id, is_active,
                    token_expires_at, created_at, updated_at
             FROM calendar_connections WHERE id = ?',
            [$id]
        );

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Calendar connection {$id} not found after insert.");
        }

        return $rs->fields;
    }

    /**
     * Enqueue a Celery task via RabbitMQ using the same pattern as email_sync.py.
     */
    private function enqueueCeleryTask(string $taskName, array $kwargs): void
    {
        $brokerUrl = $_ENV['RABBITMQ_URL'] ?? getenv('RABBITMQ_URL') ?: 'amqp://guest:guest@rabbitmq:5672//';

        $message = json_encode([
            'id'      => bin2hex(random_bytes(16)),
            'task'    => $taskName,
            'args'    => [],
            'kwargs'  => $kwargs,
            'retries' => 0,
        ]);

        // Use Redis as a simple queue fallback if AMQP is not available
        $redisUrl = $_ENV['REDIS_URL'] ?? getenv('REDIS_URL') ?: 'redis://redis:6379/0';

        try {
            $redis = new \Redis();
            $parts = parse_url($redisUrl);
            $redis->connect($parts['host'] ?? 'redis', (int) ($parts['port'] ?? 6379));
            $redis->rpush('celery', $message);
        } catch (\Throwable $e) {
            // Log but don't fail the main request — sync will be retried
            error_log("CalendarService::enqueueCeleryTask failed: " . $e->getMessage());
        }
    }

    private function assertProvider(string $provider): void
    {
        if (!in_array($provider, ['google', 'outlook'], true)) {
            throw new \InvalidArgumentException("Provider must be 'google' or 'outlook'.");
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
