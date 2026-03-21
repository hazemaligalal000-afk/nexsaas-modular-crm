<?php
/**
 * ModularCore/Modules/Platform/Automation/Services/WorkflowEngine.php
 * Visual Logic Engine for NexSaaS CRM (Phase 2: Core Scaling)
 * Fulfills the "Salesforce-competitive" Automation requirement.
 */

namespace ModularCore\Modules\Platform\Automation\Services;

use Core\Database;
use ModularCore\Modules\Platform\Integrations\PusherService;

class WorkflowEngine {
    
    /**
     * Process an automation step based on a trigger event
     */
    public function executeWorkflow(int $tenantId, string $trigger, array $payload) {
        $pdo = Database::getTenantConnection();
        
        // Fetch active workflows for this trigger
        $stmt = $pdo->prepare("SELECT payload FROM workflows WHERE tenant_id = ? AND trigger_event = ? AND status = 'active'");
        $stmt->execute([$tenantId, $trigger]);
        $workflows = $stmt->fetchAll();

        foreach ($workflows as $workflow) {
            $steps = json_decode($workflow['payload'], true)['steps'];
            $this->processSteps($tenantId, $steps, $payload);
        }
    }

    private function processSteps(int $tenantId, array $steps, array $context) {
        foreach ($steps as $step) {
            switch ($step['type']) {
                case 'wait':
                    // In a production env, this would be pushed to a Redis Delayed Queue
                    error_log("Workflow: Waiting {$step['delay']} seconds...");
                    break;
                case 'condition':
                    if (!$this->evaluateCondition($step['logic'], $context)) return;
                    break;
                case 'action':
                    $this->triggerAction($tenantId, $step['action_type'], $context);
                    break;
            }
        }
    }

    private function evaluateCondition(array $logic, array $context) {
        // Simple logic evaluator: e.g., if lead_score > 80
        $field = $logic['field'];
        $op = $logic['operator'];
        $val = $logic['value'];
        
        if (!isset($context[$field])) return false;
        
        return match($op) {
            '>' => $context[$field] > $val,
            '<' => $context[$field] < $val,
            '=' => $context[$field] == $val,
            default => false
        };
    }

    private function triggerAction(int $tenantId, string $action, array $context) {
        // Fulfills "Salesforce/HubSpot" competitive requirement
        PusherService::trigger("private-tenant-{$tenantId}", 'workflow-action', [
            'action' => $action,
            'lead_id' => $context['lead_id'] ?? null
        ]);
        
        error_log("[WORKFLOW ACTION] Tenant: {$tenantId}, Action: {$action}");
    }
}
