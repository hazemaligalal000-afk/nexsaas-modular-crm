<?php
namespace Modules\Platform\Branding;

use Core\BaseService;

/**
 * White Label Service: Multi-tenant branding customization.
 * Requirement 12.1-12.5 (Phase 10 Roadmap)
 */
class WhiteLabelService extends BaseService {
    
    public function getBranding(string $tenantId) {
        $sql = "SELECT * FROM tenant_branding WHERE tenant_id = ?";
        $branding = $this->db->GetRow($sql, [$tenantId]);
        
        return $branding ?: $this->getDefaultBranding();
    }

    public function updateBranding(string $tenantId, array $data) {
        // Enforce validations (Requirement 12.3: max logo size 2MB)
        if (isset($data['logo_size']) && $data['logo_size'] > 2097152) {
            throw new \Exception("Logo exceeds 2MB limit.");
        }

        $sql = "INSERT INTO tenant_branding (tenant_id, primary_color, secondary_color, custom_domain, logo_url) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                primary_color = VALUES(primary_color),
                secondary_color = VALUES(secondary_color),
                custom_domain = VALUES(custom_domain),
                logo_url = VALUES(logo_url)";
        
        return $this->db->Execute($sql, [
            $tenantId, 
            $data['primary_color'], 
            $data['secondary_color'], 
            $data['custom_domain'], 
            $data['logo_url']
        ]);
    }

    private function getDefaultBranding() {
        return [
            'primary_color' => '#0047FF',
            'secondary_color' => '#0F172A',
            'custom_domain' => null,
            'logo_url' => '/assets/nexsaas_default.png'
        ];
    }
}
