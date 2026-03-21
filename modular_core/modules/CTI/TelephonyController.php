<?php
/**
 * CTI/TelephonyController.php
 * 
 * CORE → ADVANCED: Softphone & Inbound CTI Hub
 */

declare(strict_types=1);

namespace Modules\CTI;

use Core\BaseController;
use Core\Response;
use Modules\Platform\Auth\AuthMiddleware;

class TelephonyController extends BaseController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Handle inbound call webhook from Twilio/Asterisk
     * Used by: Individual clients to automatically see their previous data
     */
    public function handleInboundCall($request): Response
    {
        $from = $request['queries']['from'];
        $tenantId = $request['queries']['tenant_id'] ?? $this->tenantId;

        // 1. Identify Caller (Rules: CRM Partner Search)
        $sql = "SELECT id, name_en, name_ar, source FROM leads 
                WHERE tenant_id = ? AND phone = ? AND deleted_at IS NULL";
        
        $caller = $this->db->GetRow($sql, [$tenantId, $from]);

        if (!$caller) {
            $caller = ['id' => 0, 'name' => 'Unknown Caller (' . $from . ')'];
        }

        // 2. Automated Global Audit Log & Call History
        $this->db->Execute(
            "INSERT INTO call_history (tenant_id, caller_no, lead_id, call_type, status, created_at)
             VALUES (?, ?, ?, 'inbound', 'ringing', NOW())",
            [$tenantId, $from, $caller['id'] ?? 0]
        );

        return $this->respond([
            'caller' => $caller,
            'status' => 'identified',
            'redirect_to' => '/crm/leads/' . ($caller['id'] ?? 'new')
        ], 'Inbound call identified successfully');
    }

    /**
     * Terminate call and log record
     */
    public function logCallEnd($request): Response
    {
        $callId = $request['body']['call_id'];
        $duration = $request['body']['duration'];

        $this->db->Execute(
            "UPDATE call_history SET status = 'completed', duration = ?, ended_at = NOW() WHERE id = ?",
            [$duration, $callId]
        );

        return $this->respond(null, 'Call logged successfully');
    }
}
