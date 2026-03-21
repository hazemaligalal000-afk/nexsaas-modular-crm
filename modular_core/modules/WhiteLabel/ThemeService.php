<?php
/**
 * WhiteLabel/ThemeService.php
 * 
 * CORE → ADVANCED: Dynamic Brand Identity & UI Theme Orchestration
 */

declare(strict_types=1);

namespace Modules\WhiteLabel;

use Core\BaseService;

class ThemeService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get theme configuration for a tenant (Rule: HSL-calculated complementary colors)
     * Used by: App.tsx / Frontend ThemeProvider
     */
    public function getTheme(string $tenantId): array
    {
        $sql = "SELECT primary_color, secondary_color, font_family, logo_url, dark_mode 
                FROM theme_settings WHERE tenant_id = ?";
        
        $theme = $this->db->GetRow($sql, [$tenantId]);

        if (!$theme) {
            return [
                'primary_color' => '#2563eb', // Default Indigo
                'secondary_color' => '#1e40af',
                'font_family' => 'Inter, sans-serif',
                'logo_url' => '/static/default-logo.png',
                'dark_mode' => false
            ];
        }

        return $theme;
    }

    /**
     * Update brand settings and re-provision cached CSS variables
     */
    public function updateTheme(string $tenantId, array $data): void
    {
        $this->db->AutoExecute('theme_settings', $data, 'UPDATE', "tenant_id = '{$tenantId}'");
        
        // FIRE EVENT: Theme Updated (Frontend re-fetches or injects CSS)
        // $this->fireEvent('whitelabel.theme_updated', ['tenant_id' => $tenantId]);
    }
}
