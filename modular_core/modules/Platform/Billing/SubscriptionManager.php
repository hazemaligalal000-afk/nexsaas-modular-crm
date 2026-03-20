<?php

namespace ModularCore\Modules\Platform\Billing;

use Stripe\StripeClient;
use Exception;

/**
 * Subscription Manager: Orchestrates the SaaS Billing Lifecycle
 * Handles immediate upgrades, deferred downgrades, and tier enforcement.
 */
class SubscriptionManager
{
    private $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('stripe.secret'));
    }

    /**
     * Requirement 9.2: Tier Upgrade Immediate Effect
     */
    public function upgradeTier($subscriptionId, $newPriceId)
    {
        // Upgrades reflect immediately with proration
        try {
            $subscription = $this->stripe->subscriptions->update($subscriptionId, [
                'items' => [
                    [
                        'id' => $this->stripe->subscriptions->retrieve($subscriptionId)->items->data[0]->id,
                        'price' => $newPriceId,
                    ],
                ],
                'proration_behavior' => 'always_invoice',
            ]);
            return $subscription;
        } catch (Exception $e) {
            \Log::error("Stripe Upgrade Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Requirement 9.3: Tier Downgrade Deferred Effect
     */
    public function downgradeTier($subscriptionId, $newPriceId)
    {
        // Downgrades take effect at the end of the billing cycle
        // Proration is set to none for downgrades by default in this policy
        try {
            $subscription = $this->stripe->subscriptions->update($subscriptionId, [
                'items' => [
                    [
                        'id' => $this->stripe->subscriptions->retrieve($subscriptionId)->items->data[0]->id,
                        'price' => $newPriceId,
                    ],
                ],
                'proration_behavior' => 'none',
                'cancel_at_period_end' => false, // Ensure it sticks
            ]);
            return $subscription;
        } catch (Exception $e) {
            \Log::error("Stripe Downgrade Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Requirement 10.1: Subscription Cancellation
     */
    public function cancelSubscription($subscriptionId, $immediate = false)
    {
        try {
            if ($immediate) {
                // Task 13.6: Immediate Cancellation (Access Locked)
                return $this->stripe->subscriptions->cancel($subscriptionId);
            } else {
                // Task 13.6: Scheduled Cancellation (Access until end of period)
                return $this->stripe->subscriptions->update($subscriptionId, [
                    'cancel_at_period_end' => true,
                ]);
            }
        } catch (Exception $e) {
             \Log::error("Stripe Cancellation Error: " . $e->getMessage());
             throw $e;
        }
    }

    /**
     * Requirement 9.4: Downgrade User Limit Enforcement
     */
    public function checkTierLimits($tenant, $targetTier)
    {
        $tiers = config('stripe.tiers');
        $limit = $tiers[$targetTier]['user_limit'] ?? 1000;
        $currentCount = \DB::table('users')->where('tenant_id', $tenant->id)->count();

        if ($currentCount > $limit) {
            throw new Exception("Tier limit exceeded. This tenant has {$currentCount} users, but the '{$targetTier}' tier only allows {$limit}. Remove users before downgrading.");
        }
        return true;
    }
}
