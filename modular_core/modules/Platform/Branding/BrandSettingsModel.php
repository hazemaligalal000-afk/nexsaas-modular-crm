<?php

namespace ModularCore\Modules\Platform\Branding;

/**
 * Brand Settings Model: White-label & Reseller Data Hub (Requirement S2)
 * Orchestrates brand inheritance and per-tenant visual overrides.
 */
class BrandSettingsModel
{
    /**
     * Requirement S2: Retrieve Active Branding Context
     */
    public function getForTenant($tenantId)
    {
        // 1. Check for specific tenant overrides
        $tenantBranding = \DB::table('tenant_branding')
            ->where('tenant_id', $tenantId)
            ->first();

        if ($tenantBranding) {
            return $this->formatBranding($tenantBranding);
        }

        // 2. Fallback to Partner/Reseller defaults if mapped
        $partnerId = \DB::table('tenants')->where('id', $tenantId)->value('partner_id');
        if ($partnerId) {
            $partnerBranding = \DB::table('partner_branding')
                ->where('partner_id', $partnerId)
                ->first();
            
            if ($partnerBranding) {
                return $this->formatBranding($partnerBranding);
            }
        }

        // 3. Global Default (NexSaaS)
        return [
            'brand_name' => 'NexSaaS CRM',
            'primary_color' => '#1d4ed8', // Royal Blue
            'secondary_color' => '#0f172a',
            'logo_url' => '/assets/logo_default.png',
            'favicon_url' => '/favicon.ico',
            'support_email' => 'support@nexsaas.com',
            'is_default' => true
        ];
    }

    private function formatBranding($record)
    {
        return [
            'brand_name' => $record->brand_name,
            'primary_color' => $record->primary_color,
            'secondary_color' => $record->secondary_color ?? '#0f172a',
            'logo_url' => $record->logo_url,
            'favicon_url' => $record->favicon_url,
            'support_email' => $record->support_email,
            'is_default' => false
        ];
    }

    /**
     * Requirement S3: Bulk Update Branding for Partner Clients
     */
    public function updatePartnerClients($partnerId, array $settings)
    {
        // Propagate branding changes to all clients under this partner
        \DB::table('tenant_branding')
            ->whereIn('tenant_id', function ($query) use ($partnerId) {
                $query->select('id')->from('tenants')->where('partner_id', $partnerId);
            })
            ->update($settings);
    }
}
