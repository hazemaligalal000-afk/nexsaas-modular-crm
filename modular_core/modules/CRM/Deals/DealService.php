<?php
/**
 * CRM/Deals/DealService.php
 *
 * Business logic for deal and pipeline management.
 *
 * Requirements: 10.1, 10.2, 10.3, 10.6, 10.7, 10.8, 11.1, 11.3
 */

declare(strict_types=1);

namespace CRM\Deals;

use Core\BaseService;

class DealService extends BaseService
{
    private object $rabbitMQ;
    private string $tenantId;
    private string $companyCode;
    private WinProbabilityService $winProbabilityService;

    /**
     * @param \ADOConnection      $db
     * @param object              $rabbitMQ             RabbitMQ publisher
     * @param string              $tenantId             Current tenant UUID
     * @param string              $companyCode          Two-digit company code
     * @param WinProbabilityService|null $winProbabilityService
     */
    public function __construct(
        $db,
        object $rabbitMQ,
        string $tenantId,
        string $companyCode,
        ?WinProbabilityService $winProbabilityService = null
    ) {
        parent::__construct($db);
        $this->rabbitMQ              = $rabbitMQ;
        $this->tenantId              = $tenantId;
        $this->companyCode           = $companyCode;
        $this->winProbabilityService = $winProbabilityService ?? new WinProbabilityService($db, $rabbitMQ, $tenantId, $companyCode);
    }

    // -------------------------------------------------------------------------
    // create — Requirement 10.1
    // -------------------------------------------------------------------------

    /**
     * Create a new deal.
     *
     * @param  array $data      Deal fields
     * @param  int   $createdBy ID of the authenticated user
     * @return int              New deal ID
     *
     * @throws \RuntimeException on DB error
     */
    public function create(array $data, int $createdBy): int
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $sql = <<<SQL
            INSERT INTO deals
                (tenant_id, company_code, title, contact_id, account_id, pipeline_id,
                 stage_id, value, win_probability, close_date, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        SQL;

        $rs = $this->db->Execute($sql, [
            $this->tenantId,
            $this->companyCode,
            $data['title']           ?? '',
            $data['contact_id']      ?? null,
            $data['account_id']      ?? null,
            $data['pipeline_id']     ?? null,
            $data['stage_id']        ?? null,
            $data['value']           ?? null,
            $data['win_probability'] ?? null,
            $data['close_date']      ?? null,
            $createdBy,
            $now,
            $now,
        ]);

        if ($rs === false) {
            throw new \RuntimeException('DealService::create failed: ' . $this->db->ErrorMsg());
        }

        $id = !$rs->EOF ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();

        // Requirement 11.1 — enqueue win probability request on deal create
        $this->winProbabilityService->onDealChange($id, 'deal.created');

        return $id;
    }

    // -------------------------------------------------------------------------
    // moveStage — Requirement 10.2
    // -------------------------------------------------------------------------

    /**
     * Move a deal to a new pipeline stage.
     *
     * Records the transition in deal_stage_history with timestamp and user,
     * then updates deal.stage_id. Both writes are atomic.
     *
     * @param  int $dealId      Deal to move
     * @param  int $newStageId  Target stage
     * @param  int $userId      User performing the move
     *
     * @throws \InvalidArgumentException if deal not found
     * @throws \RuntimeException         on DB error
     */
    public function moveStage(int $dealId, int $newStageId, int $userId): void
    {
        $this->transaction(function () use ($dealId, $newStageId, $userId): void {
            $deal = $this->findById($dealId);

            if ($deal === null) {
                throw new \InvalidArgumentException("Deal {$dealId} not found.");
            }

            $fromStageId = $deal['stage_id'] !== null ? (int) $deal['stage_id'] : null;
            $now         = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

            // Record history
            $histResult = $this->db->Execute(
                'INSERT INTO deal_stage_history (tenant_id, company_code, deal_id, from_stage_id, to_stage_id, changed_by, changed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$this->tenantId, $this->companyCode, $dealId, $fromStageId, $newStageId, $userId, $now]
            );

            if ($histResult === false) {
                throw new \RuntimeException('DealService::moveStage history insert failed: ' . $this->db->ErrorMsg());
            }

            // Update deal stage
            $updateResult = $this->db->Execute(
                'UPDATE deals SET stage_id = ?, updated_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                [$newStageId, $now, $dealId, $this->tenantId, $this->companyCode]
            );

            if ($updateResult === false) {
                throw new \RuntimeException('DealService::moveStage update failed: ' . $this->db->ErrorMsg());
            }
        });

        // Requirement 11.1 — enqueue win probability request on stage change
        $this->winProbabilityService->onDealChange($dealId, 'deal.stage_changed');
    }

