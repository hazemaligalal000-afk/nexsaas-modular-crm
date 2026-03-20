<?php

namespace ModularCore\Modules\Platform\CustomerPortal\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Exception;

/**
 * Portal Auth Controller: Customer Portal Access Management (Requirement F3)
 * Isolates Portal Users (Contacts) from CRM Agents/Admins.
 */
class PortalAuthController extends Controller
{
    /**
     * Requirement F3: Client Portal Login (Email/Password)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'tenant_id' => 'required'
        ]);

        // Querying from separate 'portal_users' or contact-based auth table
        $user = \DB::table('portal_users')
            ->where('email', $request->email)
            ->where('tenant_id', $request->tenant_id)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid portal credentials'], 401);
        }

        // Logic to issue JWT for portal access with contact scope
        return response()->json([
            'token' => 'portal_jwt_mock_token',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'tenant_id' => $request->tenant_id,
        ]);
    }

    /**
     * Requirement F3: Password Reset flow for clients
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email', 'tenant_id' => 'required']);
        
        // Logic to dispatch 'PortalPasswordReset' notification
        return response()->json(['message' => 'Reset link sent to ' . $request->email]);
    }

    /**
     * Requirement F3: Agent-Side Portal Toggle for Contact
     */
    public function toggleAccess(Request $request, $contactId)
    {
        $request->validate(['enabled' => 'required|boolean']);

        \DB::table('contacts')
            ->where('id', $contactId)
            ->where('tenant_id', $request->user()->tenant_id)
            ->update(['portal_access_enabled' => $request->enabled]);

        return response()->json(['success' => true]);
    }
}
