<?php

namespace ModularCore\Modules\Integrations\Zapier;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use ModularCore\Modules\Omnichannel\WhatsApp\WhatsAppService;
use Exception;

/**
 * Zapier Action Controller: Inbound No-code Automation (Requirement I2)
 */
class ZapierActionController extends Controller
{
    private $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Requirement 577: Create Lead via Zapier Action
     */
    public function createLead(Request $request)
    {
        $request->validate(['first_name' => 'required', 'email' => 'required|email']);
        
        $tenantId = $request->user()->tenant_id;
        
        $leadId = \DB::table('leads')->insertGetId(array_merge($request->all(), [
            'tenant_id' => $tenantId,
            'source' => 'Zapier',
            'created_at' => now(),
        ]));

        return response()->json(['id' => $leadId, 'status' => 'created'], 201);
    }

    /**
     * Requirement 582: Send WhatsApp Message via Zapier Action
     */
    public function sendWhatsApp(Request $request)
    {
        $request->validate(['to' => 'required', 'message' => 'required']);
        
        $res = $this->whatsapp->sendMessage($request->to, $request->message);
        return response()->json($res);
    }

    /**
     * Requirement 580: Log Activity via Zapier Action
     */
    public function logActivity(Request $request)
    {
        $request->validate(['lead_id' => 'required', 'note' => 'required']);

        $tenantId = $request->user()->tenant_id;
        
        \DB::table('activities')->insert([
            'tenant_id' => $tenantId,
            'lead_id' => $request->lead_id,
            'type' => 'note',
            'content' => $request->note,
            'created_at' => now(),
        ]);

        return response()->json(['status' => 'activity_logged']);
    }
}
