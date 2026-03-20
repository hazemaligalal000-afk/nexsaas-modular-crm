<?php

namespace ModularCore\Modules\Platform\Billing;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantLockoutMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) return $next($request);

        $tenant = $user->tenant;
        if (!$tenant) return $next($request);

        # 1. Skip checks for Billing/Public endpoints
        if ($request->is('api/v1/billing/*') || $request->is('health') || $request->is('webhooks/*')) {
            return $next($request);
        }

        # 2. Check for Lockout
        if ($tenant->subscription_status === 'canceled' || $tenant->subscription_status === 'unpaid') {
            return response()->json([
                'success' => False,
                'error' => [
                    'code' => 403,
                    'message' => 'Subscription Canceled or Unpaid. Access is locked.',
                    'billing_url' => config('app.url') . '/billing'
                ]
            ], 403);
        }

        # 3. Check for expired Grace Period (Past Due)
        if ($tenant->subscription_status === 'past_due' && $tenant->grace_period_end < now()) {
            $tenant->update(['subscription_status' => 'unpaid', 'access_locked_at' => now()]);
            return response()->json([
                'success' => False,
                'error' => [
                    'code' => 403,
                    'message' => 'Grace period has expired. Please update your payment method.',
                    'billing_url' => config('app.url') . '/billing'
                ]
            ], 403);
        }

        return $next($request);
    }
}
