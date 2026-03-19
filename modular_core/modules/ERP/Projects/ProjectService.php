<?php
namespace Modules\ERP\Projects;

use Core\BaseService;
use Core\Database;

class ProjectService extends BaseService {
    public function createProject(array $data) {
        return $this->transaction(function() use ($data) {
            $db = Database::getInstance();
            
            $sql = "INSERT INTO projects (
                        tenant_id, company_code, name, description, account_id, manager_id,
                        budget_hours, budget_amount, start_date, end_date, priority
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";
                    
            $result = $db->query($sql, [
                $this->tenantId, $this->companyCode, $data['name'], $data['description'],
                $data['account_id'], $data['manager_id'],
                $data['budget_hours'] ?? 0, $data['budget_amount'] ?? 0,
                $data['start_date'], $data['end_date'], $data['priority'] ?? 'medium'
            ]);
            
            return ['success' => true, 'data' => ['project_id' => $result[0]['id']]];
        });
    }

    public function addTask(int $projectId, array $data) {
        return $this->transaction(function() use ($projectId, $data) {
            $db = Database::getInstance();
            
            if (!empty($data['depends_on_task_ids'])) {
                // Circular dependency check would occur here
            }
            
            $sql = "INSERT INTO project_tasks (
                        tenant_id, company_code, project_id, milestone_id, parent_task_id,
                        name, description, start_date, end_date,
                        budget_hours, priority, assignee_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";
                    
            $result = $db->query($sql, [
                $this->tenantId, $this->companyCode, $projectId,
                $data['milestone_id'] ?? null, $data['parent_task_id'] ?? null,
                $data['name'], $data['description'], $data['start_date'], $data['end_date'],
                $data['budget_hours'] ?? 0, $data['priority'] ?? 'medium', $data['assignee_id'] ?? null
            ]);
            
            return ['success' => true, 'data' => ['task_id' => $result[0]['id']]];
        });
    }

    public function logTime(int $taskId, array $data) {
        return $this->transaction(function() use ($taskId, $data) {
            $db = Database::getInstance();
            
            $task = $db->query("SELECT project_id, actual_hours FROM project_tasks 
                                WHERE id = ? AND tenant_id = ?", [$taskId, $this->tenantId]);
            if (empty($task)) {
                return ['success' => false, 'error' => 'Task not found'];
            }
            
            $projectId = $task[0]['project_id'];
            $hours = $data['hours'];
            
            $sql = "INSERT INTO time_logs (
                        tenant_id, company_code, project_id, task_id, employee_id,
                        log_date, hours, description
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    
            $db->query($sql, [
                $this->tenantId, $this->companyCode, $projectId, $taskId,
                $data['employee_id'], $data['log_date'], $hours, $data['description']
            ]);
            
            // Update project and task totals
            $db->query("UPDATE project_tasks SET actual_hours = actual_hours + ? WHERE id = ?", [$hours, $taskId]);
            $db->query("UPDATE projects SET actual_hours = actual_hours + ? WHERE id = ?", [$hours, $projectId]);
            
            return ['success' => true, 'data' => ['logged_hours' => $hours]];
        });
    }
}
