<?php

namespace ModularCore\Modules\SaaS\Affiliate;

use Exception;

/**
 * Affiliate Service: SaaS Growth & Referral Engine (Requirement S1)
 * Orchestrates referral link clicks, multi-tenant signups, and commissions.
 */
class AffiliateService
{
    /**
     * Requirement 310: Track Affiliate Click Context
     */
    public function trackClick($referralCode, $ipAddress, $userAgent)
    {
        // Increment raw clicks for the partner/affiliate
        \DB::table('affiliate_clicks')->insert([
            'referral_code' => $referralCode,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'clicked_at' => now(),
        ]);
    }

    /**
     * Requirement 311: Secure Referral Link Generation
     */
    public function generateReferralLink($userId)
    {
        $code = \DB::table('affiliates')->where('user_id', $userId)->value('referral_code');
        
        if (!$code) {
            $code = 'REF_' . strtoupper(bin2hex(random_bytes(4)));
            \DB::table('affiliates')->insert([
                'user_id' => $userId,
                'referral_code' => $code,
                'commission_rate' => 20.00, // Default 20% commission
                'created_at' => now()
            ]);
        }

        return url("/signup?ref={$code}");
    }

    /**
     * Requirement 312: Commission Calculation (on Tenant Subscription Payment)
     */
    public function recordCommission($tenantId, $amountPaid)
    {
        $referralCode = \DB::table('tenants')->where('id', $tenantId)->value('referred_by');
        if (!$referralCode) return;

        $affiliate = \DB::table('affiliates')->where('referral_code', $referralCode)->first();
        if (!$affiliate) return;

        $commission = $amountPaid * ($affiliate->commission_rate / 100);

        \DB::table('affiliate_earnings')->insert([
            'affiliate_id' => $affiliate->id,
            'tenant_id' => $tenantId,
            'amount_paid' => $amountPaid,
            'commission_amount' => $commission,
            'status' => 'pending',
            'earned_at' => now(),
        ]);
        
        // Update total balance for the affiliate (Requirement S1)
        \DB::table('affiliates')->where('id', $affiliate->id)->increment('unpaid_balance', $commission);
    }
}
