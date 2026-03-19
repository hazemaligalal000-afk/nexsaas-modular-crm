<?php
/**
 * Core/BaseModel.php
 *
 * Abstract base model enforcing multi-tenant isolation on every query via ADOdb.
 * Every query is automatically scoped to tenant_id + company_code.
 * Soft-delete sets deleted_at; default scope excludes deleted rows.
 *
 * Requirements: 1.3, 1.4, 1.5, 1.6
 */

declare(strict_types=1);

namespace Core;

/**
 * @property \ADOConnection $db  ADOdb connection instance
 */
abstract class BaseModel
{
    /** @var \ADOConnection */
    protected $db;

    /** @var string  UUID of the current tenant — MUST be set before any query */
    protected string $tenantId;

    /** @var string  Two-digit company code, e.g. '01'–'06' */
    protected string $companyCode;

    /** @var string  The primary database table this model operates on */
    protected string $table = '';

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    /**
     * @param \ADOConnection $db          ADOdb connection
     * @param string         $tenantId    Current tenant UUID (non-empty)
     * @param string         $companyCode Current company code (non-empty)
     *
     * @throws \InvalidArgumentException if tenantId or companyCode is empty
     */
    public function __construct($db, string $tenantId, string $companyCode)
    {
        if (empty($tenantId)) {
            throw new \InvalidArgumentException(
                'BaseModel requires a non-empty tenantId. ' .
                'Queries without tenant_id are rejected. (Req 1.4)'
            );
        }

        if (empty($companyCode)) {
            throw new \InvalidArgumentException(
                'BaseModel requires a non-empty companyCode.'
            );
        }

        $this->db          = $db;
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    // -------------------------------------------------------------------------
    // Core query helpers
    // -------------------------------------------------------------------------

    /**
     * Execute a tenant-scoped SELECT query.
     *
     * Automatically prepends:
     *   WHERE tenant_id = ? AND company_code = ? AND deleted_at IS NULL
     * (or AND-extends an existing WHERE clause).
     *
     * The tenant clause is injected at the *start* of the WHERE block so that
     * any OR conditions in caller-supplied SQL cannot bypass isolation.
     *
     * Requirements 1.3, 1.4, 1.6
     *
     * @param  string  $sql    Raw SQL — may already contain a WHERE clause.
     *                         Must NOT contain a trailing semicolon.
     * @param  array   $params Positional parameters for the caller-supplied
     *                         predicates (tenant_id + company_code are
     *                         prepended automatically).
     * @return array           Rows as associative arrays.
     *
     * @throws \RuntimeException         on DB error.
     * @throws \InvalidArgumentException if tenantId is empty at call time.
     */
    public function scopeQuery(string $sql, array $params = []): array
    {
        $this->assertTenantId();

        [$scopedSql, $scopedParams] = $this->buildScopedQuery($sql, $params);

        $rs = $this->db->Execute($scopedSql, $scopedParams);

        if ($rs === false) {
            throw new \RuntimeException(
                'BaseModel::scopeQuery failed: ' . $this->db->ErrorMsg()
            );
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    /**
     * Soft-delete a record by setting deleted_at to the current UTC timestamp.
     *
     * The UPDATE is scoped to tenant_id + company_code so a tenant can never
     * delete another tenant's records.
     *
     * Requirements 1.5
     *
     * @param  int  $id  Primary key of the record to soft-delete.
     * @return bool      true on success, false if no row was affected.
     *
     * @throws \InvalidArgumentException if tenantId is empty.
     * @throws \RuntimeException         on DB error.
     */
    public function softDelete(int $id): bool
    {
        $this->assertTenantId();
        $this->assertTable();

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $sql = sprintf(
            "UPDATE %s SET deleted_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL",
            $this->table
        );

        $result = $this->db->Execute($sql, [$now, $id, $this->tenantId, $this->companyCode]);

        if ($result === false) {
            throw new \RuntimeException(
                'BaseModel::softDelete failed: ' . $this->db->ErrorMsg()
            );
        }

        return $this->db->Affected_Rows() > 0;
    }

    /**
     * Find a single active (non-deleted) record by primary key, scoped to tenant.
     *
     * @param  int        $id
     * @return array|null Row as associative array, or null if not found.
     */
    public function findById(int $id): ?array
    {
        $this->assertTenantId();
        $this->assertTable();

        $sql  = "SELECT * FROM {$this->table} WHERE id = ?";
        $rows = $this->scopeQuery($sql, [$id]);

        return $rows[0] ?? null;
    }

    /**
     * Insert a new row, automatically injecting tenant_id and company_code.
     *
     * @param  array $data  Column → value map. tenant_id and company_code are
     *                      always overwritten with the model's own values.
     * @return int          The new record's auto-increment / BIGSERIAL id.
     *
     * @throws \RuntimeException on DB error.
     */
    public function insert(array $data): int
    {
        $this->assertTenantId();
        $this->assertTable();

        // Enforce tenant isolation — never trust caller-supplied values
        $data['tenant_id']    = $this->tenantId;
        $data['company_code'] = $this->companyCode;

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        if (!isset($data['created_at'])) {
            $data['created_at'] = $now;
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = $now;
        }

        $columns      = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values       = array_values($data);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $result = $this->db->Execute($sql, $values);

        if ($result === false) {
            throw new \RuntimeException(
                'BaseModel::insert failed: ' . $this->db->ErrorMsg()
            );
        }

        return (int) $this->db->Insert_ID();
    }

    /**
     * Update an existing row, scoped to the current tenant + company.
     *
     * @param  int   $id
     * @param  array $data  Column → value map to update.
     * @return bool         true if at least one row was updated.
     *
     * @throws \RuntimeException on DB error.
     */
    public function update(int $id, array $data): bool
    {
        $this->assertTenantId();
        $this->assertTable();

        // Prevent callers from overriding tenant isolation fields
        unset($data['tenant_id'], $data['company_code'], $data['id']);
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
            "UPDATE %s SET %s WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL",
            $this->table,
            implode(', ', $setClauses)
        );

        $result = $this->db->Execute($sql, $values);

        if ($result === false) {
            throw new \RuntimeException(
                'BaseModel::update failed: ' . $this->db->ErrorMsg()
            );
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build a tenant-scoped SQL string and merged parameter list.
     *
     * Prepends `tenant_id = ? AND company_code = ? AND deleted_at IS NULL`
     * to the WHERE clause, creating one if none exists.
     *
     * Injecting at the *start* of WHERE ensures OR conditions in caller SQL
     * cannot bypass tenant isolation.
     *
     * @param  string $sql
     * @param  array  $params
     * @return array{0: string, 1: array}  [scopedSql, scopedParams]
     */
    protected function buildScopedQuery(string $sql, array $params): array
    {
        $tenantClause = 'tenant_id = ? AND company_code = ? AND deleted_at IS NULL';

        if (stripos($sql, 'WHERE') !== false) {
            // Inject at the start of the existing WHERE clause
            $sql = preg_replace(
                '/\bWHERE\b/i',
                "WHERE {$tenantClause} AND",
                $sql,
                1
            );
        } else {
            $sql .= " WHERE {$tenantClause}";
        }

        // Prepend tenantId + companyCode to params (match the two injected ?s)
        $scopedParams = array_merge([$this->tenantId, $this->companyCode], $params);

        return [$sql, $scopedParams];
    }

    /**
     * Guard: throw if tenantId is empty.
     *
     * Requirement 1.4 — queries without tenant_id must be rejected.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertTenantId(): void
    {
        if (empty($this->tenantId)) {
            throw new \InvalidArgumentException(
                'Query rejected: tenant_id is required but was not set. (Req 1.4)'
            );
        }
    }

    /**
     * Guard: throw if $this->table is empty.
     *
     * @throws \LogicException
     */
    protected function assertTable(): void
    {
        if (empty($this->table)) {
            throw new \LogicException(
                get_class($this) . ' must define a non-empty $table property.'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCompanyCode(): string
    {
        return $this->companyCode;
    }

    /**
     * Get database connection (for internal use)
     * 
     * @return \ADOConnection
     */
    public function getDb()
    {
        return $this->db;
    }
}
