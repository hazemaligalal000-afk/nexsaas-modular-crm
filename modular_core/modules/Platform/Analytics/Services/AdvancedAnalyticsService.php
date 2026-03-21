<?php
/**
 * ModularCore/Modules/Platform/Analytics/Services/AdvancedAnalyticsService.php
 * Enterprise-grade Business Intelligence (Requirements 10.8, 10.9)
 */

namespace ModularCore\Modules\Platform\Analytics\Services;

use Core\Database;

class AdvancedAnalyticsService {
    
    /**
     * Get Conversion Funnel (Leads -> Deals -> Won)
     */
    public function getConversionFunnel(int $tenantId, string $period = '30d') {
        $pdo = Database::getTenantConnection();
        $interval = match($period) {
            '7d' => "INTERVAL '7 days'",
            '90d' => "INTERVAL '90 days'",
            default => "INTERVAL '30 days'",
        };

        $sql = "
            SELECT 
                (SELECT COUNT(*) FROM leads WHERE tenant_id = ? AND created_at > NOW() - {$interval}) as total_leads,
                (SELECT COUNT(*) FROM deals WHERE tenant_id = ? AND created_at > NOW() - {$interval}) as total_deals,
                (SELECT COUNT(*) FROM deals WHERE tenant_id = ? AND stage = 'Won' AND updated_at > NOW() - {$interval}) as total_won
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId, $tenantId, $tenantId]);
        return $stmt->fetch();
    }

    /**
     * Get MRR Prediction based on weighted pipeline
     */
    public function getRevenueForecast(int $tenantId) {
        $pdo = Database::getTenantConnection();
        $sql = "
            SELECT 
                SUM(value * probability / 100) as weighted_value,
                SUM(value) as total_pipeline
            FROM deals 
            WHERE tenant_id = ? AND stage NOT IN ('Won', 'Lost')
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        return $stmt->fetch();
    }

    /**
     * Omnichannel Occupancy: How much volume per channel
     */
    public function getChannelDistribution(int $tenantId) {
        $pdo = Database::getTenantConnection();
        $sql = "SELECT channel, COUNT(*) as count FROM conversations WHERE tenant_id = ? GROUP BY channel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }
}
