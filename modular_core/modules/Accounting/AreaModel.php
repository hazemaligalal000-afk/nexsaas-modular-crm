<?php
namespace Modules\Accounting;

use Core\BaseModel;

class AreaModel extends BaseModel
{
    protected string $table = 'branches';

    /**
     * Get branch description by code
     */
    public function getBranchName(string $tenantId, string $companyCode, string $branchCode): ?string
    {
        $sql = "SELECT name_en, name_ar FROM {$this->table} 
                WHERE tenant_id = ? AND company_code = ? AND branch_code = ? AND deleted_at IS NULL";
        
        $result = $this->db->Execute($sql, [$tenantId, $companyCode, $branchCode]);
        if ($result && !$result->EOF) {
            return $result->fields['name_en'] ?? $result->fields['name_ar'];
        }
        return null;
    }

    /**
     * List all branches for a company
     */
    public function listBranches(string $tenantId, string $companyCode): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = ? AND company_code = ? AND deleted_at IS NULL
                ORDER BY branch_code";
        
        return $this->scopeQuery($sql, [$tenantId, $companyCode]);
    }
}
