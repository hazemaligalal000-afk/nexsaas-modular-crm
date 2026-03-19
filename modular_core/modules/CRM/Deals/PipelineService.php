<?php
/**
 * CRM/Deals/PipelineService.php
 *
 * CRUD for pipelines and pipeline stages.
 *
 * Requirements: 10.1, 10.2, 10.3
 */

declare(strict_types=1);

namespace CRM\Deals;

use Core\BaseService;

class PipelineService extends BaseService
{
    private string $tenantId;
    private string $companyCode;

    public function __construct($db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    // -------------------------------------------------------------------------
    // Pipeline CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a new pipeline.
     *
     * @param  string $name
     * @param  int    $createdBy
     * @return int    New pipeline ID
     */
    public function createPipeline(string $name, int $createdBy): int
    {
        $now = $this->now();
        $rs  = $this->db->Execute(
            'INSERT INTO pipelines (tenant_id, company_code, name, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?) RETURNING id',
            [$this->tenantId, $this->companyCode, $name, $createdBy, $now, $now]
        );

        if ($rs === false) {
            throw new \RuntimeException('PipelineService::createPipeline failed: ' . $this->db->ErrorMsg());
        }

        return !$rs->EOF ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();
    }

    /**
     * Find a pipeline by ID, scoped to tenant.
     */
    public function findPipelineById(int $id): ?array
    {
        $rs = $this->db->Execute(
            'SELECT id, name, created_by, created_at, updated_at
             FROM pipelines
             WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$id, $this->tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        $pipeline           = $rs->fields;
        $pipeline['stages'] = $this->listStages($id);
        return $pipeline;
    }

    /**
     * List all pipelines for the current tenant.
     */
    public function listPipelines(): array
    {
        $rs = $this->db->Execute(
            'SELECT id, name, created_at, updated_at
             FROM pipelines
             WHERE tenant_id = ? AND company_code = ? AND deleted_at IS NULL
             ORDER BY created_at ASC',
            [$this->tenantId, $this->companyCode]
        );

        if ($rs === false) {
            throw new \RuntimeException('PipelineService::listPipelines failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    /**
     * Update a pipeline name.
     */
    public function updatePipeline(int $id, string $name): bool
    {
        $rs = $this->db->Execute(
            'UPDATE pipelines SET name = ?, updated_at = ?
             WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$name, $this->now(), $id, $this->tenantId, $this->companyCode]
        );

        if ($rs === false) {
            throw new \RuntimeException('PipelineService::updatePipeline failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    /**
     * Soft-delete a pipeline.
     */
    public function deletePipeline(int $id): bool
    {
        $rs = $this->db->Execute(
            'UPDATE pipelines SET deleted_at = ?
             WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$this->now(), $id, $this->tenantId, $this->companyCode]
        );

        if ($rs === false) {
            throw new \RuntimeException('PipelineService::deletePipeline failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Stage CRUD — Requirement 10.1 (configurable ordered stage list)
    // -------------------------------------------------------------------------

    /**
     * Add a stage to a pipeline.
     *
     * @param  int    $pipelineId
     * @param  string $name
     * @param  int    $position        Display order (1-based)
     * @param  bool   $isClosedWon
     * @param  bool   $isClosedLost
     * @param  int    $createdBy
     * @return int    New stage ID
     */
    public function addStage(
        int    $pipelineId,
        string $name,
        int    $position,
        bool   $isClosedWon  = false,
        bool   $isClosedLost = false,
        int    $createdBy    = 0
    ): int {
        $now = $this->now();
        $rs  = $this->db->Execute(
            'INSERT INTO pipeline_stages
                (tenant_id, company_code, pipeline_id, name, position, is_closed_won, is_closed_lost, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id',
            [
                $this->tenantId, $this->companyCode, $pipelineId,
                $name, $position, $isClosedWon ? 1 : 0, $isClosedLost ? 1 : 0,
                $createdBy, $now, $now,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('PipelineService::addStage failed: ' . $this->db->ErrorMsg());
        }

        return !$rs->EOF ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();
    }

    /**
     * List stages for a pipeline ordered by position.
     */
    public function listStages(int $pipelineId): array
    {
        $rs = $this->db->Execute(
            'SELECT id, name, position, is_closed_won, is_closed_lost
             FROM pipeline_stages
             WHERE pipeline_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL
             ORDER BY position ASC',
            [$pipelineId, $this->tenantId, $this->companyCode]
        );

        if ($rs === false) {
            return [];
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }

    /**
     * Update a stage (name and/or position).
     */
    public function updateStage(int $stageId, array $data): bool
    {
        $allowed = ['name', 'position', 'is_closed_won', 'is_closed_lost'];
        $set     = [];
        $values  = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $set[]    = "{$col} = ?";
                $values[] = $data[$col];
            }
        }

        if (empty($set)) {
            return false;
        }

        $set[]    = 'updated_at = ?';
        $values[] = $this->now();
        $values[] = $stageId;
        $values[] = $this->tenantId;
        $values[] = $this->companyCode;

        $rs = $this->db->Execute(
            sprintf(
                'UPDATE pipeline_stages SET %s WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
                implode(', ', $set)
            ),
            $values
        );

        if ($rs === false) {
            throw new \RuntimeException('PipelineService::updateStage failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    /**
     * Soft-delete a stage.
     */
    public function deleteStage(int $stageId): bool
    {
        $rs = $this->db->Execute(
            'UPDATE pipeline_stages SET deleted_at = ?
             WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$this->now(), $stageId, $this->tenantId, $this->companyCode]
        );

        if ($rs === false) {
            throw new \RuntimeException('PipelineService::deleteStage failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
