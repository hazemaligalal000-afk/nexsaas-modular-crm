<?php
/**
 * CTI/DispositionService.php
 *
 * CRUD for disposition_codes table.
 */

declare(strict_types=1);

namespace CTI;

use Core\BaseService;

class DispositionService extends BaseService
{
    private string $tenantId;
    private string $companyCode;

    public function __construct($db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    public function list(): array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM disposition_codes WHERE tenant_id = ? AND is_active = 1 ORDER BY category, code',
            [$this->tenantId]
        );

        if ($rs === false) {
            throw new \RuntimeException('DispositionService::list failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    public function create(array $data): int
    {
        $rs = $this->db->Execute(
            'INSERT INTO disposition_codes (tenant_id, company_code, code, label_en, label_ar, category, is_active)
             VALUES (?,?,?,?,?,?,?) RETURNING id',
            [
                $this->tenantId,
                $this->companyCode,
                strtoupper(trim($data['code'])),
                $data['label_en']  ?? null,
                $data['label_ar']  ?? null,
                $data['category']  ?? 'general',
                1,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('DispositionService::create failed: ' . $this->db->ErrorMsg());
        }

        return (!$rs->EOF) ? (int)$rs->fields['id'] : (int)$this->db->Insert_ID();
    }

    public function update(int $id, array $data): bool
    {
        $set    = [];
        $params = [];

        foreach (['label_en', 'label_ar', 'category', 'is_active'] as $f) {
            if (array_key_exists($f, $data)) {
                $set[]    = "{$f} = ?";
                $params[] = $data[$f];
            }
        }

        if (empty($set)) {
            return false;
        }

        $params[] = $id;
        $params[] = $this->tenantId;

        $rs = $this->db->Execute(
            'UPDATE disposition_codes SET ' . implode(', ', $set) . ' WHERE id = ? AND tenant_id = ?',
            $params
        );

        return $rs !== false && $this->db->Affected_Rows() > 0;
    }

    public function delete(int $id): bool
    {
        $rs = $this->db->Execute(
            'UPDATE disposition_codes SET is_active = 0 WHERE id = ? AND tenant_id = ?',
            [$id, $this->tenantId]
        );

        return $rs !== false && $this->db->Affected_Rows() > 0;
    }
}
