<?php
/**
 * CRM/DealsModel.php
 * 
 * BATCH PIPELINE — Visual Sales Management
 */

declare(strict_types=1);

namespace Modules\CRM;

use Core\BaseModel;

class DealsModel extends BaseModel
{
    protected string $table = 'crm_deals';

    /**
     * Get deals grouped by stage for Kanban
     */
    public function getBoard(string $tenantId, string $companyCode): array
    {
        $sql = "SELECT id, title, amount, stage, lead_id, expected_close_date, 
                       (SELECT name_en FROM leads WHERE id = lead_id) as lead_name
                FROM crm_deals 
                WHERE tenant_id = ? AND company_code = ? AND deleted_at IS NULL
                ORDER BY stage, amount DESC";
        
        $deals = $this->db->GetAll($sql, [$tenantId, $companyCode]);
        
        $board = [
            'prospecting' => [],
            'qualification' => [],
            'proposal' => [],
            'negotiation' => [],
            'closed_won' => [],
            'closed_lost' => []
        ];

        foreach ($deals as $deal) {
            $board[$deal['stage']][] = $deal;
        }

        return $board;
    }

    /**
     * Update deal stage (drag-and-drop trigger)
     */
    public function updateStage(int $dealId, string $newStage, int $userId): bool
    {
        // Advanced: Audit trail for stage change
        $data = [
            'stage' => $newStage,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $success = $this->update($dealId, $data);

        if ($success) {
            // FIRE EVENT: Deal Stage Updated (Omnichannel Trigger)
            $this->fireEvent('crm.deal_stage_changed', [
                'deal_id' => $dealId,
                'new_stage' => $newStage,
                'performed_by' => $userId
            ]);
        }

        return $success;
    }
}
