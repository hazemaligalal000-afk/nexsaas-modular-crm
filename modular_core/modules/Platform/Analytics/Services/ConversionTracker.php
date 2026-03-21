<?php
/**
 * ModularCore/Modules/Platform/Analytics/Services/ConversionTracker.php
 * CRO & Real-time Social Proof Engine (Phase 1: Quick Wins)
 */

namespace ModularCore\Modules\Platform\Analytics\Services;

use Core\Database;
use ModularCore\Modules\Platform\Integrations\PusherService;

class ConversionTracker {
    
    /**
     * Log a conversion event and broadcast to active dashboard users
     * Creates "The Herd Effect" (Requirement Phase 1.1)
     */
    public function logMajorConversion(int $tenantId, string $eventType, array $data) {
        $pdo = Database::getCentralConnection();
        $stmt = $pdo->prepare("INSERT INTO conversion_audit (tenant_id, event_type, metadata, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$tenantId, $eventType, json_encode($data)]);

        // Broadcast to all active trial users globally for "Unicorn Social Proof"
        PusherService::trigger('global-social-proof', 'conversion-toast', [
            'type' => $eventType,
            'location' => $data['location'] ?? 'Global',
            'amount' => $data['amount'] ?? 0,
            'time' => 'Just now'
        ]);
    }

    /**
     * Get 1-Click Onboarding Progress for UI
     */
    public function getOnboardingStatus(int $tenantId) {
        $pdo = Database::getCentralConnection();
        $sql = "SELECT onboarding_percent, missing_steps FROM tenant_growth WHERE tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        return $stmt->fetch();
    }
}
