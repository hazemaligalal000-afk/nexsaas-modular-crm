<?php
/**
 * WhiteLabel/WhiteLabelController.php
 * 
 * CORE → ADVANCED: Dynamic White-Label Engine
 */

declare(strict_types=1);

namespace Modules\WhiteLabel;

use Core\BaseController;
use Core\Response;
use Modules\Platform\Auth\AuthMiddleware;

class WhiteLabelController extends BaseController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get branding themes for the current tenant
     * Used by: App.tsx or ThemeProvider.tsx
     */
    public function getBranding($request): Response
    {
        $tenantId = $request['queries']['tenant_id'] ?? $this->tenantId;
        $sql = "SELECT primary_color, secondary_color, logo_url, custom_domain, 
                       favicon_url, company_name_alias, footer_text_en, footer_text_ar,
                       theme_variant (dark/light)
                FROM tenant_branding 
                WHERE tenant_id = ? AND is_active = TRUE";
        
        $branding = $this->db->GetRow($sql, [$tenantId]);

        if (empty($branding)) {
            // Default NexSaaS Branding
            return $this->respond([
                'primary_color' => '#2563eb', // blue-600
                'secondary_color' => '#1e293b', // slate-800
                'logo_url' => '/static/logo-default.png',
                'company_name' => 'NexSaaS CRM+',
                'theme' => 'light'
            ]);
        }

        return $this->respond($branding, 'Branding loaded successfully');
    }

    /**
     * Update branding configuration (Admin Only)
     */
    public function updateBranding($request): Response
    {
        AuthMiddleware::verify($request); // Should check is_admin role
        $data = $request['body'];

        $sql = "INSERT INTO tenant_branding 
                (tenant_id, primary_color, secondary_color, logo_url, custom_domain, theme_variant)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (tenant_id) 
                DO UPDATE SET 
                    primary_color = EXCLUDED.primary_color,
                    secondary_color = EXCLUDED.secondary_color,
                    logo_url = EXCLUDED.logo_url,
                    custom_domain = EXCLUDED.custom_domain,
                    theme_variant = EXCLUDED.theme_variant,
                    updated_at = NOW()";
        
        $this->db->Execute($sql, [
            $this->tenantId, 
            $data['primary_color'], 
            $data['secondary_color'], 
            $data['logo_url'], 
            $data['custom_domain'],
            $data['theme_variant']
        ]);

        return $this->respond(null, 'White-label branding updated successfully');
    }
}
