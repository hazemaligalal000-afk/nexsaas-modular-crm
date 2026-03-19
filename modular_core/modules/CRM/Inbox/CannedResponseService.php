<?php
/**
 * CRM/Inbox/CannedResponseService.php
 *
 * CRUD for canned responses — pre-written reply templates selectable by
 * agents during a live chat or inbox conversation.
 *
 * Requirements: 12.8
 */

declare(strict_types=1);

namespace CRM\Inbox;

use Core\BaseService;

class CannedResponseService extends BaseService
{
    private string $tenantId;
    private string $companyCode;

    public function __construct(object $db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    /**
     * List all canned responses for the tenant, optionally filtered by a
     * search term matching shortcut or title.
     *
     * @param  string $search  Optional substring to filter by
     * @return array
     */
    public function list(string $search = ''): array
    {
        $where  = ['tenant_id = ?', 'company_code = ?', 'deleted_at IS NULL'];
        $params = [$this->tenantId, $this->companyCode];

        if ($search !== '') {
            $where[]  = '(shortcut ILIKE ? OR title ILIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $rs = $this->db->Execute(
            'SELECT * FROM canned_responses WHERE ' . implode(' AND ', $where) . ' ORDER BY shortcut ASC',
            $params
        );

        if ($rs === false) {
            throw new \RuntimeException('CannedResponseService::list failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    /**
     * Create a new canned response.
     *
     * @param  array $data  Keys: shortcut (string), title (string), body (string)
     * @param  int   $createdBy
     * @return array  Created record
     *
     * @throws \InvalidArgumentException on validation failure
     */
    public function create(array $data, int $createdBy): array
    {
        $this->validate($data);

        $now = $this->now();
        $rs  = $this->db->Execute(
            'INSERT INTO canned_responses (tenant_id, company_code, shortcut, title, body, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING *',
            [
                $this->tenantId,
                $this->companyCode,
                trim($data['shortcut']),
                trim($data['title']),
                trim($data['body']),
                $createdBy ?: null,
                $now,
                $now,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('CannedResponseService::create failed: ' . $this->db->ErrorMsg());
        }

        return $rs->fields;
    }

    /**
     * Update an existing canned response.
     *
     * @param  int   $id
     * @param  array $data  Keys: shortcut, title, body (all optional)
     * @param  int   $updatedBy
     * @return array  Updated record
     *
     * @throws \RuntimeException if not found
     */
    public function update(int $id, array $data, int $updatedBy): array
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            throw new \RuntimeException("Canned response {$id} not found.");
        }

        $merged = array_merge($existing, array_filter([
            'shortcut' => isset($data['shortcut']) ? trim($data['shortcut']) : null,
            'title'    => isset($data['title'])    ? trim($data['title'])    : null,
            'body'     => isset($data['body'])     ? trim($data['body'])     : null,
        ], fn($v) => $v !== null));

        $this->validate($merged);

        $now = $this->now();
        $this->db->Execute(
            'UPDATE canned_responses SET shortcut = ?, title = ?, body = ?, updated_at = ?
             WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [
                $merged['shortcut'],
                $merged['title'],
                $merged['body'],
                $now,
                $id,
                $this->tenantId,
                $this->companyCode,
            ]
        );

        return $this->findById($id) ?? $merged;
    }

    /**
     * Soft-delete a canned response.
     *
     * @param  int $id
     * @param  int $deletedBy
     * @return bool
     */
    public function delete(int $id, int $deletedBy): bool
    {
        $now = $this->now();
        $this->db->Execute(
            'UPDATE canned_responses SET deleted_at = ?, updated_at = ?
             WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$now, $now, $id, $this->tenantId, $this->companyCode]
        );

        return $this->db->Affected_Rows() > 0;
    }

    /**
     * Find a single canned response by ID scoped to this tenant.
     */
    public function findById(int $id): ?array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM canned_responses WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$id, $this->tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function validate(array $data): void
    {
        if (empty($data['shortcut'])) {
            throw new \InvalidArgumentException('shortcut is required.');
        }
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('title is required.');
        }
        if (empty($data['body'])) {
            throw new \InvalidArgumentException('body is required.');
        }
        if (strlen($data['shortcut']) > 50) {
            throw new \InvalidArgumentException('shortcut must be 50 characters or fewer.');
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
