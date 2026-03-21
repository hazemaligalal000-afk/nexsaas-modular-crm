<?php
/**
 * Workflows/DeploymentService.php
 * 
 * CORE → ADVANCED: Automated Build & Deployment Queue (Batch DEPLOY-B)
 */

declare(strict_types=1);

namespace Modules\Workflows;

use Core\BaseService;

class DeploymentService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Queue a new build/deployment from source code repo
     * Used by: GitHubIntegrationService dispatch
     */
    public function queueDeployment(string $tenantId, string $repoUrl, string $branch = 'main'): int
    {
        $data = [
            'tenant_id' => $tenantId,
            'repo_url' => $repoUrl,
            'branch' => $branch,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->db->AutoExecute('deployment_queue', $data, 'INSERT');
        $id = (int)$this->db->Insert_ID();

        // 1. Logic: Trigger Background Runner (Runner-A)
        // Rule: Identify the appropriate build environment (Docker/K8s)
        
        return $id;
    }

    /**
     * Get deployment logs for the tenant dashboard
     */
    public function getDeploymentHistory(string $tenantId): array
    {
        $sql = "SELECT id, repo_url, status, ended_at, build_log 
                FROM deployment_queue WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 10";
        
        return $this->db->GetAll($sql, [$tenantId]);
    }
}
