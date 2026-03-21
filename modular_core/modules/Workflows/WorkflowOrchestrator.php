<?php
/**
 * Workflows/WorkflowOrchestrator.php
 * 
 * CORE → ADVANCED: Omnichannel Automation Engine
 */

declare(strict_types=1);

namespace Modules\Workflows;

use Core\BaseService;

class WorkflowOrchestrator extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Trigger workflows based on CRM events
     */
    public function triggerEvent(string $eventName, int $leadId, int $tenantId): void
    {
        // 1. Fetch active workflows for this event
        $sql = "SELECT id, config FROM automation_workflows 
                WHERE event_name = ? AND tenant_id = ? AND is_active = TRUE";
        
        $workflows = $this->db->GetAll($sql, [$eventName, $tenantId]);

        foreach ($workflows as $wf) {
             $config = json_decode($wf['config'], true);

             // Process steps: Notify (Email/Waba), Create Task, or Update Lead
             foreach ($config['steps'] as $step) {
                 switch ($step['type']) {
                     case 'notify':
                         $this->sendNotification($step['channel'], $step['template_id'], $leadId);
                         break;
                     case 'create_task':
                         $this->createFollowupTask($step['title'], $step['due_days'], $leadId);
                         break;
                     case 'update_lead':
                         $this->updateLeadStatus($step['status'], $leadId);
                         break;
                 }
             }
        }
    }

    private function sendNotification(string $channel, int $templateId, int $leadId): void
    {
        // Placeholder for Omnichannel Service (WABA/Email/SMS integration)
        // Rule: Log action in activity_log
        $sql = "INSERT INTO notification_queue (channel, template_id, entity_type, entity_id, status)
                VALUES (?, ?, 'lead', ?, 'queued')";
        
        $this->db->Execute($sql, [$channel, $templateId, $leadId]);
    }

    private function createFollowupTask(string $title, int $dueDays, int $leadId): void
    {
        $dueDate = date('Y-m-d', strtotime("+$dueDays days"));
        $sql = "INSERT INTO tasks (title, due_date, entity_type, entity_id, status)
                VALUES (?, ?, 'lead', ?, 'pending')";
        
        $this->db->Execute($sql, [$title, $dueDate, $leadId]);
    }

    private function updateLeadStatus(string $status, int $leadId): void
    {
        $this->db->Execute("UPDATE leads SET status = ? WHERE id = ?", [$status, $leadId]);
    }
}
