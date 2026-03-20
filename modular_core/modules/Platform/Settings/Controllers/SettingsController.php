<?php

namespace ModularCore\Modules\Platform\Settings\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;

/**
 * Settings Controller: Unified Platform Configuration Hub (Requirement Phase 0-4)
 * Orchestrates Tenant Profiles, Billing methods, and API Authorization.
 */
class SettingsController extends Controller
{
    /**
     * Requirement 50: Update Tenant Profile (Name, Subdomain, Branding)
     */
    public function updateProfile(Request $request)
    {
        $request->validate(['name' => 'sometimes', 'primary_color' => 'sometimes']);
        $tenantId = $request->user()->tenant_id;

        \DB::table('tenants')->where('id', $tenantId)->update($request->only('name'));
        
        if ($request->has('primary_color')) {
            \DB::table('tenant_branding')->updateOrInsert(
                ['tenant_id' => $tenantId],
                ['primary_color' => $request->primary_color]
            );
        }

        return response()->json(['success' => true]);
    }

    /**
     * Requirement 10.1: Multi-method Billing Management
     */
    public function getBillingOverview(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        
        $subscription = \DB::table('tenants')
            ->select(['current_tier', 'subscription_status', 'grace_period_end'])
            ->where('id', $tenantId)
            ->first();

        $methods = \DB::table('payment_gateways')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'subscription' => $subscription,
            'gateways' => $methods
        ]);
    }

    /**
     * Requirement 30: Scoped API Key Management
     */
    public function generateApiKey(Request $request)
    {
        $request->validate(['label' => 'required', 'scope' => 'required']);
        $tenantId = $request->user()->tenant_id;

        $key = 'nx_' . bin2hex(random_bytes(24));
        
        \DB::table('api_keys')->insert([
            'tenant_id' => $tenantId,
            'label' => $request->label,
            'scoped_permissions' => $request->scope,
            'key_hash' => hash('sha256', $key),
            'created_at' => now()
        ]);

        return response()->json(['api_key' => $key]); // Return plain key only once
    }
}
