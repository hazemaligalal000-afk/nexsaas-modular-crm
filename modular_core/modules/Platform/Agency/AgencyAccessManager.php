<?php

namespace ModularCore\Modules\Platform\Agency;

/**
 * Agency Access Manager: Multi-client Dashboard Context (Requirement S3)
 * Orchestrates cross-tenant visibility for Marketing/Support Agencies.
 */
class AgencyAccessManager
{
    /**
     * Requirement S3: Get Connected Clients per Agency
     */
    public function getClients($agencyUserId)
    {
        return \DB::table('agency_client_map')
            ->where('agency_user_id', $agencyUserId)
            ->join('tenants', 'agency_client_map.client_tenant_id', '=', 'tenants.id')
            ->select(['tenants.id', 'tenants.name', 'tenants.subdomain', 'tenants.current_tier'])
            ->get();
    }

    /**
     * Requirement S3: Aggregate Multi-client Performance (Leads/Revenue)
     */
    public function getAggregateMetrics($agencyUserId)
    {
        $clients = $this->getClients($agencyUserId);
        $clientIds = $clients->pluck('id');

        return [
            'total_managed_leads' => \DB::table('leads')->whereIn('tenant_id', $clientIds)->count(),
            'total_managed_deals' => \DB::table('deals')->whereIn('tenant_id', $clientIds)->count(),
            'active_clients' => count($clients),
            'last_sync' => now()->toIso8601String(),
        ];
    }

    /**
     * Context Switch Security: Verify User has access to Target Tenant
     */
    public function hasAccess($agencyUserId, $targetTenantId)
    {
        return \DB::table('agency_client_map')
            ->where('agency_user_id', $agencyUserId)
            ->where('client_tenant_id', $targetTenantId)
            ->exists();
    }
}
