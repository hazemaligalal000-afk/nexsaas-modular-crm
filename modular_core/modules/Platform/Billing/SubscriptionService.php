<?php

namespace ModularCore\Modules\Platform\Billing;

use ModularCore\Core\BaseService;

/**
 * Subscription Service
 * 
 * SaaS subscription billing via Stripe
 * Requirements: 28.1, 28.2, 28.3, 28.4, 28.5, 28.6, 28.7
 */
class SubscriptionService extends BaseService
{
    protected $stripe;
    
    public function __construct()
    {
        parent::__construct();
        
        // Initialize Stripe
        $apiKey = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_mock_nexsaas_key';
        \Stripe\Stripe::setApiKey($apiKey);
        $this->stripe = new \Stripe\StripeClient($apiKey);
    }

    /**
     * Requirement 47.3: Calculate AI API Overage
     */
    public function getAIOverageCost(string $tenantId): float {
        global $db;
        $sql = "SELECT SUM(tokens_used) as total FROM ai_usage_audit WHERE tenant_id = ? AND billing_cycle = 'current'";
        $total = $db->getOne($sql, [$tenantId]) ?: 0;
        
        $freeTier = 100000;
        $ratePer1M = 10.0; // $10 per 1M tokens overage
        
        $overage = max(0, $total - $freeTier);
        return ($overage / 1000000) * $ratePer1M;
    }
    
