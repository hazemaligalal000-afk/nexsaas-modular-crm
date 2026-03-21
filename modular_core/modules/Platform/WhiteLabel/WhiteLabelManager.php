<?php
/**
 * Platform/WhiteLabel/WhiteLabelManager.php
 * 
 * Rebranding & White-labeling engine for Enterprise Multi-Tenancy (Requirement 10.5)
 */

namespace NexSaaS\Platform\WhiteLabel;

class WhiteLabelManager
{
    private $adb;

    public function __construct($adb) {
        $this->adb = $adb;
    }

    /**
     * Get White-labeling Settings for Tenant
     */
    public function getSettings(int $tenantId): array
    {
        $query = "SELECT brand_logo, primary_color, custom_domain, portal_title FROM saas_white_label WHERE tenant_id = ? AND active = 1";
        $result = $this->adb->pquery($query, [$tenantId]);

        if ($this->adb->num_rows($result) === 0) {
            return [
                'logo' => '/static/logo_default.png',
                'primary_color' => '#3b82f6',
                'portal_title' => 'NexSaaS CRM'
            ];
        }

        $row = $this->adb->fetch_array($result);
        return [
            'logo' => $row['brand_logo'],
            'primary_color' => $row['primary_color'],
            'portal_title' => $row['portal_title'],
            'custom_domain' => $row['custom_domain']
        ];
    }

    /**
     * Set/Update settings
     */
    public function setSettings(int $tenantId, array $settings): void
    {
        $query = "UPSERT INTO saas_white_label (tenant_id, brand_logo, primary_color, custom_domain, portal_title) VALUES (?, ?, ?, ?, ?)";
        $this->adb->pquery($query, [
            $tenantId,
            $settings['logo'],
            $settings['primary_color'],
            $settings['custom_domain'],
            $settings['portal_title']
        ]);
    }
}
