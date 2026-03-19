<?php
/**
 * CRM/Leads/LeadService.php
 * Requirements: 7.2, 7.3, 7.4, 7.5, 7.6, 7.7
 */
declare(strict_types=1);

namespace CRM\Leads;

use Core\BaseService;

class LeadService extends BaseService
{
    private object $rabbitMQ;
    private string $tenantId;
    private string $companyCode;

    public function __construct($db, object $rabbitMQ, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->rabbitMQ    = $rabbitMQ;
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    /**
     * Valid lead source values per Requirement 7.7.
     */
    private const VALID_SOURCES = ['web_form', 'api', 'import', 'manual'];

    public function capture(array $data, int $createdBy): int
    {
        // Requirement 7.3: validate required fields
        $fullName = $data['full_name']
            ?? trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        if (trim($fullName) === '') {
            throw new \InvalidArgumentException('full_name is required.');
        }

        // Requirement 7.7: validate and default source attribution
        $source = $data['source'] ?? 'manual';
        if (!in_array($source, self::VALID_SOURCES, true)) {
            $source = 'manual';
        }

        $email = isset($data['email']) && $data['email'] !== '' ? trim($data['email']) : null;
        $phone = isset($data['phone']) && $data['phone'] !== '' ? trim($data['phone']) : null;

        // Requirement 7.4: duplicate detection by email/phone
        if ($email !== null || $phone !== null) {
            $duplicate = $this->findDuplicate($email ?? '', $phone ?? '');
            if ($duplicate !== null) {
                throw new \RuntimeException(
                    'A lead with this email or phone already exists. (duplicate_id=' . $duplicate['id'] . ')',
                    409
                );
            }
        }

        $now = $this->now();

        $sql = <<<SQL
            INSERT INTO leads
                (tenant_id, company_code, full_name, email, phone,
                 source, status, owner_id, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        SQL;

        $rs = $this->db->Execute($sql, [
            $this->tenantId, $this->companyCode,
            $fullName,
            $email,
            $phone,
            $source,
            $data['status'] ?? 'new',
            $data['owner_id'] ?? ($createdBy ?: null),
            $createdBy ?: null, $now, $now,
        ]);

        if ($rs === false) {
            throw new \RuntimeException('LeadService::capture failed: ' . $this->db->ErrorMsg());
        }

        $leadId = (!$rs->EOF) ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();

        // Requirement 7.3: enqueue lead.captured event (within 2s — synchronous publish)
        $this->publishEvent('lead.captured', [
            'lead_id'      => $leadId,
            'tenant_id'    => $this->tenantId,
            'company_code' => $this->companyCode,
            'email'        => $email,
            'source'       => $source,
            'captured_at'  => $now,
        ]);

        return $leadId;
    }

    public function convert(int $leadId): array
    {
        $lead = $this->findById($leadId);
        if ($lead === null) {
            throw new \InvalidArgumentException("Lead {$leadId} not found.");
        }
        if ($lead['converted_at'] !== null) {
            throw new \InvalidArgumentException("Lead {$leadId} has already been converted.");
        }

        return $this->transaction(function () use ($lead, $leadId): array {
            $now = $this->now();

            $contactRs = $this->db->Execute(
                "INSERT INTO contacts (tenant_id, company_code, full_name, email, phone, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id",
                [$this->tenantId, $this->companyCode, $lead['full_name'], $lead['email'], $lead['phone'], $lead['created_by'], $now, $now]
            );
            if ($contactRs === false) throw new \RuntimeException('Contact insert failed: ' . $this->db->ErrorMsg());
            $contactId = (!$contactRs->EOF) ? (int) $contactRs->fields['id'] : (int) $this->db->Insert_ID();

            $accountRs = $this->db->Execute(
                "INSERT INTO accounts (tenant_id, company_code, company_name, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?) RETURNING id",
                [$this->tenantId, $this->companyCode, $lead['full_name'], $lead['created_by'], $now, $now]
            );
            if ($accountRs === false) throw new \RuntimeException('Account insert failed: ' . $this->db->ErrorMsg());
            $accountId = (!$accountRs->EOF) ? (int) $accountRs->fields['id'] : (int) $this->db->Insert_ID();

            $dealRs = $this->db->Execute(
                "INSERT INTO deals (tenant_id, company_code, title, contact_id, account_id, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id",
                [$this->tenantId, $this->companyCode, 'Deal from lead: ' . $lead['full_name'], $contactId, $accountId, $lead['created_by'], $now, $now]
            );
            if ($dealRs === false) throw new \RuntimeException('Deal insert failed: ' . $this->db->ErrorMsg());
            $dealId = (!$dealRs->EOF) ? (int) $dealRs->fields['id'] : (int) $this->db->Insert_ID();

            $this->db->Execute(
                'UPDATE leads SET converted_at = ?, converted_contact_id = ?, converted_account_id = ?, converted_deal_id = ?, updated_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                [$now, $contactId, $accountId, $dealId, $now, $leadId, $this->tenantId, $this->companyCode]
            );

            return ['contact_id' => $contactId, 'account_id' => $accountId, 'deal_id' => $dealId];
        });
    }

    public function findById(int $id): ?array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM leads WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$id, $this->tenantId, $this->companyCode]
        );
        if ($rs === false || $rs->EOF) return null;
        return $rs->fields;
    }

    /**
     * Find an existing active lead by email or phone within the current tenant.
     *
     * Used for duplicate detection before insert (Requirement 7.4).
     *
     * @param  string      $email  Normalised email (may be empty string)
     * @param  string      $phone  Normalised phone (may be empty string)
     * @return array|null  First matching lead row, or null if no duplicate found
     */
    public function findDuplicate(string $email, string $phone): ?array
    {
        $email = trim(strtolower($email));
        $phone = trim($phone);

        if ($email === '' && $phone === '') {
            return null;
        }

        $conditions = [];
        $params     = [];

        if ($email !== '') {
            $conditions[] = 'LOWER(email) = ?';
            $params[]     = $email;
        }

        if ($phone !== '') {
            $conditions[] = 'phone = ?';
            $params[]     = $phone;
        }

        $where = implode(' OR ', $conditions);

        $sql = <<<SQL
            SELECT id, full_name, email, phone, source, status, created_at
            FROM leads
            WHERE tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
              AND ({$where})
            LIMIT 1
        SQL;

        $rs = $this->db->Execute($sql, array_merge(
            [$this->tenantId, $this->companyCode],
            $params
        ));

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    public function update(int $id, array $data): bool
    {
        unset($data['id'], $data['tenant_id'], $data['company_code'], $data['created_at'], $data['converted_at']);
        if (empty($data)) return false;
        $data['updated_at'] = $this->now();
        if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
            $data['custom_fields'] = json_encode($data['custom_fields']);
        }
        $setClauses = []; $values = [];
        foreach ($data as $col => $val) { $setClauses[] = "{$col} = ?"; $values[] = $val; }
        $values[] = $id; $values[] = $this->tenantId; $values[] = $this->companyCode;
        $result = $this->db->Execute(
            sprintf('UPDATE leads SET %s WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL', implode(', ', $setClauses)),
            $values
        );
        if ($result === false) throw new \RuntimeException('LeadService::update failed: ' . $this->db->ErrorMsg());
        $affected = $this->db->Affected_Rows() > 0;

        // Requirement 8.1: publish lead.updated so LeadScoringService enqueues a score_request
        if ($affected) {
            $this->publishEvent('lead.updated', [
                'lead_id'      => $id,
                'tenant_id'    => $this->tenantId,
                'company_code' => $this->companyCode,
                'updated_at'   => $data['updated_at'],
            ]);
        }

        return $affected;
    }

    public function delete(int $id): bool
    {
        $now = $this->now();
        $result = $this->db->Execute(
            'UPDATE leads SET deleted_at = ?, updated_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$now, $now, $id, $this->tenantId, $this->companyCode]
        );
        if ($result === false) throw new \RuntimeException('LeadService::delete failed: ' . $this->db->ErrorMsg());
        return $this->db->Affected_Rows() > 0;
    }

    public function list(int $limit = 50, int $offset = 0): array
    {
        $rs = $this->db->Execute(
            'SELECT id, full_name, email, phone, source, status, lead_score, owner_id, converted_at, created_at, updated_at FROM leads WHERE tenant_id = ? AND company_code = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$this->tenantId, $this->companyCode, $limit, $offset]
        );
        if ($rs === false) throw new \RuntimeException('LeadService::list failed: ' . $this->db->ErrorMsg());
        $rows = [];
        while (!$rs->EOF) { $rows[] = $rs->fields; $rs->MoveNext(); }
        return $rows;
    }

    public function dispatchImportJob(array $payload): void
    {
        $this->publishEvent('crm.lead_import', $payload);
    }

    private function publishEvent(string $event, array $payload): void
    {
        $this->rabbitMQ->publish('crm.events', $event, $payload);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