    /**
     * Create Stripe customer on tenant signup
     */
    public function createCustomer(string $tenantId, string $email, string $name): array
    {
        try {
            $customer = $this->stripe->customers->create([
                'email' => $email,
                'name' => $name,
                'metadata' => [
                    'tenant_id' => $tenantId
                ]
            ]);
            
            // Store customer ID in database
            global $db;
            $sql = "UPDATE tenants SET stripe_customer_id = ? WHERE id = ?";
            $db->Execute($sql, [$customer->id, $tenantId]);
            
            return [
                'success' => true,
                'customer_id' => $customer->id
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Attach payment method to customer
     */
    public function attachPaymentMethod(string $tenantId, string $paymentMethodId): array
    {
        try {
            $customerId = $this->getStripeCustomerId($tenantId);
            
            if (!$customerId) {
                return ['success' => false, 'error' => 'Customer not found'];
            }
            
            // Attach payment method
            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customerId
            ]);
            
            // Set as default payment method
            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId
                ]
            ]);
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create subscription
     */
    public function createSubscription(string $tenantId, string $planId, int $seats = 1): array
    {
        try {
            $customerId = $this->getStripeCustomerId($tenantId);
            
            if (!$customerId) {
                return ['success' => false, 'error' => 'Customer not found'];
            }
            
            // Create subscription with Seat-based overage and Global Tax
            $subscription = $this->stripe->subscriptions->create([
                'customer' => $customerId,
                'items' => [
                    ['price' => $planId, 'quantity' => $seats]
                ],
                'automatic_tax' => ['enabled' => true], // Requirement 4.9 Stripe Tax
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'tenant_id' => $tenantId
                ]
            ]);
            
            // Update tenant subscription info
            global $db;
            $sql = "UPDATE tenants SET 
                    stripe_subscription_id = ?,
                    plan_id = ?,
                    plan_seats = ?,
                    subscription_status = ?,
                    subscription_current_period_end = ?
                    WHERE id = ?";
            
            $db->Execute($sql, [
                $subscription->id,
                $planId,
                $seats,
                $subscription->status,
                date('Y-m-d H:i:s', $subscription->current_period_end),
                $tenantId
            ]);
            
            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'status' => $subscription->status
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update subscription (upgrade/downgrade)
     */
    public function updateSubscription(string $tenantId, ?string $newPlanId = null, ?int $newSeats = null): array
    {
        try {
            $subscriptionId = $this->getStripeSubscriptionId($tenantId);
            
            if (!$subscriptionId) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);
            
            $updateData = [];
            
            if ($newPlanId) {
                $updateData['items'] = [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'price' => $newPlanId
                    ]
                ];
            }
            
            if ($newSeats !== null) {
                if (!isset($updateData['items'])) {
                    $updateData['items'] = [
                        [
                            'id' => $subscription->items->data[0]->id,
                            'quantity' => $newSeats
                        ]
                    ];
                } else {
                    $updateData['items'][0]['quantity'] = $newSeats;
                }
            }
            
            if (empty($updateData)) {
                return ['success' => false, 'error' => 'No changes specified'];
            }
            
            $subscription = $this->stripe->subscriptions->update($subscriptionId, $updateData);
            
            // Update database
            global $db;
            $sql = "UPDATE tenants SET ";
            $params = [];
            
            if ($newPlanId) {
                $sql .= "plan_id = ?, ";
                $params[] = $newPlanId;
            }
            
            if ($newSeats !== null) {
                $sql .= "plan_seats = ?, ";
                $params[] = $newSeats;
            }
            
            $sql = rtrim($sql, ', ') . " WHERE id = ?";
            $params[] = $tenantId;
            
            $db->Execute($sql, $params);
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel subscription
     */
    public function cancelSubscription(string $tenantId, bool $immediately = false): array
    {
        try {
            $subscriptionId = $this->getStripeSubscriptionId($tenantId);
            
            if (!$subscriptionId) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            if ($immediately) {
                $this->stripe->subscriptions->cancel($subscriptionId);
                $status = 'canceled';
            } else {
                // Cancel at period end
                $subscription = $this->stripe->subscriptions->update($subscriptionId, [
                    'cancel_at_period_end' => true
                ]);
                $status = 'canceling';
            }
            
            // Update database
            global $db;
            $sql = "UPDATE tenants SET subscription_status = ? WHERE id = ?";
            $db->Execute($sql, [$status, $tenantId]);
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Requirement 47.5: Stripe Customer Portal (Self-Serve)
     */
    public function createCustomerPortalSession(string $tenantId, string $returnUrl) {
        $customerId = $this->getStripeCustomerId($tenantId);
        if (!$customerId) return null;
        
        return $this->stripe->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * Requirement 47.6: Automated Dunning (Wait-for-Success logic)
     */
    public function runDunningCheck(string $tenantId) {
        $sql = "SELECT subscription_status, grace_period_end FROM tenants WHERE id = ?";
        $row = $this->db->GetRow($sql, [$tenantId]);
        
        if ($row['subscription_status'] === 'past_due') {
            // Trigger automatic retry or send dunning email (Phase 10 Roadmap)
            \Core\AuditLogger::log($tenantId, 'SYSTEM', 'DUNNING_CHECK', 'WARNING', "Payment past due. Retry planned.", 0, []);
        }
    }

    /**
     * Handle Stripe webhook
     */
    public function handleWebhook(array $event): array
    {
        try {
            switch ($event['type']) {
                case 'invoice.payment_succeeded':
                    return $this->handlePaymentSucceeded($event['data']['object']);
                    
                case 'invoice.payment_failed':
                    return $this->handlePaymentFailed($event['data']['object']);
                    
                case 'customer.subscription.updated':
                    return $this->handleSubscriptionUpdated($event['data']['object']);
                    
                case 'customer.subscription.deleted':
                    return $this->handleSubscriptionDeleted($event['data']['object']);
                    
                default:
                    return ['success' => true, 'message' => 'Event not handled'];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle successful payment
     * Update status to active within 60s
     */
    protected function handlePaymentSucceeded($invoice): array
    {
        global $db;
        
        $tenantId = $invoice['metadata']['tenant_id'] ?? null;
        
        if (!$tenantId) {
            // Try to get from customer
            $customer = $this->stripe->customers->retrieve($invoice['customer']);
            $tenantId = $customer->metadata['tenant_id'] ?? null;
        }
        
        if (!$tenantId) {
            return ['success' => false, 'error' => 'Tenant ID not found'];
        }
        
        $sql = "UPDATE tenants SET 
                subscription_status = 'active',
                last_payment_date = NOW(),
                grace_period_end = NULL
                WHERE id = ?";
        
        $db->Execute($sql, [$tenantId]);
        
        return ['success' => true];
    }
    
    /**
     * Handle failed payment
     * Set grace period (7 days)
     */
    protected function handlePaymentFailed($invoice): array
    {
        global $db;
        
        $tenantId = $invoice['metadata']['tenant_id'] ?? null;
        
        if (!$tenantId) {
            $customer = $this->stripe->customers->retrieve($invoice['customer']);
            $tenantId = $customer->metadata['tenant_id'] ?? null;
        }
        
        if (!$tenantId) {
            return ['success' => false, 'error' => 'Tenant ID not found'];
        }
        
        // Set 7-day grace period
        $sql = "UPDATE tenants SET 
                subscription_status = 'past_due',
                grace_period_end = NOW() + INTERVAL '7 days'
                WHERE id = ?";
        
        $db->Execute($sql, [$tenantId]);
        
        // Notify Owner
        $notificationService = new \Core\Notifications\NotificationService();
        $notificationService->send(
            $this->getOwnerUserId($tenantId),
            'payment_failed',
            'Your payment has failed. Please update your payment method within 7 days to avoid service interruption.'
        );
        
        return ['success' => true];
    }
    
    /**
     * Handle subscription updated
     */
    protected function handleSubscriptionUpdated($subscription): array
    {
        global $db;
        
        $tenantId = $subscription['metadata']['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return ['success' => false, 'error' => 'Tenant ID not found'];
        }
        
        $sql = "UPDATE tenants SET 
                subscription_status = ?,
                subscription_current_period_end = ?
                WHERE id = ?";
        
        $db->Execute($sql, [
            $subscription['status'],
            date('Y-m-d H:i:s', $subscription['current_period_end']),
            $tenantId
        ]);
        
        return ['success' => true];
    }
    
    /**
     * Handle subscription deleted
     */
    protected function handleSubscriptionDeleted($subscription): array
    {
        global $db;
        
        $tenantId = $subscription['metadata']['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return ['success' => false, 'error' => 'Tenant ID not found'];
        }
        
        $sql = "UPDATE tenants SET subscription_status = 'canceled' WHERE id = ?";
        $db->Execute($sql, [$tenantId]);
        
        return ['success' => true];
    }
    
    /**
     * Check if tenant can access paid features
     */
    public function canAccessPaidFeatures(string $tenantId): bool
    {
        global $db;
        
        $sql = "SELECT subscription_status, grace_period_end, created_at FROM tenants WHERE id = ?";
        $result = $db->Execute($sql, [$tenantId]);
        
        if (!$result || $result->EOF) {
            return false;
        }
        
        $status = $result->fields['subscription_status'];
        $gracePeriodEnd = $result->fields['grace_period_end'];
        $createdAt = $result->fields['created_at'];
        // Requirement 47.1: 14-day free trial (no credit card required)
        $trialPeriodDays = 14;
        $trialEnd = strtotime($createdAt) + ($trialPeriodDays * 86400);
        
        // Requirement 28.5 - Trial nearing end status
        if (time() < $trialEnd && time() > ($trialEnd - 259200) && empty($status)) { // Within 3 days
            $this->triggerTrialReminder($tenantId);
        }

        if (time() < $trialEnd && empty($status)) {
            return true;
        }

        // Active subscription
        if ($status === 'active') {
            return true;
        }
        
        // Past due but within grace period
        if ($status === 'past_due' && $gracePeriodEnd) {
            return strtotime($gracePeriodEnd) > time();
        }

        return false;
    }

    protected function triggerTrialReminder(string $tenantId) {
        // Emit event for automated email dunning (Phase 10)
        \Core\AuditLogger::log($tenantId, 'SYSTEM', 'TRIAL_EXPIRING_SOON', 'WARNING', "14-day trial ending in 72h. Subscription expected.", 1);
    }
    
    /**
     * Check if tenant has reached seat limit
     */
    public function hasReachedSeatLimit(string $tenantId): bool
    {
        global $db;
        
        $sql = "SELECT plan_seats, 
                (SELECT COUNT(*) FROM users WHERE tenant_id = ? AND deleted_at IS NULL) as user_count
                FROM tenants WHERE id = ?";
        
        $result = $db->Execute($sql, [$tenantId, $tenantId]);
        
        if (!$result || $result->EOF) {
            return true;
        }
        
        $planSeats = (int)$result->fields['plan_seats'];
        $userCount = (int)$result->fields['user_count'];
        
        // Requirement 4.2 - Dynamic Seat Overage
        // Instead of a hard-block, we allow excess users and charge $15/seat overage
        if ($userCount > $planSeats) {
            $this->addSeatOverage($tenantId, $userCount - $planSeats);
        }
        
        return false; // Never block, always charge (SaaS growth model)
    }

    protected function addSeatOverage(string $tenantId, int $excessSeatsCount) {
        $customerId = $this->getStripeCustomerId($tenantId);
        if (!$customerId) return;

        // Add 1-time charge for the overage seat before next invoice
        \Stripe\InvoiceItem::create([
            'customer' => $customerId,
            'amount' => 1500 * $excessSeatsCount, // $15 per extra seat
            'currency' => 'usd',
            'description' => "Excess User Seat Overage ({$excessSeatsCount} seats)",
            'metadata' => ['tenant_id' => $tenantId]
        ]);
        
        AuditLogger::log($tenantId, 'BILLING', 'SEAT_OVERAGE_INJECTED', 'INFO', "Created overage charge for {$excessSeatsCount} additional seats.", 1);
    }
    
    /**
     * Get Stripe customer ID for tenant
     */
    protected function getStripeCustomerId(string $tenantId): ?string
    {
        global $db;
        $sql = "SELECT stripe_customer_id FROM tenants WHERE id = ?";
        $result = $db->Execute($sql, [$tenantId]);
        
        return $result && !$result->EOF ? $result->fields['stripe_customer_id'] : null;
    }
    
    /**
     * Get Stripe subscription ID for tenant
     */
    protected function getStripeSubscriptionId(string $tenantId): ?string
    {
        global $db;
        $sql = "SELECT stripe_subscription_id FROM tenants WHERE id = ?";
        $result = $db->Execute($sql, [$tenantId]);
        
        return $result && !$result->EOF ? $result->fields['stripe_subscription_id'] : null;
    }
    
    /**
     * Get owner user ID for tenant
     */
    protected function getOwnerUserId(string $tenantId): ?int
    {
        global $db;
        $sql = "SELECT id FROM users WHERE tenant_id = ? AND role = 'Owner' LIMIT 1";
        $result = $db->Execute($sql, [$tenantId]);
        
        return $result && !$result->EOF ? (int)$result->fields['id'] : null;
    }
}
