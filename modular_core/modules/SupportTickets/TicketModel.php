<?php
/**
 * SupportTickets/TicketModel.php
 * 
 * CORE → ADVANCED: SLA-Driven Support Engine
 */

declare(strict_types=1);

namespace Modules\SupportTickets;

use Core\BaseModel;

class TicketModel extends BaseModel
{
    protected string $table = 'support_tickets';

    /**
     * Create a ticket with SLA calculation
     */
    public function createTicket(array $data): int
    {
        // 1. Assign Priority & SLA (Rule: Priority by Category)
        $priorityProb = [
            'critical' => 4,    // 4 Hours SLA
            'high' => 24,       // 24 Hours SLA
            'medium' => 48,     // 48 Hours SLA
            'low' => 72         // 72 Hours SLA
        ];
        
        $priority = $data['priority'] ?? 'medium';
        $slaHours = $priorityProb[$priority] ?? 48;
        
        $data['sla_due_at'] = date('Y-m-d H:i:s', strtotime("+{$slaHours} hours"));
        $data['status'] = 'open';

        $id = $this->insert($data);

        // 2. FIRE EVENT: Ticket Created (Triggers agent notification)
        $this->fireEvent('support.ticket_created', array_merge(['id' => $id], $data));

        return $id;
    }

    /**
     * Get tickets with visual SLA indicators
     */
    public function getActiveBoard(string $tenantId): array
    {
        $sql = "SELECT id, subject, priority, sla_due_at, status, 
                       (CASE WHEN sla_due_at < NOW() THEN 'BREACHED' ELSE 'OK' END) as sla_status
                FROM support_tickets 
                WHERE tenant_id = ? AND status != 'closed' AND deleted_at IS NULL
                ORDER BY sla_due_at ASC";
        
        return $this->db->GetAll($sql, [$tenantId]);
    }
}
