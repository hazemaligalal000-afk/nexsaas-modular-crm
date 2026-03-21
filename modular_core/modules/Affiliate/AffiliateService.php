<?php
/**
 * Affiliate/AffiliateService.php
 * 
 * CORE → ADVANCED: Dynamic Partner & Commission Engine
 */

declare(strict_types=1);

namespace Modules\Affiliate;

use Core\BaseService;

class AffiliateService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Calculate and record commission for an affiliate on a sale
     * Rule: 20% commission on recurring billing_subscriptions
     */
    public function recordReferralSale(string $affiliateCode, string $tenantId, float $saleAmount): array
    {
        // 1. Fetch Affiliate details by code
        $sql = "SELECT id, commission_rate, total_earnings FROM affiliates 
                WHERE affiliate_code = ? AND is_active = TRUE";
        
        $aff = $this->db->GetRow($sql, [$affiliateCode]);

        if (!$aff) throw new \RuntimeException("Invalid affiliate code: " . $affiliateCode);

        $rate = $aff['commission_rate'] ?? 0.20; // Default 20%
        $commission = $saleAmount * $rate;

        // 2. Insert Commission Record
        $data = [
            'affiliate_id' => $aff['id'],
            'tenant_id' => $tenantId,
            'amount' => $commission,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->db->AutoExecute('affiliate_commissions', $data, 'INSERT');

        // 3. Update Affiliate Earnings
        $this->db->Execute(
            "UPDATE affiliates SET total_earnings = total_earnings + ? WHERE id = ?",
            [$commission, $aff['id']]
        );

        // 4. FIRE EVENT: Affiliate Commission (Automated payout or notification)
        // $this->fireEvent('affiliate.commission_earned', $data);

        return $data;
    }
}