    // -------------------------------------------------------------------------
    // computeForecast — Requirement 10.7
    // -------------------------------------------------------------------------

    /**
     * Compute the weighted pipeline forecast.
     *
     * Forecast = SUM(deal.value * deal.win_probability) for all active deals
     * in the pipeline. win_probability is stored as DECIMAL(5,4) (0.0000–1.0000).
     *
     * @param  int   $pipelineId
     * @return array { weighted_value: float, deal_count: int }
     */
    public function computeForecast(int $pipelineId): array
    {
        $sql = <<<SQL
            SELECT
                COALESCE(SUM(d.value * COALESCE(d.win_probability, 0)), 0) AS weighted_value,
                COUNT(d.id)                                                  AS deal_count
            FROM deals d
            WHERE d.pipeline_id  = ?
              AND d.tenant_id    = ?
              AND d.company_code = ?
              AND d.deleted_at   IS NULL
        SQL;

        $rs = $this->db->Execute($sql, [$pipelineId, $this->tenantId, $this->companyCode]);

        if ($rs === false || $rs->EOF) {
            return ['weighted_value' => 0.0, 'deal_count' => 0];
        }

        return [
            'weighted_value' => (float) $rs->fields['weighted_value'],
            'deal_count'     => (int)   $rs->fields['deal_count'],
        ];
    }

    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    /**
     * Find a single active deal by primary key, scoped to tenant.
     *
     * @param  int        $id
     * @return array|null Row as associative array, or null if not found / deleted
     */
    public function findById(int $id): ?array
    {
        $sql = <<<SQL
            SELECT id, title, contact_id, account_id, pipeline_id, stage_id,
                   value, win_probability, win_probability_updated_at, close_date,
                   is_stale, is_overdue, created_by, created_at, updated_at
            FROM deals
            WHERE id = ?
              AND tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
        SQL;

        $rs = $this->db->Execute($sql, [$id, $this->tenantId, $this->companyCode]);

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    /**
     * Update an existing deal.
     *
     * @param  int   $id
     * @param  array $data
     * @return bool
     *
     * @throws \RuntimeException on DB error
     */
    public function update(int $id, array $data): bool
    {
        unset($data['id'], $data['tenant_id'], $data['company_code'], $data['created_at']);

        if (empty($data)) {
            return false;
        }

        // Detect which win-probability-relevant fields are changing (Requirement 11.1)
        $valueChanged    = array_key_exists('value', $data);
        $dateChanged     = array_key_exists('close_date', $data);

        $data['updated_at'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $setClauses = [];
        $values     = [];
        foreach ($data as $col => $val) {
            $setClauses[] = "{$col} = ?";
            $values[]     = $val;
        }

        $values[] = $id;
        $values[] = $this->tenantId;
        $values[] = $this->companyCode;

        $sql = sprintf(
            'UPDATE deals SET %s WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            implode(', ', $setClauses)
        );

        $result = $this->db->Execute($sql, $values);

        if ($result === false) {
            throw new \RuntimeException('DealService::update failed: ' . $this->db->ErrorMsg());
        }

        $affected = $this->db->Affected_Rows() > 0;

        // Requirement 11.1 — enqueue win probability request on value or date change
        if ($affected) {
            if ($valueChanged) {
                $this->winProbabilityService->onDealChange($id, 'deal.value_changed');
            }
            if ($dateChanged) {
                $this->winProbabilityService->onDealChange($id, 'deal.date_changed');
            }
        }

        return $affected;
    }

    // -------------------------------------------------------------------------
    // delete (soft)
    // -------------------------------------------------------------------------

    /**
     * Soft-delete a deal.
     *
     * @param  int  $id
     * @return bool
     *
     * @throws \RuntimeException on DB error
     */
    public function delete(int $id): bool
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $result = $this->db->Execute(
            'UPDATE deals SET deleted_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$now, $id, $this->tenantId, $this->companyCode]
        );

        if ($result === false) {
            throw new \RuntimeException('DealService::delete failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // list (paginated)
    // -------------------------------------------------------------------------

    /**
     * List deals for the current tenant with optional pagination.
     *
     * @param  int $limit
     * @param  int $offset
     * @return array
     */
    public function list(int $limit = 50, int $offset = 0): array
    {
        $sql = <<<SQL
            SELECT id, title, contact_id, account_id, pipeline_id, stage_id,
                   value, win_probability, close_date, is_stale, is_overdue,
                   created_at, updated_at
            FROM deals
            WHERE tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        SQL;

        $rs = $this->db->Execute($sql, [
            $this->tenantId,
            $this->companyCode,
            $limit,
            $offset,
        ]);

        if ($rs === false) {
            throw new \RuntimeException('DealService::list failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // listFiltered — Requirement 10.5 (sortable, filterable list view)
    // -------------------------------------------------------------------------

    /**
     * List deals with optional filters and sorting.
     *
     * @param  int    $limit
     * @param  int    $offset
     * @param  array  $filters  Keys: pipeline_id, stage_id, owner_id, is_overdue, is_stale
     * @param  string $sort     Column name (created_at|updated_at|close_date|value|title)
     * @param  string $dir      ASC|DESC
     * @return array
     */
    public function listFiltered(
        int    $limit   = 50,
        int    $offset  = 0,
        array  $filters = [],
        string $sort    = 'created_at',
        string $dir     = 'DESC'
    ): array {
        $where  = ['tenant_id = ?', 'company_code = ?', 'deleted_at IS NULL'];
        $params = [$this->tenantId, $this->companyCode];

        foreach (['pipeline_id', 'stage_id', 'owner_id'] as $col) {
            if (isset($filters[$col])) {
                $where[]  = "{$col} = ?";
                $params[] = (int) $filters[$col];
            }
        }
        foreach (['is_overdue', 'is_stale'] as $col) {
            if (isset($filters[$col])) {
                $where[]  = "{$col} = ?";
                $params[] = $filters[$col] ? 'true' : 'false';
            }
        }

        $allowedSort = ['created_at', 'updated_at', 'close_date', 'value', 'title'];
        $sortCol = in_array($sort, $allowedSort, true) ? $sort : 'created_at';
        $dirSql  = $dir === 'ASC' ? 'ASC' : 'DESC';

        $sql = sprintf(
            'SELECT id, title, contact_id, account_id, pipeline_id, stage_id,
                    value, currency_code, win_probability, close_date,
                    is_stale, is_overdue, owner_id, created_at, updated_at
             FROM deals
             WHERE %s
             ORDER BY %s %s
             LIMIT ? OFFSET ?',
            implode(' AND ', $where),
            $sortCol,
            $dirSql
        );

        $params[] = $limit;
        $params[] = $offset;

        $rs = $this->db->Execute($sql, $params);

        if ($rs === false) {
            throw new \RuntimeException('DealService::listFiltered failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // getKanban — Requirement 10.4
    // -------------------------------------------------------------------------

    /**
     * Return deals grouped by stage for a Kanban board view.
     *
     * @param  int   $pipelineId
     * @return array Stages with nested deals arrays
     */
    public function getKanban(int $pipelineId): array
    {
        // Fetch stages ordered by position
        $stagesRs = $this->db->Execute(
            'SELECT id, name, position, win_probability, is_won, is_lost
             FROM pipeline_stages
             WHERE pipeline_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL
             ORDER BY position ASC',
            [$pipelineId, $this->tenantId, $this->companyCode]
        );

        $stages = [];
        if ($stagesRs !== false) {
            while (!$stagesRs->EOF) {
                $stage           = $stagesRs->fields;
                $stage['deals']  = [];
                $stages[(int) $stage['id']] = $stage;
                $stagesRs->MoveNext();
            }
        }

        if (empty($stages)) {
            return [];
        }

        // Fetch deals for this pipeline
        $dealsRs = $this->db->Execute(
            'SELECT id, title, contact_id, account_id, stage_id, value, win_probability, close_date, is_stale, is_overdue
             FROM deals
             WHERE pipeline_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL
             ORDER BY created_at DESC',
            [$pipelineId, $this->tenantId, $this->companyCode]
        );

        if ($dealsRs !== false) {
            while (!$dealsRs->EOF) {
                $deal    = $dealsRs->fields;
                $stageId = (int) $deal['stage_id'];
                if (isset($stages[$stageId])) {
                    $stages[$stageId]['deals'][] = $deal;
                }
                $dealsRs->MoveNext();
            }
        }

        return array_values($stages);
    }
}
