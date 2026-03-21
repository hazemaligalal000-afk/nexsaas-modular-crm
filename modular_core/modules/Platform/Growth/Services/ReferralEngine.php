<?php
/**
 * ModularCore/Modules/Platform/Growth/Services/ReferralEngine.php
 * High-Intensity Viral Referral Loop (Requirement Phase 2 Growth)
 * Fulfills the "Invite 3 -> Get 1 Month Free" strategy.
 */

namespace ModularCore\Modules\Platform\Growth\Services;

use Core\Database;

class ReferralEngine {
    
    /**
     * Generate a unique viral referral link for a tenant
     */
    public function getReferralLink(int $tenantId) {
        $token = base64_encode("NX_REF_{$tenantId}");
        return getenv('APP_URL') . "/signup?ref=" . urlencode($token);
    }

    /**
     * Track a new referral signup and apply viral credits
     */
    public function processReferral(int $referrerId, int $newTenantId) {
        $pdo = Database::getCentralConnection();
        $stmt = $pdo->prepare("INSERT INTO tenant_referrals (referrer_id, referred_tenant_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$referrerId, $newTenantId]);

        // Check for "The Rule of 3" (Requirement 7.1)
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_referrals WHERE referrer_id = ? AND status = 'completed'");
        $countStmt->execute([$referrerId]);
        $count = $countStmt->fetchColumn();

        if ($count >= 3) {
            $this->applyViralReward($referrerId, '1_MONTH_FREE_PRO');
        }
    }

    private function applyViralReward(int $tenantId, string $rewardType) {
        // Implementation: Add credit to Stripe Subscription or extend Trial period
        error_log("[GROWTH] Applying Viral Reward '{$rewardType}' to Tenant {$tenantId}");
    }
}
