<?php
/**
 * CRM/Calendar/SchedulingLinkService.php
 *
 * Business logic for public meeting scheduling links and bookings.
 *
 * Requirements: 16.5
 */

declare(strict_types=1);

namespace CRM\Calendar;

use Core\BaseService;

class SchedulingLinkService extends BaseService
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
    // CRUD for scheduling links
    // -------------------------------------------------------------------------

    /**
     * List scheduling links for the current user.
     *
     * @param  int $userId
     * @return array
     */
    public function list(int $userId): array
    {
        $rs = $this->db->Execute(
            'SELECT id, slug, title, duration_minutes, buffer_minutes, availability_rules,
                    is_active, created_at, updated_at
             FROM scheduling_links
             WHERE tenant_id = ? AND company_code = ? AND user_id = ? AND deleted_at IS NULL
             ORDER BY created_at DESC',
            [$this->tenantId, $this->companyCode, $userId]
        );

        if ($rs === false) {
            throw new \RuntimeException('SchedulingLinkService::list failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    /**
     * Create a new scheduling link.
     *
     * @param  int   $userId
     * @param  array $data
     * @return array
     */
    public function create(int $userId, array $data): array
    {
        $slug  = $this->generateSlug();
        $rules = $data['availability_rules'] ?? [
            'weekdays'   => [1, 2, 3, 4, 5],
            'start_time' => '09:00',
            'end_time'   => '17:00',
            'timezone'   => 'UTC',
        ];

        $now = $this->now();
        $rs  = $this->db->Execute(
            'INSERT INTO scheduling_links
                (tenant_id, company_code, user_id, slug, title, duration_minutes,
                 buffer_minutes, availability_rules, is_active, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?::jsonb, TRUE, ?, ?, ?) RETURNING id',
            [
                $this->tenantId,
                $this->companyCode,
                $userId,
                $slug,
                $data['title'] ?? 'Meeting',
                (int) ($data['duration_minutes'] ?? 30),
                (int) ($data['buffer_minutes']   ?? 0),
                json_encode($rules),
                $userId,
                $now,
                $now,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('SchedulingLinkService::create failed: ' . $this->db->ErrorMsg());
        }

        $id = (!$rs->EOF) ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();
        return $this->findById($id);
    }

    /**
     * Update a scheduling link.
     *
     * @param  int   $id
     * @param  int   $userId
     * @param  array $data
     * @return array
     */
    public function update(int $id, int $userId, array $data): array
    {
        $allowed = ['title', 'duration_minutes', 'buffer_minutes', 'availability_rules', 'is_active'];
        $set     = [];
        $params  = [];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if ($field === 'availability_rules') {
                $set[]    = "availability_rules = ?::jsonb";
                $params[] = json_encode($data[$field]);
            } else {
                $set[]    = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($set)) {
            return $this->findById($id);
        }

        $set[]    = 'updated_at = ?';
        $params[] = $this->now();
        $params[] = $id;
        $params[] = $userId;
        $params[] = $this->tenantId;

        $result = $this->db->Execute(
            'UPDATE scheduling_links SET ' . implode(', ', $set) .
            ' WHERE id = ? AND user_id = ? AND tenant_id = ? AND deleted_at IS NULL',
            $params
        );

        if ($result === false) {
            throw new \RuntimeException('SchedulingLinkService::update failed: ' . $this->db->ErrorMsg());
        }

        return $this->findById($id);
    }

    /**
     * Soft-delete a scheduling link.
     *
     * @param  int $id
     * @param  int $userId
     * @return bool
     */
    public function delete(int $id, int $userId): bool
    {
        $now    = $this->now();
        $result = $this->db->Execute(
            'UPDATE scheduling_links SET deleted_at = ?, updated_at = ?
             WHERE id = ? AND user_id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$now, $now, $id, $userId, $this->tenantId]
        );

        return $result !== false && $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Public booking — Requirement 16.5
    // -------------------------------------------------------------------------

    /**
     * Get available time slots for a scheduling link over the next 14 days.
     *
     * @param  string $slug
     * @param  string $startDate  YYYY-MM-DD
     * @param  string $endDate    YYYY-MM-DD
     * @return array
     * @throws \InvalidArgumentException if slug not found
     */
    public function getAvailableSlots(string $slug, string $startDate, string $endDate): array
    {
        $link = $this->findBySlug($slug);
        if ($link === null) {
            throw new \InvalidArgumentException("Scheduling link '{$slug}' not found.");
        }

        $rules    = is_string($link['availability_rules'])
            ? json_decode($link['availability_rules'], true)
            : $link['availability_rules'];

        $weekdays    = $rules['weekdays']   ?? [1, 2, 3, 4, 5];
        $startTime   = $rules['start_time'] ?? '09:00';
        $endTime     = $rules['end_time']   ?? '17:00';
        $timezone    = $rules['timezone']   ?? 'UTC';
        $duration    = (int) $link['duration_minutes'];
        $buffer      = (int) $link['buffer_minutes'];
        $slotMinutes = $duration + $buffer;

        // Fetch existing bookings in range to exclude
        $bookedSlots = $this->fetchBookedSlots((int) $link['id'], $startDate, $endDate);

        $tz    = new \DateTimeZone($timezone);
        $utcTz = new \DateTimeZone('UTC');

        $slots   = [];
        $current = new \DateTimeImmutable($startDate, $tz);
        $end     = new \DateTimeImmutable($endDate, $tz);

        while ($current <= $end) {
            $dow = (int) $current->format('N'); // 1=Mon, 7=Sun

            if (in_array($dow, $weekdays, true)) {
                // Generate slots for this day
                $dayStart = new \DateTimeImmutable($current->format('Y-m-d') . ' ' . $startTime, $tz);
                $dayEnd   = new \DateTimeImmutable($current->format('Y-m-d') . ' ' . $endTime,   $tz);

                $slot = $dayStart;
                while ($slot->modify("+{$duration} minutes") <= $dayEnd) {
                    $slotEnd = $slot->modify("+{$duration} minutes");

                    // Check if slot overlaps with any existing booking
                    $slotStartUtc = $slot->setTimezone($utcTz)->format('Y-m-d H:i:s');
                    $slotEndUtc   = $slotEnd->setTimezone($utcTz)->format('Y-m-d H:i:s');

                    if (!$this->overlapsBooking($slotStartUtc, $slotEndUtc, $bookedSlots)) {
                        $slots[] = [
                            'start' => $slotStartUtc,
                            'end'   => $slotEndUtc,
                        ];
                    }

                    $slot = $slot->modify("+{$slotMinutes} minutes");
                }
            }

            $current = $current->modify('+1 day');
        }

        return [
            'link'  => [
                'slug'             => $link['slug'],
                'title'            => $link['title'],
                'duration_minutes' => $duration,
            ],
            'slots' => $slots,
        ];
    }

    /**
     * Create a booking via a scheduling link.
     *
     * Creates a scheduling_booking record + an activity of type 'meeting',
     * then enqueues a calendar push.
     *
     * @param  string $slug
     * @param  array  $bookerData  { booker_name, booker_email, start_at, notes? }
     * @return array  Booking record
     * @throws \InvalidArgumentException on invalid slug or slot
     */
    public function createBooking(string $slug, array $bookerData): array
    {
        $link = $this->findBySlug($slug);
        if ($link === null) {
            throw new \InvalidArgumentException("Scheduling link '{$slug}' not found.");
        }

        $startAt = $bookerData['start_at'] ?? '';
        if ($startAt === '') {
            throw new \InvalidArgumentException('start_at is required.');
        }

        $duration = (int) $link['duration_minutes'];
        $startDt  = new \DateTimeImmutable($startAt, new \DateTimeZone('UTC'));
        $endDt    = $startDt->modify("+{$duration} minutes");

        $startAtStr = $startDt->format('Y-m-d H:i:s');
        $endAtStr   = $endDt->format('Y-m-d H:i:s');

        // Check slot is still available
        $bookedSlots = $this->fetchBookedSlots((int) $link['id'], $startDt->format('Y-m-d'), $endDt->format('Y-m-d'));
        if ($this->overlapsBooking($startAtStr, $endAtStr, $bookedSlots)) {
            throw new \InvalidArgumentException('The requested time slot is no longer available.');
        }

        $now = $this->now();

        // Create activity of type 'meeting' for the link owner
        $activityRs = $this->db->Execute(
            <<<SQL
            INSERT INTO activities
                (tenant_id, company_code, type, subject, body, duration_minutes,
                 activity_date, performed_by, created_by, created_at, updated_at)
            VALUES (?, ?, 'meeting', ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
            SQL,
            [
                $link['tenant_id'],
                $link['company_code'],
                'Meeting with ' . ($bookerData['booker_name'] ?? 'Guest'),
                $bookerData['notes'] ?? '',
                $duration,
                $startAtStr,
                (int) $link['user_id'],
                (int) $link['user_id'],
                $now,
                $now,
            ]
        );

        $activityId = null;
        if ($activityRs !== false && !$activityRs->EOF) {
            $activityId = (int) $activityRs->fields['id'];
        }

        // Create booking record
        $rs = $this->db->Execute(
            'INSERT INTO scheduling_bookings
                (tenant_id, company_code, scheduling_link_id, booker_name, booker_email,
                 start_at, end_at, notes, activity_id, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'confirmed\', ?, ?) RETURNING id',
            [
                $link['tenant_id'],
                $link['company_code'],
                (int) $link['id'],
                $bookerData['booker_name'] ?? '',
                $bookerData['booker_email'] ?? '',
                $startAtStr,
                $endAtStr,
                $bookerData['notes'] ?? null,
                $activityId,
                $now,
                $now,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('SchedulingLinkService::createBooking failed: ' . $this->db->ErrorMsg());
        }

        $bookingId = (!$rs->EOF) ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();

        // Enqueue calendar push for the created activity
        if ($activityId !== null) {
            $this->enqueueCeleryTask('calendar_sync.push_calendar_event', ['activity_id' => $activityId]);
        }

        return $this->findBookingById($bookingId);
    }

    /**
     * Generate a unique URL-safe slug.
     *
     * @return string
     */
    public function generateSlug(): string
    {
        do {
            $slug = bin2hex(random_bytes(6)); // 12 hex chars
            $rs   = $this->db->Execute(
                'SELECT id FROM scheduling_links WHERE slug = ? LIMIT 1',
                [$slug]
            );
        } while ($rs !== false && !$rs->EOF);

        return $slug;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    public function findBySlug(string $slug): ?array
    {
        $rs = $this->db->Execute(
            'SELECT id, tenant_id, company_code, user_id, slug, title, duration_minutes,
                    buffer_minutes, availability_rules, is_active, created_at, updated_at
             FROM scheduling_links
             WHERE slug = ? AND is_active = TRUE AND deleted_at IS NULL
             LIMIT 1',
            [$slug]
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    private function findById(int $id): array
    {
        $rs = $this->db->Execute(
            'SELECT id, slug, title, duration_minutes, buffer_minutes, availability_rules,
                    is_active, created_at, updated_at
             FROM scheduling_links WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Scheduling link {$id} not found.");
        }

        return $rs->fields;
    }

    private function findBookingById(int $id): array
    {
        $rs = $this->db->Execute(
            'SELECT id, scheduling_link_id, booker_name, booker_email, start_at, end_at,
                    notes, activity_id, status, created_at, updated_at
             FROM scheduling_bookings WHERE id = ?',
            [$id]
        );

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Booking {$id} not found.");
        }

        return $rs->fields;
    }

    private function fetchBookedSlots(int $linkId, string $startDate, string $endDate): array
    {
        $rs = $this->db->Execute(
            "SELECT start_at, end_at FROM scheduling_bookings
             WHERE scheduling_link_id = ? AND status != 'cancelled'
               AND start_at <= ? AND end_at >= ?",
            [$linkId, $endDate . ' 23:59:59', $startDate . ' 00:00:00']
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

    private function overlapsBooking(string $start, string $end, array $bookedSlots): bool
    {
        foreach ($bookedSlots as $booked) {
            if ($start < $booked['end_at'] && $end > $booked['start_at']) {
                return true;
            }
        }
        return false;
    }

    private function enqueueCeleryTask(string $taskName, array $kwargs): void
    {
        $redisUrl = $_ENV['REDIS_URL'] ?? getenv('REDIS_URL') ?: 'redis://redis:6379/0';

        $message = json_encode([
            'id'      => bin2hex(random_bytes(16)),
            'task'    => $taskName,
            'args'    => [],
            'kwargs'  => $kwargs,
            'retries' => 0,
        ]);

        try {
            $redis = new \Redis();
            $parts = parse_url($redisUrl);
            $redis->connect($parts['host'] ?? 'redis', (int) ($parts['port'] ?? 6379));
            $redis->rpush('celery', $message);
        } catch (\Throwable $e) {
            error_log("SchedulingLinkService::enqueueCeleryTask failed: " . $e->getMessage());
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
