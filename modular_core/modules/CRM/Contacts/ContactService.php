<?php
/**
 * CRM/Contacts/ContactService.php
 *
 * Business logic for contact management.
 *
 * Requirements: 6.2, 6.3, 6.5, 6.7
 */

declare(strict_types=1);

namespace CRM\Contacts;

use Core\BaseService;

class ContactService extends BaseService
{
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
    // create — Requirement 6.2
    // -------------------------------------------------------------------------

    /**
     * Create a new contact.
     *
     * Validates that at least one of email or phone is present.
     * Checks for duplicate email within the tenant before inserting.
     *
     * @param  array $data      Contact fields (first_name, last_name, email, phone, …)
     * @param  int   $createdBy ID of the authenticated user performing the action
     * @return int              New contact ID
     *
     * @throws \InvalidArgumentException if both email and phone are absent
     * @throws \RuntimeException         if a duplicate email exists for this tenant
     */
    public function create(array $data, int $createdBy): int
    {
        $email = isset($data['email']) && $data['email'] !== '' ? trim($data['email']) : null;
        $phone = isset($data['phone']) && $data['phone'] !== '' ? trim($data['phone']) : null;

        // Requirement 6.2: email OR phone must be present
        if ($email === null && $phone === null) {
            throw new \InvalidArgumentException(
                'Contact requires at least one of email or phone. (Req 6.2)'
            );
        }

        // Requirement 6.3: duplicate email check within tenant
        if ($email !== null) {
            $this->assertNoDuplicateEmail($email);
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $customFields = isset($data['custom_fields']) && is_array($data['custom_fields'])
            ? json_encode($data['custom_fields'])
            : '{}';

        $sql = <<<SQL
            INSERT INTO contacts
                (tenant_id, company_code, first_name, last_name, email, phone,
                 company_name, job_title, custom_fields, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        SQL;

        $rs = $this->db->Execute($sql, [
            $this->tenantId,
            $this->companyCode,
            $data['first_name']   ?? null,
            $data['last_name']    ?? null,
            $email,
            $phone,
            $data['company_name'] ?? null,
            $data['job_title']    ?? null,
            $customFields,
            $createdBy,
            $now,
            $now,
        ]);

        if ($rs === false) {
            throw new \RuntimeException('ContactService::create failed: ' . $this->db->ErrorMsg());
        }

        if (!$rs->EOF) {
            return (int) $rs->fields['id'];
        }

        // Fallback for drivers that don't support RETURNING
        return (int) $this->db->Insert_ID();
    }

    // -------------------------------------------------------------------------
    // merge — Requirement 6.7
    // -------------------------------------------------------------------------

    /**
     * Merge a duplicate contact into a survivor contact.
     *
     * All activities, deals, and notes linked to $duplicateId are re-pointed
     * to $survivorId. The duplicate is then soft-deleted. Everything runs in
     * a single transaction; any failure rolls back all changes.
     *
     * @param  int $survivorId  ID of the contact to keep
     * @param  int $duplicateId ID of the contact to remove
     *
     * @throws \InvalidArgumentException if survivor or duplicate not found
     * @throws \RuntimeException         on DB error
     */
    public function merge(int $survivorId, int $duplicateId): void
    {
        if ($survivorId === $duplicateId) {
            throw new \InvalidArgumentException('survivorId and duplicateId must be different.');
        }

        $this->transaction(function () use ($survivorId, $duplicateId): void {
            // Verify both contacts exist and belong to this tenant
            $survivor  = $this->findById($survivorId);
            $duplicate = $this->findById($duplicateId);

            if ($survivor === null) {
                throw new \InvalidArgumentException("Survivor contact $survivorId not found.");
            }
            if ($duplicate === null) {
                throw new \InvalidArgumentException("Duplicate contact $duplicateId not found.");
            }

            $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

            // Re-link activities
            $this->db->Execute(
                'UPDATE activities SET contact_id = ? WHERE contact_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                [$survivorId, $duplicateId, $this->tenantId, $this->companyCode]
            );

            // Re-link deals
            $this->db->Execute(
                'UPDATE deals SET contact_id = ? WHERE contact_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                [$survivorId, $duplicateId, $this->tenantId, $this->companyCode]
            );

            // Re-link notes
            $this->db->Execute(
                'UPDATE notes SET contact_id = ? WHERE contact_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                [$survivorId, $duplicateId, $this->tenantId, $this->companyCode]
            );

            // Soft-delete the duplicate
            $result = $this->db->Execute(
                'UPDATE contacts SET deleted_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                [$now, $duplicateId, $this->tenantId, $this->companyCode]
            );

            if ($result === false) {
                throw new \RuntimeException('ContactService::merge soft-delete failed: ' . $this->db->ErrorMsg());
            }
        });
    }

    // -------------------------------------------------------------------------
    // search — Requirement 6.5
    // -------------------------------------------------------------------------

    /**
     * Full-text search contacts using the tsvector index.
     *
     * Scoped to the current tenant + company. Designed to return within 500ms
     * for 1M records via the GIN index on search_vector.
     *
     * @param  string $query  Search terms
     * @return array          Matching contact rows
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $sql = <<<SQL
            SELECT id, first_name, last_name, email, phone, company_name, job_title,
                   custom_fields, created_at, updated_at
            FROM contacts
            WHERE tenant_id = ?
              AND company_code = ?
              AND deleted_at IS NULL
              AND search_vector @@ plainto_tsquery('english', ?)
            ORDER BY ts_rank(search_vector, plainto_tsquery('english', ?)) DESC
            LIMIT 100
        SQL;

        $rs = $this->db->Execute($sql, [
            $this->tenantId,
            $this->companyCode,
            $query,
            $query,
        ]);

        if ($rs === false) {
            throw new \RuntimeException('ContactService::search failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    /**
     * Find a single active contact by primary key, scoped to tenant.
     *
     * @param  int        $id
     * @return array|null Row as associative array, or null if not found / deleted
     */
    public function findById(int $id): ?array
    {
        $sql = <<<SQL
            SELECT id, first_name, last_name, email, phone, company_name, job_title,
                   custom_fields, created_by, created_at, updated_at
            FROM contacts
            WHERE id = ?
              AND tenant_id = ?
              AND company_code = ?
              AND deleted_at IS NULL
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
     * Update an existing contact.
     *
     * @param  int   $id
     * @param  array $data  Fields to update
     * @return bool         true if a row was updated
     *
     * @throws \RuntimeException on DB error
     */
    public function update(int $id, array $data): bool
    {
        // Prevent overriding isolation fields
        unset($data['id'], $data['tenant_id'], $data['company_code'], $data['created_at']);

        if (empty($data)) {
            return false;
        }

        // If email is being updated, check for duplicates (excluding this record)
        if (isset($data['email']) && $data['email'] !== '') {
            $email = trim($data['email']);
            $this->assertNoDuplicateEmail($email, $id);
            $data['email'] = $email;
        }

        $data['updated_at'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
            $data['custom_fields'] = json_encode($data['custom_fields']);
        }

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
            'UPDATE contacts SET %s WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            implode(', ', $setClauses)
        );

        $result = $this->db->Execute($sql, $values);

        if ($result === false) {
            throw new \RuntimeException('ContactService::update failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // delete (soft)
    // -------------------------------------------------------------------------

    /**
     * Soft-delete a contact by setting deleted_at.
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
            'UPDATE contacts SET deleted_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$now, $id, $this->tenantId, $this->companyCode]
        );

        if ($result === false) {
            throw new \RuntimeException('ContactService::delete failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // getTimeline — Requirement 6.4
    // -------------------------------------------------------------------------

    /**
     * Fetch the activity timeline for a contact.
     *
     * Returns activities, deals, and notes linked to the contact,
     * ordered by created_at descending.
     *
     * @param  int   $contactId
     * @return array Timeline entries grouped by type
     */
    public function getTimeline(int $contactId): array
    {
        $timeline = [];

        // Activities
        $rs = $this->db->Execute(
            'SELECT id, \'activity\' AS type, activity_type AS subtype, subject, created_at
             FROM activities
             WHERE contact_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL
             ORDER BY created_at DESC',
            [$contactId, $this->tenantId, $this->companyCode]
        );
        if ($rs !== false) {
            while (!$rs->EOF) {
                $timeline[] = $rs->fields;
                $rs->MoveNext();
            }
        }

        // Deals
        $rs = $this->db->Execute(
            'SELECT id, \'deal\' AS type, title AS subtype, value, created_at
             FROM deals
             WHERE contact_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL
             ORDER BY created_at DESC',
            [$contactId, $this->tenantId, $this->companyCode]
        );
        if ($rs !== false) {
            while (!$rs->EOF) {
                $timeline[] = $rs->fields;
                $rs->MoveNext();
            }
        }

        // Notes
        $rs = $this->db->Execute(
            'SELECT id, \'note\' AS type, body AS subtype, created_at
             FROM notes
             WHERE contact_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL
             ORDER BY created_at DESC',
            [$contactId, $this->tenantId, $this->companyCode]
        );
        if ($rs !== false) {
            while (!$rs->EOF) {
                $timeline[] = $rs->fields;
                $rs->MoveNext();
            }
        }

        // Sort all entries by created_at descending
        usort($timeline, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $timeline;
    }

    // -------------------------------------------------------------------------
    // list (paginated)
    // -------------------------------------------------------------------------

    /**
     * List contacts for the current tenant, with optional pagination.
     *
     * @param  int $limit
     * @param  int $offset
     * @return array
     */
    public function list(int $limit = 50, int $offset = 0): array
    {
        $sql = <<<SQL
            SELECT id, first_name, last_name, email, phone, company_name, job_title,
                   custom_fields, created_at, updated_at
            FROM contacts
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
            throw new \RuntimeException('ContactService::list failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Assert that no active contact with the given email exists for this tenant.
     *
     * @param  string   $email
     * @param  int|null $excludeId  Exclude this contact ID from the check (for updates)
     *
     * @throws \RuntimeException if a duplicate is found
     */
    private function assertNoDuplicateEmail(string $email, ?int $excludeId = null): void
    {
        $sql    = 'SELECT id FROM contacts WHERE tenant_id = ? AND company_code = ? AND email = ? AND deleted_at IS NULL';
        $params = [$this->tenantId, $this->companyCode, $email];

        if ($excludeId !== null) {
            $sql    .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $rs = $this->db->Execute($sql, $params);

        if ($rs === false) {
            throw new \RuntimeException('ContactService: duplicate email check failed: ' . $this->db->ErrorMsg());
        }

        if (!$rs->EOF) {
            throw new \RuntimeException(
                "A contact with email '{$email}' already exists for this tenant. (Req 6.3)"
            );
        }
    }
}
