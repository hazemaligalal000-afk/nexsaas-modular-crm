<?php
/**
 * CRM/Email/EmailTrackingService.php
 *
 * Records email open and link-click tracking events.
 * Provides per-message tracking statistics.
 *
 * Requirements: 13.4
 */

declare(strict_types=1);

namespace CRM\Email;

use Core\BaseService;

class EmailTrackingService extends BaseService
{
    public function __construct($db)
    {
        parent::__construct($db);
    }

    // -------------------------------------------------------------------------
    // Record open event — Requirement 13.4
    // -------------------------------------------------------------------------

    /**
     * Record an email open event.
     *
     * Looks up the inbox_message by tracking_token, inserts an event record,
     * and sets inbox_messages.opened_at on first open.
     *
     * @param  string $trackingToken  UUID from the tracking pixel URL
     * @param  string $ipAddress
     * @param  string $userAgent
     */
    public function recordOpen(string $trackingToken, string $ipAddress, string $userAgent): void
    {
        $message = $this->findMessageByToken($trackingToken);
        if ($message === null) {
            return; // Unknown token — silently ignore (bot/preview)
        }

        $now = $this->now();

        // Insert tracking event
        $this->db->Execute(
            'INSERT INTO email_tracking_events
                (tenant_id, company_code, inbox_message_id, event_type, tracking_token,
                 ip_address, user_agent, occurred_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $message['tenant_id'],
                $message['company_code'],
                (int) $message['id'],
                'open',
                $trackingToken,
                $ipAddress,
                $userAgent,
                $now,
                $now,
                $now,
            ]
        );

        // Set opened_at on first open only
        if (empty($message['opened_at'])) {
            $this->db->Execute(
                'UPDATE inbox_messages SET opened_at = ?, updated_at = ? WHERE id = ? AND opened_at IS NULL',
                [$now, $now, (int) $message['id']]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Record click event — Requirement 13.4
    // -------------------------------------------------------------------------

    /**
     * Record a link click event and return the original destination URL.
     *
     * @param  string $trackingToken  UUID from the tracking redirect URL
     * @param  string $url            The original destination URL (from ?url= param)
     * @param  string $ipAddress
     * @param  string $userAgent
     * @return string  The original destination URL to redirect to
     */
    public function recordClick(string $trackingToken, string $url, string $ipAddress, string $userAgent): string
    {
        $message = $this->findMessageByToken($trackingToken);

        $now = $this->now();

        if ($message !== null) {
            $this->db->Execute(
                'INSERT INTO email_tracking_events
                    (tenant_id, company_code, inbox_message_id, event_type, tracking_token,
                     link_url, link_destination, ip_address, user_agent, occurred_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $message['tenant_id'],
                    $message['company_code'],
                    (int) $message['id'],
                    'click',
                    $trackingToken,
                    $url,
                    $url,
                    $ipAddress,
                    $userAgent,
                    $now,
                    $now,
                    $now,
                ]
            );
        }

        return $url;
    }

    // -------------------------------------------------------------------------
    // Tracking stats — Requirement 13.4
    // -------------------------------------------------------------------------

    /**
     * Return open count, click count, and last opened timestamp for a message.
     *
     * @param  int    $messageId
     * @param  string $tenantId
     * @return array  ['open_count' => int, 'click_count' => int, 'last_opened_at' => string|null]
     */
    public function getTrackingStats(int $messageId, string $tenantId): array
    {
        // Verify message belongs to tenant
        $msgRs = $this->db->Execute(
            'SELECT id, opened_at FROM inbox_messages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$messageId, $tenantId]
        );

        if ($msgRs === false || $msgRs->EOF) {
            return ['open_count' => 0, 'click_count' => 0, 'last_opened_at' => null];
        }

        $openedAt = $msgRs->fields['opened_at'];

        // Count opens
        $openRs = $this->db->Execute(
            "SELECT COUNT(*) AS cnt FROM email_tracking_events
             WHERE inbox_message_id = ? AND event_type = 'open' AND deleted_at IS NULL",
            [$messageId]
        );
        $openCount = ($openRs !== false && !$openRs->EOF) ? (int) $openRs->fields['cnt'] : 0;

        // Count clicks
        $clickRs = $this->db->Execute(
            "SELECT COUNT(*) AS cnt FROM email_tracking_events
             WHERE inbox_message_id = ? AND event_type = 'click' AND deleted_at IS NULL",
            [$messageId]
        );
        $clickCount = ($clickRs !== false && !$clickRs->EOF) ? (int) $clickRs->fields['cnt'] : 0;

        // Last opened
        $lastRs = $this->db->Execute(
            "SELECT MAX(occurred_at) AS last_at FROM email_tracking_events
             WHERE inbox_message_id = ? AND event_type = 'open' AND deleted_at IS NULL",
            [$messageId]
        );
        $lastOpenedAt = ($lastRs !== false && !$lastRs->EOF) ? $lastRs->fields['last_at'] : $openedAt;

        return [
            'open_count'     => $openCount,
            'click_count'    => $clickCount,
            'last_opened_at' => $lastOpenedAt,
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Find an inbox_message by its tracking_token.
     *
     * @param  string $trackingToken
     * @return array|null
     */
    private function findMessageByToken(string $trackingToken): ?array
    {
        $rs = $this->db->Execute(
            'SELECT id, tenant_id, company_code, opened_at FROM inbox_messages
             WHERE tracking_token = ? AND deleted_at IS NULL LIMIT 1',
            [$trackingToken]
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
