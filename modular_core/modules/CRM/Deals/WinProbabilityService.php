<?php
/**
 * CRM/Deals/WinProbabilityService.php
 *
 * Handles win probability enqueueing and persistence for deals.
 *
 * On deal create/stage/value/date change: enqueues 'deal.win_probability_request'
 * to RabbitMQ within 5s. Applies and persists the returned probability clamped
 * to [0.0, 1.0].
 *
 * Requirements: 11.1, 11.3
 */

declare(strict_types=1);

namespace CRM\Deals;

use Core\BaseService;

class WinProbabilityService extends BaseService
{
    private object $rabbitMQ;
    private string $tenantId;
    private string $companyCode;

    /** Events that trigger a win probability re-computation */
    private const TRIGGER_EVENTS = [
        'deal.created',
        'deal.stage_changed',
        'deal.value_changed',
        'deal.date_changed',
    ];

    /**
     * @param \ADOConnection $db
     * @param object         $rabbitMQ    RabbitMQ publisher
     * @param string         $tenantId    Current tenant UUID
     * @param string         $companyCode Two-digit company code
     */
    public function __construct($db, object $rabbitMQ, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->rabbitMQ    = $rabbitMQ;
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    // -------------------------------------------------------------------------
    // onDealChange — Requirement 11.1
    // -------------------------------------------------------------------------

    /**
     * React to a deal lifecycle event.
     *
     * On deal create/stage/value/date change, enqueues a
     * 'deal.win_probability_request' message to RabbitMQ within 5s.
     * Unrecognised events are silently ignored.
     *
     * @param int    $dealId  Deal primary key
     * @param string $event   Event name (e.g. 'deal.created')
     */
    public function onDealChange(int $dealId, string $event): void
    {
        if (!in_array($event, self::TRIGGER_EVENTS, true)) {
            return;
        }

        $this->rabbitMQ->publish(
            'crm.events',
            'deal.win_probability_request',
            [
                'deal_id'      => $dealId,
                'tenant_id'    => $this->tenantId,
                'company_code' => $this->companyCode,
                'triggered_by' => $event,
                'enqueued_at'  => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            ]
        );
    }

    // -------------------------------------------------------------------------
    // applyWinProbability — Requirement 11.3
    // -------------------------------------------------------------------------

    /**
     * Persist a win probability value for a deal.
     *
     * The probability is clamped to [0.0, 1.0] before being stored.
     * Also updates win_probability_updated_at to the current UTC timestamp.
     *
     * @param int   $dealId      Deal primary key
     * @param float $probability Raw probability value (will be clamped)
     *
     * @throws \RuntimeException on DB error
     */
    public function applyWinProbability(int $dealId, float $probability): void
    {
        // Clamp to [0.0, 1.0]
        $clamped = max(0.0, min(1.0, $probability));

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $result = $this->db->Execute(
            'UPDATE deals
             SET win_probability = ?, win_probability_updated_at = ?, updated_at = ?
             WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$clamped, $now, $now, $dealId, $this->tenantId, $this->companyCode]
        );

        if ($result === false) {
            throw new \RuntimeException(
                'WinProbabilityService::applyWinProbability failed: ' . $this->db->ErrorMsg()
            );
        }
    }
}
