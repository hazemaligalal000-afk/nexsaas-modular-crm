<?php

namespace NexSaaS\Platform\Billing;

use Stripe\StripeClient;

/**
 * Trial Service
 * Manages 14-day free trials (no credit card required)
 * Requirements: Master Spec - 14-Day Free Trial
 */
class TrialService
{
    private $stripe;
    private $db;
    private $trialDays = 14;
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->stripe = new StripeClient(getenv('STRIPE_SECRET_KEY'));
    }
    
    /**
     * Start free trial for new tenant
     */
    public function startTrial(int $tenantId, string $email, string $companyName): array
    {
        // Create Stripe customer (no payment method required)
        $customer = $this->stripe->customers->create([
            'email' => $email,
            'name' => $companyName,
            'metadata' => [
                'tenant_id' => $tenantId,
                'trial_started' => date('Y-m-d H:i:s')
            ]
        ]);
        
        // Calculate trial end date
        $trialEnd = strtotime("+{$this->trialDays} days");
        
        // Create subscription with trial
        $subscription = $this->stripe->subscriptions->create([
            'customer' => $customer->id,
            'items' => [
                ['price' => getenv('STRIPE_DEFAULT_PRICE_ID')]
            ],
            'trial_end' => $trialEnd,
            'trial_settings' => [
                'end_behavior' => [
                    'missing_payment_method' => 'pause'
                ]
            ],
            'metadata' => [
                'tenant_id' => $tenantId
            ]
        ]);
        
        // Store trial info in database
        $this->db->insert('tenant_trials', [
            'tenant_id' => $tenantId,
            'stripe_customer_id' => $customer->id,
            'stripe_subscription_id' => $subscription->id,
            'trial_start' => date('Y-m-d H:i:s'),
            'trial_end' => date('Y-m-d H:i:s', $trialEnd),
            'status' => 'active'
        ]);
        
        return [
            'success' => true,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'trial_end' => date('Y-m-d', $trialEnd),
            'days_remaining' => $this->trialDays
        ];
    }
    
    /**
     * Get trial status for tenant
     */
    public function getTrialStatus(int $tenantId): array
    {
        $query = "
            SELECT *
            FROM tenant_trials
            WHERE tenant_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ";
        
        $trial = $this->db->queryOne($query, [$tenantId]);
        
        if (!$trial) {
            return [
                'has_trial' => false,
                'status' => 'no_trial'
            ];
        }
        
        $now = time();
        $trialEnd = strtotime($trial['trial_end']);
        $daysRemaining = max(0, ceil(($trialEnd - $now) / 86400));
        
        return [
            'has_trial' => true,
            'status' => $trial['status'],
            'trial_start' => $trial['trial_start'],
            'trial_end' => $trial['trial_end'],
            'days_remaining' => $daysRemaining,
            'is_expired' => $now > $trialEnd
        ];
    }
    
    /**
     * Convert trial to paid subscription
     */
    public function convertToPaid(int $tenantId, string $paymentMethodId): array
    {
        $trial = $this->getTrialStatus($tenantId);
        
        if (!$trial['has_trial']) {
            throw new \Exception('No active trial found');
        }
        
        // Get Stripe customer ID
        $query = "SELECT stripe_customer_id, stripe_subscription_id FROM tenant_trials WHERE tenant_id = ?";
        $trialData = $this->db->queryOne($query, [$tenantId]);
        
        // Attach payment method to customer
        $this->stripe->paymentMethods->attach(
            $paymentMethodId,
            ['customer' => $trialData['stripe_customer_id']]
        );
        
        // Set as default payment method
        $this->stripe->customers->update(
            $trialData['stripe_customer_id'],
            ['invoice_settings' => ['default_payment_method' => $paymentMethodId]]
        );
        
        // Update subscription to remove trial and start billing
        $subscription = $this->stripe->subscriptions->update(
            $trialData['stripe_subscription_id'],
            [
                'trial_end' => 'now',
                'proration_behavior' => 'none'
            ]
        );
        
        // Update trial status
        $this->db->update('tenant_trials', [
            'status' => 'converted',
            'converted_at' => date('Y-m-d H:i:s')
        ], ['tenant_id' => $tenantId]);
        
        return [
            'success' => true,
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
            'message' => 'Trial converted to paid subscription'
        ];
    }
    
    /**
     * Cancel trial
     */
    public function cancelTrial(int $tenantId): array
    {
        $query = "SELECT stripe_subscription_id FROM tenant_trials WHERE tenant_id = ? AND status = 'active'";
        $trial = $this->db->queryOne($query, [$tenantId]);
        
        if (!$trial) {
            throw new \Exception('No active trial found');
        }
        
        // Cancel Stripe subscription
        $this->stripe->subscriptions->cancel($trial['stripe_subscription_id']);
        
        // Update trial status
        $this->db->update('tenant_trials', [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s')
        ], ['tenant_id' => $tenantId]);
        
        return [
            'success' => true,
            'message' => 'Trial cancelled'
        ];
    }
    
    /**
     * Send trial reminder emails
     */
    public function sendTrialReminders(): array
    {
        // Find trials ending in 3 days
        $query = "
            SELECT tenant_id, trial_end
            FROM tenant_trials
            WHERE status = 'active'
            AND trial_end BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
            AND reminder_sent = 0
        ";
        
        $trials = $this->db->query($query);
        $sent = 0;
        
        foreach ($trials as $trial) {
            // Send reminder email
            $this->sendTrialEndingEmail($trial['tenant_id'], $trial['trial_end']);
            
            // Mark reminder as sent
            $this->db->update('tenant_trials', [
                'reminder_sent' => 1,
                'reminder_sent_at' => date('Y-m-d H:i:s')
            ], ['tenant_id' => $trial['tenant_id']]);
            
            $sent++;
        }
        
        return [
            'reminders_sent' => $sent
        ];
    }
    
    /**
     * Send trial ending email
     */
    private function sendTrialEndingEmail(int $tenantId, string $trialEnd): void
    {
        // Get tenant email
        $query = "SELECT email, company_name FROM tenants WHERE id = ?";
        $tenant = $this->db->queryOne($query, [$tenantId]);
        
        $daysRemaining = ceil((strtotime($trialEnd) - time()) / 86400);
        
        // Email content
        $subject = "Your NexSaaS trial ends in {$daysRemaining} days";
        $body = "
            Hi {$tenant['company_name']},
            
            Your 14-day free trial of NexSaaS ends in {$daysRemaining} days.
            
            To continue using NexSaaS after your trial ends, please add a payment method.
            
            Add Payment Method: https://app.nexsaas.com/billing
            
            Questions? Reply to this email or contact support@nexsaas.com
            
            Best regards,
            The NexSaaS Team
        ";
        
        // Send email (implement your email service)
        mail($tenant['email'], $subject, $body);
    }
    
    /**
     * Extend trial period
     */
    public function extendTrial(int $tenantId, int $additionalDays): array
    {
        $query = "SELECT stripe_subscription_id, trial_end FROM tenant_trials WHERE tenant_id = ? AND status = 'active'";
        $trial = $this->db->queryOne($query, [$tenantId]);
        
        if (!$trial) {
            throw new \Exception('No active trial found');
        }
        
        // Calculate new trial end
        $newTrialEnd = strtotime($trial['trial_end'] . " +{$additionalDays} days");
        
        // Update Stripe subscription
        $this->stripe->subscriptions->update(
            $trial['stripe_subscription_id'],
            ['trial_end' => $newTrialEnd]
        );
        
        // Update database
        $this->db->update('tenant_trials', [
            'trial_end' => date('Y-m-d H:i:s', $newTrialEnd)
        ], ['tenant_id' => $tenantId]);
        
        return [
            'success' => true,
            'new_trial_end' => date('Y-m-d', $newTrialEnd),
            'days_added' => $additionalDays
        ];
    }
}
