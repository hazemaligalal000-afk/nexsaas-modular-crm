<?php
/**
 * Workflows/WorkflowExecutionService.php
 * 
 * CORE → ADVANCED: Background Worker & Step Retry Engine
 */

declare(strict_types=1);

namespace Modules\Workflows;

use Core\BaseService;

class WorkflowExecutionService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Dispatch a workflow step with persistent retry logic (Req [WF-B])
     * Used by: Individual automation agents (Cron/Worker)
     */
    public function executeStep(int $logId, callable $action): array
    {
        // 1. Fetch step status & retry count
        $log = $this->db->GetRow("SELECT retry_count, status FROM workflow_log WHERE id = ?", [$logId]);

        if ($log['status'] === 'completed') return ['status' => 'already_done'];

        try {
            // 2. Perform the Action (e.g., Send Waba, Create Invoice)
            $result = $action();
            
            // 3. Status: SUCCESS
            $this->db->Execute("UPDATE workflow_log SET status = 'completed', ended_at = NOW() WHERE id = ?", [$logId]);
            return ['status' => 'success', 'result' => $result];

        } catch (\Exception $e) {
            // 4. Status: FAILED (Trigger Retry)
            $newCount = $log['retry_count'] + 1;
            $status = $newCount >= 3 ? 'dead_letter' : 'retrying'; // Rule: Max 3 retries
            
            $this->db->Execute(
                "UPDATE workflow_log SET status = ?, retry_count = ?, last_error = ? WHERE id = ?",
                [$status, $newCount, substr($e->getMessage(), 0, 255), $logId]
            );

            return ['status' => 'failed', 'retry' => ($status === 'retrying')];
        }
    }
}
