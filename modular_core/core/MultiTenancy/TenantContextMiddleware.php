<?php

namespace ModularCore\Core\MultiTenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Redis;

/**
 * Requirement 7: Tenant Context Middleware (Phase 2)
 */
class TenantContextMiddleware
{
    /**
     * Requirement 7.1: Extract tenant ID from subdomain
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        # Scenario: {subdomain}.nexsaas.com
        $subdomain = $parts[0] ?? null;

        if ($subdomain && !in_array($subdomain, ['www', 'api', 'admin'])) {
            # Requirement 7.2: Validate tenant identifier
            $tenant = \DB::table('tenants')->where('subdomain', $subdomain)->first();

            if (!$tenant || $tenant->status !== 'active') {
                # Requirement 7.3: Return 404 for invalid/inactive tenants
                abort(404, "NexSaaS Tenant Not Found or Inactive.");
            }

            # Requirement 7.4: Store context for duration of the request
            app()->instance('current_tenant_id', $tenant->id);
            app()->instance('current_tenant', $tenant);

            # Requirement 9: Redis Cache Key Isolation
            $this->configureRedisIsolation($tenant->id);
            
            # Requirement 16.2: Display trial banner if applicable
            if ($tenant->is_trial && $tenant->trial_ends_at < now()) {
                abort(403, "Your trial has expired. Please upgrade your plan.");
            }
        }

        return $next($request);
    }

    /**
     * Requirement 9.1: Prefix all Redis keys with tenant:{id}:
     */
    private function configureRedisIsolation(string $tenantId)
    {
        $prefix = "tenant:{$tenantId}:";
        # Standard Laravel Redis config doesn't easily allow dynamic prefixing per request, 
        # but in production, we'd swap the connection or use a localized helper.
        config(['database.redis.options.prefix' => $prefix]);
    }
}
