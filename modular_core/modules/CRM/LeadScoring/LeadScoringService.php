<?php
/**
 * CRM/LeadScoring/LeadScoringService.php
 *
 * Handles lead score event enqueueing and score persistence with
 * WebSocket notification on significant score changes.
 *
 * Requirements: 8.1, 8.3, 8.6
 */

declare(strict_types=1);

namespace CRM\LeadScoring;

use Core\BaseService;

class LeadScoringService extends BaseService
{
    private object $rabbitMQ;
    private object $redis;
    private string $tenantId;
    private string $companyCode;

    /**
     * @param \ADOConnection $db
     * @param object         $rabbitMQ  RabbitMQ publisher (publish(exchange, routingKey, payload))
     * @param object         $redis     Redis client (rpush(key, value))
     * @param string         $tenantId
     * @param string         $companyCode
     */
    public function __construct(
        $db,
        object $rabbitMQ,
        object $redis,
        string $tenantId,
        string $companyCode
    ) {
        parent::__construct($db);
        $this->rabbitMQ    = $rabbitMQ;
        $this->redis       = $redis;
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    // -------------------------------------------------------------------------
    // Requirement 8.1 — enqueue score_request within 5s of lead.captured / lead.updated
    // -------------------------------------------------------------------------

    /**
     * React to a lead lifecycle event.
     *
     * On 'lead.captured' or 'lead.updated' events, enqueues a
     * 'lead.score_request' message to the 'crm.events' RabbitMQ exchange
     * within 5 seconds.
     *
     * @param int    $leadId  The lead that triggered the event.
     * @param string $event   Event name, e.g. 'lead.captured' or 'lead.updated'.
     */
    public function onLeadEvent(int $leadId, string $event): void
    {
        if (!in_array($event, ['lead.captured', 'lead.updated'], true)) {
            return;
        }

        $this->rabbitMQ->publish('crm.events', 'lead.score_request', [
            'lead_id'      => $leadId,
            'tenant_id'    => $this->tenantId,
            'company_code' => $this->companyCode,
            'triggered_by' => $event,
            'requested_at' => $this->now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Requirements 8.3, 8.6 — persist score; notify on delta > 20
    // -------------------------------------------------------------------------

    /**
     * Persist an AI-computed score on the lead record.
     *
     * - Clamps score to [0, 100].
     * - Reads the current score from the DB to compute the delta.
     * - If |new_score - old_score| > 20, pushes a WebSocket notification
     *   to the Redis pending list for the lead's assigned owner within 30s.
     *
     * @param int $leadId  Target lead.
     * @param int $score   New score (0–100); values outside range are clamped.
     *
     * @throws \RuntimeException  On DB failure.
     */
    public function applyScore(int $leadId, int $score): void
    {
        // Clamp to valid range
        $score = max(0, min(100, $score));

        // Fetch current lead row (tenant-scoped)
        $lead = $this->findLeadById($leadId);
        if ($lead === null) {
            throw new \InvalidArgumentException("Lead {$leadId} not found.");
        }

        $oldScore = ($lead['lead_score'] !== null) ? (int) $lead['lead_score'] : null;

        // Persist new score + timestamp
        $now = $this->now();
        $result = $this->db->Execute(
            'UPDATE leads SET lead_score = ?, score_updated_at = ?, updated_at = ?
              WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$score, $now, $now, $leadId, $this->tenantId, $this->companyCode]
        );

        if ($result === false) {
            throw new \RuntimeException('LeadScoringService::applyScore DB update failed: ' . $this->db->ErrorMsg());
        }

        // Requirement 8.6 — notify on significant change (delta > 20)
        if ($oldScore !== null && abs($score - $oldScore) > 20) {
            $this->pushScoreChangeNotification($lead, $oldScore, $score);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch a single lead row scoped to this tenant + company.
     */
    private function findLeadById(int $id): ?array
    {
        $rs = $this->db->Execute(
            'SELECT id, lead_score, owner_id
               FROM leads
              WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$id, $this->tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    /**
     * Push a WebSocket notification payload to the Redis pending list
     * for the lead's assigned user.
     *
     * Notification payload shape (Requirement 8.6 / design spec):
     *   { type, lead_id, old_score, new_score, tenant_id }
     *
     * Redis key: notifications:pending:{user_id}
     */
    private function pushScoreChangeNotification(array $lead, int $oldScore, int $newScore): void
    {
        // Resolve the assigned user via owner_id
        $userId = $lead['owner_id'] ?? null;
        if ($userId === null) {
            return; // No owner to notify
        }

        $payload = json_encode([
            'type'      => 'lead_score_change',
            'lead_id'   => (int) $lead['id'],
            'old_score' => $oldScore,
            'new_score' => $newScore,
            'tenant_id' => $this->tenantId,
        ]);

        $redisKey = "notifications:pending:{$userId}";
        $this->redis->rpush($redisKey, $payload);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
