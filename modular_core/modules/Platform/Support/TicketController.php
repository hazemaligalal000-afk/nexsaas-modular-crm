<?php

namespace ModularCore\Modules\Platform\Support;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use ModularCore\Modules\Platform\Support\LinearTicketService;
use Exception;

/**
 * Task 12.3: Support Ticket Management API
 */
class TicketController extends Controller
{
    private $linear;

    public function __construct(LinearTicketService $linear)
    {
        $this->linear = $linear;
    }

    /**
     * Requirement 10.1: Create Ticket and Sync to Linear
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|max:255',
            'description' => 'required',
            'priority'    => 'in:low,medium,high,urgent'
        ]);

        $user = $request->user();

        try {
            # 1. Sync to Linear (Requirement 10.1)
            $ticket = $this->linear->createTicket(
                $request->title, 
                $request->description, 
                $user->tenant_id, 
                $request->priority
            );

            # 2. Persist in local DB for tenant isolation and history (Requirement 12.2)
            \DB::table('support_tickets')->insert([
               'tenant_id'  => $user->tenant_id,
               'user_id'    => $user->id,
               'linear_id'  => $ticket['id'],
               'identifier' => $ticket['identifier'],
               'title'      => $request->title,
               'status'     => 'open',
               'priority'   => $request->priority,
               'created_at' => now(),
               'updated_at' => now()
            ]);

            return response()->json(['success' => true, 'ticket' => $ticket]);

        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Requirement 10.6: RBAC check - ensure user only sees their tenant tickets
     */
    public function index(Request $request)
    {
        $tickets = \DB::table('support_tickets')
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($tickets);
    }
}
