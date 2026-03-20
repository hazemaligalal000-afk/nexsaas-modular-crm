<?php

namespace ModularCore\Modules\Platform\Billing;

use Carbon\Carbon;
use Exception;

/**
 * Grace Period Service: Handles Past Due & Payment Retry logic
 */
class GracePeriodService
{
    private $gracePeriodDays = 7; // Requirements 13.1: 7-day grace period

    /**
     * Requirement 11.6: Payment Failure Grace Period Initiation
     */
    public function startGracePeriod($tenantId)
    {
        $graceStart = Carbon::now();
        $graceEnd = $graceStart->copy()->addDays($this->gracePeriodDays);

        \DB::table('tenants')
            ->where('id', $tenantId)
            ->update([
                'subscription_status' => 'past_due',
                'grace_period_start' => $graceStart,
                'grace_period_end' => $graceEnd,
                'access_locked_at' => null, // Requirement: Allow access during grace period
            ]);

        // Logic to send Day 1 Email (Payment Failed)
        $this->notifyPaymentFailed($tenantId, $graceStart);

        return [
            'status' => 'past_due',
            'grace_period_end' => $graceEnd->toDateTimeString(),
        ];
    }

    /**
     * Requirement 13.5: Grace Period Payment Success Recovery
     */
    public function clearGracePeriod($tenantId)
    {
        \DB::table('tenants')
            ->where('id', $tenantId)
            ->update([
                'subscription_status' => 'active',
                'grace_period_start' => null,
                'grace_period_end' => null,
                'access_locked_at' => null,
            ]);
    }

    /**
     * Requirement 13.6: Grace Period Expiration Lockout
     */
    public function lockExpiredTenant($tenantId)
    {
        \DB::table('tenants')
            ->where('id', $tenantId)
            ->update([
                'subscription_status' => 'unpaid',
                'access_locked_at' => Carbon::now(),
            ]);
            
        // Logic to send Access Locked Email
        $this->notifyAccessLocked($tenantId);
    }

    /**
     * Requirement 13.3: Grace Period Email Reminders (Days 1, 3, 5, 7)
     */
    public function notifyPaymentFailed($tenantId, $graceStart)
    {
        // Integration with Email Service for multi-tenant notifications
        \Log::info("Grace Period Reminder: Payment failed for Tenant {$tenantId}. Grace period ends " . $graceStart->copy()->addDays(7)->toFormattedDateString());
    }

    public function notifyAccessLocked($tenantId)
    {
        \Log::warning("Tenant {$tenantId} locked due to unpaid subscription.");
    }
}
