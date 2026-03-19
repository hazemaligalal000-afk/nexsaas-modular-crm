<?php
/**
 * CRM/Inbox/ConversationMetricsService.php
 *
 * Tracks first_response_at, handle_time, and resolved_at per conversation.
 *
 * - first_response_at: timestamp of the first outbound message on a conversation
 *   that previously had only inbound messages.
 * - handle_time: seconds between conversation created_at and resolved_at.
 * - resolved_at: timestamp when the conversation was marked resolved.
 *
 * Requirements: 12.6
 */

declare(strict_types=1);

namespace CRM\Inbox;

use Core\BaseService;

class ConversationMetricsService extends BaseService
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
    // Event hooks called by InboxService
    // -------------------------------------------------------------------------

    /**
     * Called when an inbound message is received.
     * No metric update needed on inbound — first_response_at is set on first outbound.
     *
     * @param int $conversationId
     */
    public function onInboundMessage(int $conversationId): void
    {
        // No-op: metrics are updated on outbound reply and resolution.
        // Reserved for future sentiment aggregation or SLA breach detection.
    }

    /**
     * Called when an outbound message is sent.
     *
     * Sets first_response_at on the conversation if not already set and
     * there is at least one prior inbound message (Req 12.6).
     *
     * @param int   $conversationId
     * @param array $conversation   Current conversation row
     */
    public function onOutboundMessage(int $conversationId, array $conversation): void
    {
        // Only set first_response_at once
        if (!empty($conversation['first_response_at'])) {
            return;
        }

        // Verify there is at least one inbound message before this outbound
        $rs = $this->db->Execute(
            'SELECT COUNT(*) AS cnt FROM inbox_messages WHERE conversation_id = ? AND direction = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$conversationId, 'inbound', $this->tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return;
        }

        $inboundCount = (int) $rs->fields['cnt'];
        if ($inboundCount === 0) {
            return; // No inbound messages yet — this is an outbound-initiated conversation
        }

        $now = $this->now();
        $this->db->Execute(
            'UPDATE inbox_conversations SET first_response_at = ?, updated_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND first_response_at IS NULL AND deleted_at IS NULL',
            [$now, $now, $conversationId, $this->tenantId, $this->companyCode]
        );
    }

    /**
     * Called when a conversation is resolved.
     *
     * Sets resolved_at if not already set. handle_time is computed on-the-fly
     * in getMetrics() as (resolved_at - created_at) in seconds.
     *
     * @param int   $conversationId
     * @param array $conversation   Current conversation row
     */
    public function onResolved(int $conversationId, array $conversation): void
    {
        if (!empty($conversation['resolved_at'])) {
            return; // Already resolved
        }

        $now = $this->now();
        $this->db->Execute(
            'UPDATE inbox_conversations SET resolved_at = ?, updated_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND resolved_at IS NULL AND deleted_at IS NULL',
            [$now, $now, $conversationId, $this->tenantId, $this->companyCode]
        );
    }

    // -------------------------------------------------------------------------
    // Metrics retrieval — Requirement 12.6
    // -------------------------------------------------------------------------

    /**
     * Return computed metrics for a single conversation.
     *
     * Returns:
     * - first_response_at  (string|null)  UTC timestamp of first agent reply
     * - first_response_seconds (int|null) seconds from created_at to first_response_at
     * - handle_time_seconds   (int|null)  seconds from created_at to resolved_at
     * - resolved_at           (string|null) UTC timestamp of resolution
     *
     * @param  int   $conversationId
     * @return array
     */
    public function getMetrics(int $conversationId): array
    {
        $rs = $this->db->Execute(
            'SELECT created_at, first_response_at, resolved_at FROM inbox_conversations WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$conversationId, $this->tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return [
                'first_response_at'       => null,
                'first_response_seconds'  => null,
                'handle_time_seconds'     => null,
                'resolved_at'             => null,
            ];
        }

        $row       = $rs->fields;
        $createdAt = $row['created_at'];

        $firstResponseSeconds = null;
        if (!empty($row['first_response_at'])) {
            $firstResponseSeconds = $this->diffSeconds($createdAt, $row['first_response_at']);
        }

        $handleTimeSeconds = null;
        if (!empty($row['resolved_at'])) {
            $handleTimeSeconds = $this->diffSeconds($createdAt, $row['resolved_at']);
        }

        return [
            'first_response_at'      => $row['first_response_at'] ?? null,
            'first_response_seconds' => $firstResponseSeconds,
            'handle_time_seconds'    => $handleTimeSeconds,
            'resolved_at'            => $row['resolved_at'] ?? null,
        ];
    }

    /**
     * Compute aggregate metrics across all conversations for the tenant.
     *
     * Returns averages for first_response_time and handle_time.
     *
     * @param  array $filters  Optional: channel, assigned_agent_id, date_from, date_to
     * @return array
     */
    public function getAggregateMetrics(array $filters = []): array
    {
        $where  = ['tenant_id = ?', 'company_code = ?', 'deleted_at IS NULL'];
        $params = [$this->tenantId, $this->companyCode];

        if (!empty($filters['channel'])) {
            $where[]  = 'channel = ?';
            $params[] = $filters['channel'];
        }
        if (!empty($filters['assigned_agent_id'])) {
            $where[]  = 'assigned_agent_id = ?';
            $params[] = $filters['assigned_agent_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = 'SELECT
                    COUNT(*) AS total_conversations,
                    COUNT(first_response_at) AS conversations_with_response,
                    AVG(EXTRACT(EPOCH FROM (first_response_at - created_at))) AS avg_first_response_seconds,
                    COUNT(resolved_at) AS resolved_conversations,
                    AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))) AS avg_handle_time_seconds
                FROM inbox_conversations
                WHERE ' . implode(' AND ', $where);

        $rs = $this->db->Execute($sql, $params);

        if ($rs === false || $rs->EOF) {
            return [
                'total_conversations'         => 0,
                'conversations_with_response' => 0,
                'avg_first_response_seconds'  => null,
                'resolved_conversations'      => 0,
                'avg_handle_time_seconds'     => null,
            ];
        }

        $row = $rs->fields;
        return [
            'total_conversations'         => (int) $row['total_conversations'],
            'conversations_with_response' => (int) $row['conversations_with_response'],
            'avg_first_response_seconds'  => $row['avg_first_response_seconds'] !== null
                                                ? (float) $row['avg_first_response_seconds']
                                                : null,
            'resolved_conversations'      => (int) $row['resolved_conversations'],
            'avg_handle_time_seconds'     => $row['avg_handle_time_seconds'] !== null
                                                ? (float) $row['avg_handle_time_seconds']
                                                : null,
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Compute the difference in seconds between two UTC timestamp strings.
     *
     * @param  string $from  Earlier timestamp
     * @param  string $to    Later timestamp
     * @return int           Seconds (non-negative)
     */
    private function diffSeconds(string $from, string $to): int
    {
        try {
            $tz   = new \DateTimeZone('UTC');
            $dtFrom = new \DateTimeImmutable($from, $tz);
            $dtTo   = new \DateTimeImmutable($to, $tz);
            $diff   = $dtTo->getTimestamp() - $dtFrom->getTimestamp();
            return max(0, $diff);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
