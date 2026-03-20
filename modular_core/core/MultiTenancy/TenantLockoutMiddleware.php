<?php

namespace ModularCore\Core\MultiTenancy;

use Closure;
use Illuminate\Http\Request;
use Exception;

/**
 * Tenant Lockout Middleware: Enforces Billing Compliance (Security & SaaS)
 * Requirement 14.1: Subscription status check on every request.
 * Requirement 14.3: Allows access to billing endpoints for locked tenants.
 */
class TenantLockoutMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $tenant = $request->get('tenant'); // Injected by TenantContextMiddleware

        if (!$tenant) {
            return $next($request);
        }

        // Requirement 14.3: Allow billing routes for locked tenants
        if ($request->is('api/v1/billing/*') || $request->is('billing/*')) {
            return $next($request);
        }

        // Requirements 10.5, 14.2: Canceled/Unpaid tenant access restriction
        if (in_array($tenant->subscription_status, ['canceled', 'unpaid'])) {
            return response()->json([
                'error' => 'Your subscription is ' . $tenant->subscription_status . '. Access to the CRM is restricted.',
                'code' => 'TENANT_LOCKED',
                'billing_url' => '/settings/billing',
                'locked_at' => $tenant->access_locked_at,
            ], 403);
        }

        // Requirement 11.6, 13.1: Past Due with Grace Period Check
        if ($tenant->subscription_status === 'past_due') {
            $now = now();
            if ($tenant->grace_period_end && $now->greaterThan($tenant->grace_period_end)) {
                // Task 13.6: Auto-update to 'unpaid' if grace period expired
                \DB::table('tenants')->where('id', $tenant->id)->update([
                    'subscription_status' => 'unpaid',
                    'access_locked_at' => $now,
                ]);

                return response()->json([
                    'error' => 'Your grace period for payment has expired. Please update your billing information.',
                    'code' => 'TENANT_LOCKED',
                    'billing_url' => '/settings/billing',
                ], 403);
            }
        }

        return $next($request);
    }
}
