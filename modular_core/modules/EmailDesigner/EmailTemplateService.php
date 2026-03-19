<?php
/**
 * EmailDesigner/EmailTemplateService.php
 *
 * CRUD for email templates with MJML compilation.
 */

declare(strict_types=1);

namespace EmailDesigner;

use Core\BaseService;

class EmailTemplateService extends BaseService
{
    private string $tenantId;
    private string $companyCode;

    public function __construct($db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    /**
     * Create a new email template.
     */
    public function create(array $data, int $createdBy): int
    {
        $this->validate($data);

        $now = $this->now();
        $rs  = $this->db->Execute(
            'INSERT INTO email_templates (
                tenant_id, company_code, name, category, subject_en, subject_ar,
                preheader_en, preheader_ar, mjml_source, html_compiled,
                thumbnail_url, is_active, created_by, created_at, updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id',
            [
                $this->tenantId,
                $this->companyCode,
                $data['name'],
                $data['category'] ?? 'general',
                $data['subject_en'],
                $data['subject_ar'] ?? null,
                $data['preheader_en'] ?? null,
                $data['preheader_ar'] ?? null,
                $data['mjml_source'],
                $this->compileMJML($data['mjml_source']),
                $data['thumbnail_url'] ?? null,
                $data['is_active'] ?? true,
                $createdBy,
                $now,
                $now,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('EmailTemplateService::create failed: ' . $this->db->ErrorMsg());
        }

        return (!$rs->EOF) ? (int)$rs->fields['id'] : (int)$this->db->Insert_ID();
    }

    /**
     * Update an existing template.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['name', 'category', 'subject_en', 'subject_ar', 'preheader_en', 'preheader_ar', 'mjml_source', 'is_active'];
        $set     = [];
        $params  = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $set[]    = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($set)) {
            return false;
        }

        // Recompile HTML if MJML changed
        if (array_key_exists('mjml_source', $data)) {
            $set[]    = 'html_compiled = ?';
            $params[] = $this->compileMJML($data['mjml_source']);
        }

        $set[]    = 'updated_at = ?';
        $params[] = $this->now();
        $params[] = $id;
        $params[] = $this->tenantId;
        $params[] = $this->companyCode;

        $rs = $this->db->Execute(
            'UPDATE email_templates SET ' . implode(', ', $set) . ' WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            $params
        );

        return $rs !== false && $this->db->Affected_Rows() > 0;
    }

    /**
     * Get template by ID.
     */
    public function findById(int $id): ?array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM email_templates WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$id, $this->tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    /**
     * List templates with optional filters.
     */
    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['tenant_id = ?', 'company_code = ?', 'deleted_at IS NULL'];
        $params = [$this->tenantId, $this->companyCode];

        if (!empty($filters['category'])) {
            $where[]  = 'category = ?';
            $params[] = $filters['category'];
        }

        if (isset($filters['is_active'])) {
            $where[]  = 'is_active = ?';
            $params[] = (bool)$filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $where[]  = '(name ILIKE ? OR subject_en ILIKE ?)';
            $search   = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $sql = 'SELECT * FROM email_templates WHERE ' . implode(' AND ', $where)
             . ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $rs = $this->db->Execute($sql, $params);
        if ($rs === false) {
            throw new \RuntimeException('EmailTemplateService::list failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    /**
     * Soft-delete a template.
     */
    public function delete(int $id): bool
    {
        $now = $this->now();
        $rs  = $this->db->Execute(
            'UPDATE email_templates SET deleted_at = ?, updated_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$now, $now, $id, $this->tenantId, $this->companyCode]
        );

        return $rs !== false && $this->db->Affected_Rows() > 0;
    }

    /**
     * Duplicate a template.
     */
    public function duplicate(int $id, int $createdBy): int
    {
        $original = $this->findById($id);
        if ($original === null) {
            throw new \RuntimeException("Template {$id} not found");
        }

        $data = [
            'name'         => $original['name'] . ' (Copy)',
            'category'     => $original['category'],
            'subject_en'   => $original['subject_en'],
            'subject_ar'   => $original['subject_ar'],
            'preheader_en' => $original['preheader_en'],
            'preheader_ar' => $original['preheader_ar'],
            'mjml_source'  => $original['mjml_source'],
            'is_active'    => false,
        ];

        return $this->create($data, $createdBy);
    }

    /**
     * Compile MJML to HTML.
     * 
     * In production, call MJML microservice or use MJML PHP library.
     * For now, return simplified HTML.
     */
    private function compileMJML(string $mjml): string
    {
        // Simplified - in production call MJML API:
        // POST https://api.mjml.io/v1/render
        // with { "mjml": $mjml }
        
        // For now, return basic HTML wrapper
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email</title>
</head>
<body>
    {$mjml}
</body>
</html>
HTML;
    }

    /**
     * Validate template data.
     */
    private function validate(array $data): void
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Template name is required');
        }

        if (empty($data['subject_en'])) {
            throw new \InvalidArgumentException('English subject is required');
        }

        if (empty($data['mjml_source'])) {
            throw new \InvalidArgumentException('MJML source is required');
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
