<?php
/**
 * CRM/Accounts/AccountService.php
 *
 * Business logic for account management:
 *   - Hierarchy validation (max 5 levels, stored as hierarchy_depth 0–4)
 *   - Contact linking / unlinking (Req 9.2)
 *   - Timeline aggregation (Req 9.3)
 *   - Aggregate deal value and win rate (Req 9.5)
 *
 * Requirements: 9.2, 9.3, 9.4, 9.5
 */

declare(strict_types=1);

namespace CRM\Accounts;

use Core\BaseService;

class AccountService extends BaseService
{
    /** Maximum hierarchy_depth value (0-based). Depth 4 = level 5. */
    private const MAX_DEPTH = 4;

    private string $tenantId;
    private string $companyCode;

    /**
     * @param \ADOConnection $db
     * @param string         $tenantId    Current tenant UUID
     * @param string         $companyCode Two-digit company code
     */
    public function __construct($db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    // -------------------------------------------------------------------------
    // create — Requirements 9.2, 9.4
    // -------------------------------------------------------------------------

    /**
     * Create a new account.
     *
     * Hierarchy depth is stored 0-based (root = 0, max = 4 = level 5).
     * If parent_account_id is provided, the parent's hierarchy_depth must be
     * < MAX_DEPTH (4); otherwise the depth limit is exceeded.
     *
     * @param  array $data      Account fields (company_name required)
     * @param  int   $createdBy Authenticated user ID
     * @return int              New account ID
     *
     * @throws \InvalidArgumentException on validation failure
     * @throws \RuntimeException         on DB error
     */
    public function create(array $data, int $createdBy): int
    {
        $parentId = isset($data['parent_account_id']) ? (int) $data['parent_account_id'] : null;
        $depth    = 0;

        if ($parentId !== null && $parentId > 0) {
            $parent = $this->findById($parentId);

            if ($parent === null) {
                throw new \InvalidArgumentException("Parent account {$parentId} not found.");
            }

            $parentDepth = (int) ($parent['hierarchy_depth'] ?? $parent['hierarchy_level'] ?? 0);

            // hierarchy_depth is 0-based; if parent is already at MAX_DEPTH (4 = level 5),
            // a child would be at depth 5 = level 6, which exceeds the 5-level limit.
            if ($parentDepth >= self::MAX_DEPTH) {
                $parentLevel = $parentDepth + 1;
                throw new \InvalidArgumentException(
                    "Account hierarchy depth limit reached. Parent is at level {$parentLevel}; max is 5. (Req 9.4)"
                );
            }

            $depth = $parentDepth + 1;
        }

        $name = $data['company_name'] ?? $data['name'] ?? '';
        if ($name === '') {
            throw new \InvalidArgumentException('Account company_name is required.');
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $sql = <<<SQL
            INSERT INTO accounts
                (tenant_id, company_code, company_name, parent_account_id, hierarchy_depth,
                 industry, website, phone, billing_address, annual_revenue, employee_count,
                 owner_id, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        SQL;

        $rs = $this->db->Execute($sql, [
            $this->tenantId,
            $this->companyCode,
            $name,
            $parentId,
            $depth,
            $data['industry']       ?? null,
            $data['website']        ?? null,
            $data['phone']          ?? null,
            isset($data['billing_address']) ? json_encode($data['billing_address']) : null,
            $data['annual_revenue'] ?? null,
            $data['employee_count'] ?? null,
            $data['owner_id']       ?? null,
            $createdBy,
            $now,
            $now,
        ]);

        if ($rs === false) {
            throw new \RuntimeException('AccountService::create failed: ' . $this->db->ErrorMsg());
        }

        if (!$rs->EOF) {
            return (int) $rs->fields['id'];
        }

        return (int) $this->db->Insert_ID();
    }

    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    /**
     * Find a single active account by primary key, scoped to tenant.
     *
     * @param  int        $id
     * @return array|null Row as associative array, or null if not found / deleted
     */
    public function findById(int $id): ?array
    {
        $sql = <<<SQL
            SELECT id, company_name, parent_account_id, hierarchy_depth,
                   industry, website, phone, billing_address,
                   annual_revenue, employee_count, owner_id,
                   churn_score, churn_score_updated_at,
                   total_deal_value, win_rate,
                   created_by, created_at, updated_at
            FROM accounts
            WHERE id = ?
              AND tenant_id = ?
              AND company_code = ?
              AND deleted_at IS NULL
        SQL;

        $rs = $this->db->Execute($sql, [$id, $this->tenantId, $this->companyCode]);

        if ($rs === false || $rs->EOF) {
            return null;
        }

        $row = $rs->fields;

        // Expose hierarchy_level (1-based) alongside hierarchy_depth for convenience
        if (isset($row['hierarchy_depth'])) {
            $row['hierarchy_level'] = (int) $row['hierarchy_depth'] + 1;
        }

        return $row;
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    /**
     * Update an existing account.
     *
     * @param  int   $id
     * @param  array $data  Fields to update (hierarchy fields are immutable)
     * @return bool         true if a row was updated
     *
     * @throws \RuntimeException on DB error
     */
    public function update(int $id, array $data): bool
    {
        // Immutable fields — never allow direct update
        unset(
            $data['id'], $data['tenant_id'], $data['company_code'],
            $data['created_at'], $data['hierarchy_depth'], $data['hierarchy_level'],
            $data['parent_account_id']
        );

        if (empty($data)) {
            return false;
        }

        // Normalise company_name alias
        if (isset($data['name']) && !isset($data['company_name'])) {
            $data['company_name'] = $data['name'];
            unset($data['name']);
        }

        if (isset($data['billing_address']) && is_array($data['billing_address'])) {
            $data['billing_address'] = json_encode($data['billing_address']);
        }

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
            'UPDATE accounts SET %s WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            implode(', ', $setClauses)
        );

        $result = $this->db->Execute($sql, $values);

        if ($result === false) {
            throw new \RuntimeException('AccountService::update failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // delete (soft)
    // -------------------------------------------------------------------------

    /**
     * Soft-delete an account by setting deleted_at.
     *
     * @param  int  $id
     * @return bool true if a row was deleted
     *
     * @throws \RuntimeException on DB error
     */
    public function delete(int $id): bool
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $result = $this->db->Execute(
            'UPDATE accounts SET deleted_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$now, $id, $this->tenantId, $this->companyCode]
        );

        if ($result === false) {
            throw new \RuntimeException('AccountService::delete failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // list (paginated)
    // -------------------------------------------------------------------------

    /**
     * List accounts for the current tenant with optional pagination.
     *
     * @param  int $limit
     * @param  int $offset
     * @return array
     */
    public function list(int $limit = 50, int $offset = 0): array
    {
        $sql = <<<SQL
            SELECT id, company_name, parent_account_id, hierarchy_depth,
                   industry, website, phone, owner_id,
                   total_deal_value, win_rate, created_at, updated_at
            FROM accounts
            WHERE tenant_id = ?
              AND company_code = ?
              AND deleted_at IS NULL
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
            throw new \RuntimeException('AccountService::list failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $row = $rs->fields;
            $row['hierarchy_level'] = (int) $row['hierarchy_depth'] + 1;
            $rows[] = $row;
            $rs->MoveNext();
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Contact linking — Requirement 9.2
    // -------------------------------------------------------------------------

    /**
     * Link a contact to an account.
     *
     * Idempotent: silently succeeds if the link already exists.
     *
     * @param  int $accountId
     * @param  int $contactId
     * @param  int $createdBy
     * @return void
     *
     * @throws \InvalidArgumentException if account not found
     * @throws \RuntimeException         on DB error
     */
    public function linkContact(int $accountId, int $contactId, int $createdBy): void
    {
        if ($this->findById($accountId) === null) {
            throw new \InvalidArgumentException("Account {$accountId} not found.");
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        // INSERT … ON CONFLICT DO NOTHING for idempotency
        $sql = <<<SQL
            INSERT INTO account_contacts (tenant_id, company_code, account_id, contact_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT (tenant_id, account_id, contact_id) DO NOTHING
        SQL;

        $result = $this->db->Execute($sql, [
            $this->tenantId,
            $this->companyCode,
            $accountId,
            $contactId,
            $createdBy,
            $now,
        ]);

        if ($result === false) {
            throw new \RuntimeException('AccountService::linkContact failed: ' . $this->db->ErrorMsg());
        }
    }

    /**
     * Unlink a contact from an account.
     *
     * @param  int  $accountId
     * @param  int  $contactId
     * @return bool true if the link existed and was removed
     *
     * @throws \RuntimeException on DB error
     */
    public function unlinkContact(int $accountId, int $contactId): bool
    {
        $result = $this->db->Execute(
            'DELETE FROM account_contacts WHERE tenant_id = ? AND account_id = ? AND contact_id = ?',
            [$this->tenantId, $accountId, $contactId]
        );

        if ($result === false) {
            throw new \RuntimeException('AccountService::unlinkContact failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    /**
     * Get all contacts linked to an account.
     *
     * @param  int $accountId
     * @return array Contact rows
     */
    public function getContacts(int $accountId): array
    {
        $sql = <<<SQL
            SELECT c.id, c.first_name, c.last_name, c.email, c.phone,
                   c.company_name, c.job_title, c.created_at
            FROM contacts c
            INNER JOIN account_contacts ac
                ON ac.contact_id = c.id
               AND ac.tenant_id  = c.tenant_id
            WHERE ac.account_id   = ?
              AND ac.tenant_id    = ?
              AND ac.company_code = ?
              AND c.deleted_at IS NULL
            ORDER BY c.last_name, c.first_name
        SQL;

        $rs = $this->db->Execute($sql, [$accountId, $this->tenantId, $this->companyCode]);

        if ($rs === false) {
            throw new \RuntimeException('AccountService::getContacts failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // getTimeline — Requirement 9.3
    // -------------------------------------------------------------------------

    /**
     * Fetch the unified activity timeline for an account.
     *
     * Merges activities, deals, and notes linked to the account,
     * ordered by created_at descending.
     *
     * @param  int   $accountId
     * @return array Timeline entries
     */
    public function getTimeline(int $accountId): array
    {
        $timeline = [];

        // Activities
        $rs = $this->db->Execute(
            "SELECT id, 'activity' AS type, activity_type AS subtype, subject, created_at
             FROM activities
             WHERE account_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL
             ORDER BY created_at DESC",
            [$accountId, $this->tenantId, $this->companyCode]
        );
        if ($rs !== false) {
            while (!$rs->EOF) {
                $timeline[] = $rs->fields;
                $rs->MoveNext();
            }
        }

        // Deals
        $rs = $this->db->Execute(
            "SELECT id, 'deal' AS type, title AS subtype, value, created_at
             FROM deals
             WHERE account_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL
             ORDER BY created_at DESC",
            [$accountId, $this->tenantId, $this->companyCode]
        );
        if ($rs !== false) {
            while (!$rs->EOF) {
                $timeline[] = $rs->fields;
                $rs->MoveNext();
            }
        }

        // Notes
        $rs = $this->db->Execute(
            "SELECT id, 'note' AS type, body AS subtype, created_at
             FROM notes
             WHERE account_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL
             ORDER BY created_at DESC",
            [$accountId, $this->tenantId, $this->companyCode]
        );
        if ($rs !== false) {
            while (!$rs->EOF) {
                $timeline[] = $rs->fields;
                $rs->MoveNext();
            }
        }

        usort($timeline, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $timeline;
    }

    // -------------------------------------------------------------------------
    // computeAggregates — Requirement 9.5
    // -------------------------------------------------------------------------

    /**
     * Compute aggregate deal metrics for an account.
     *
     * Win rate = (deals in a closed-won stage) / (all closed deals) × 100.
     * Uses pipeline_stages.is_closed_won to identify won deals.
     *
     * @param  int   $accountId
     * @return array { total_deal_value: float, win_rate: float, deal_count: int, won_count: int }
     */
    public function computeAggregates(int $accountId): array
    {
        $sql = <<<SQL
            SELECT
                COALESCE(SUM(d.value), 0)                                                   AS total_deal_value,
                COUNT(*)                                                                     AS deal_count,
                COALESCE(SUM(CASE WHEN ps.is_closed_won = TRUE THEN 1 ELSE 0 END), 0)      AS won_count,
                COALESCE(SUM(CASE WHEN (ps.is_closed_won OR ps.is_closed_lost) THEN 1 ELSE 0 END), 0)
                                                                                             AS closed_count
            FROM deals d
            LEFT JOIN pipeline_stages ps ON ps.id = d.stage_id AND ps.deleted_at IS NULL
            WHERE d.account_id   = ?
              AND d.tenant_id    = ?
              AND d.company_code = ?
              AND d.deleted_at IS NULL
        SQL;

        $rs = $this->db->Execute($sql, [$accountId, $this->tenantId, $this->companyCode]);

        if ($rs === false || $rs->EOF) {
            return ['total_deal_value' => 0.0, 'win_rate' => 0.0, 'deal_count' => 0, 'won_count' => 0];
        }

        $row         = $rs->fields;
        $closedCount = (int) $row['closed_count'];
        $wonCount    = (int) $row['won_count'];
        // Win rate is won / closed (not won / total) to avoid penalising open deals
        $winRate     = $closedCount > 0 ? round(($wonCount / $closedCount) * 100, 2) : 0.0;

        return [
            'total_deal_value' => (float) $row['total_deal_value'],
            'win_rate'         => $winRate,
            'deal_count'       => (int) $row['deal_count'],
            'won_count'        => $wonCount,
        ];
    }

    // -------------------------------------------------------------------------
    // refreshAggregates — persists computed aggregates back to the accounts row
    // -------------------------------------------------------------------------

    /**
     * Recompute and persist total_deal_value and win_rate on the account row.
     *
     * Called by DealService whenever a deal linked to this account changes.
     *
     * @param  int  $accountId
     * @return void
     */
    public function refreshAggregates(int $accountId): void
    {
        $agg = $this->computeAggregates($accountId);
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->db->Execute(
            'UPDATE accounts SET total_deal_value = ?, win_rate = ?, updated_at = ?
             WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [
                $agg['total_deal_value'],
                $agg['win_rate'],
                $now,
                $accountId,
                $this->tenantId,
                $this->companyCode,
            ]
        );
    }
}
