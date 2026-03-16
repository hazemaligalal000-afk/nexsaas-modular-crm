<?php
/**
 * Modules/Workflows/WorkflowEngine.php
 * The heart of SaaS automation. Processes triggers and executes actions.
 */

namespace Modules\Workflows;

class WorkflowEngine {
    
    /**
     * Dispatch an event to the workflow engine.
     * @param string $triggerType e.g., 'lead_created', 'deal_stage_changed'
     * @param array $payload The data related to the event
     */
    public static function dispatch($triggerType, $payload) {
        $tenantId = \Core\TenantResolver::getTenantId();
        
        // 1. Fetch active workflows for this tenant and trigger type
        $workflows = self::getActiveWorkflows($tenantId, $triggerType);

        foreach ($workflows as $workflow) {
            // 2. Queue the workflow for background execution (using Redis/RabbitMQ)
            self::queueExecution($workflow, $payload);
        }
    }

    private static function getActiveWorkflows($tenantId, $triggerType) {
        $pdo = \Core\Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM workflows WHERE tenant_id = ? AND trigger_type = ? AND is_active = 1");
        $stmt->execute([$tenantId, $triggerType]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function queueExecution($workflow, $payload) {
        // Logic to push to a background worker
        // Job::dispatch(new ExecuteWorkflow($workflow, $payload));
    }

    /**
     * Execute specific actions defined in the visual workflow builder.
     */
    public function executeAction($action, $context) {
        switch ($action['type']) {
            case 'send_telegram':
                $provider = new \Modules\Omnichannel\Providers\TelegramProvider($action['bot_token']);
                $provider.sendMessage($action['chat_id'], $action['message']);
                break;
            case 'assign_lead':
                // Lead routing logic
                break;
            case 'update_contact_field':
                // DB update logic
                break;
        }
    }
}
