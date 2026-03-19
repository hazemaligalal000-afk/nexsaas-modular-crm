<?php

namespace NexSaaS\Platform\Billing;

use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

/**
 * Stripe Webhook Handler
 * Processes all Stripe webhook events
 * Requirements: Master Spec - Complete Stripe Integration
 */
class StripeWebhookHandler
{
    private $subscriptionService;
    private $usageMeteringService;
    private $dunningService;
    private $auditService;
    private $webhookSecret;
    
    public function __construct(
        SubscriptionService $subscriptionService,
        UsageMeteringService $usageMeteringService,
        DunningService $dunningService,
        $auditService
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->usageMeteringService = $usageMeteringService;
        $this->dunningService = $dunningService;
        $this->auditService = $auditService;
        $this->webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');
    }
    
    /**
     * Handle incoming webhook
     */
    public function handle(string $payload, string $signature): array
    {
        try {
            // Verify webhook signature
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );
            
            // Log webhook received
            $this->auditService->log([
                'action' => 'stripe_webhook_received',
                'event_type' => $event->type,
                'event_id' => $event->id
            ]);
            
            // Route to appropriate handler
            $result = $this->routeEvent($event);
            
            return [
                'success' => true,
                'event_type' => $event->type,
                'result' => $result
            ];
            
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            $this->auditService->log([
                'action' => 'stripe_webhook_signature_failed',
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Invalid webhook signature');
        } catch (\Exception $e) {
            // Log error
            $this->auditService->log([
                'action' => 'stripe_webhook_error',
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Route event to appropriate handler
     */
    private function routeEvent($event): array
    {
        switch ($event->type) {
            // Subscription events
            case 'customer.subscription.created':
                return $this->handleSubscriptionCreated($event->data->object);
            
            case 'customer.subscription.updated':
                return $this->handleSubscriptionUpdated($event->data->object);
            
            case 'customer.subscription.deleted':
                return $this->handleSubscriptionDeleted($event->data->object);
            
            case 'customer.subscription.trial_will_end':
                return $this->handleTrialWillEnd($event->data->object);
            
            // Payment events
            case 'invoice.payment_succeeded':
                return $this->handlePaymentSucceeded($event->data->object);
            
            case 'invoice.payment_failed':
                return $this->handlePaymentFailed($event->data->object);
            
            case 'invoice.upcoming':
                return $this->handleUpcomingInvoice($event->data->object);
            
            case 'invoice.finalized':
                return $this->handleInvoiceFinalized($event->data->object);
            
            // Customer events
            case 'customer.created':
                return $this->handleCustomerCreated($event->data->object);
            
            case 'customer.updated':
                return $this->handleCustomerUpdated($event->data->object);
            
            case 'customer.deleted':
                return $this->handleCustomerDeleted($event->data->object);
            
            // Payment method events
            case 'payment_method.attached':
                return $this->handlePaymentMethodAttached($event->data->object);
            
            case 'payment_method.detached':
                return $this->handlePaymentMethodDetached($event->data->object);
            
            // Charge events
            case 'charge.succeeded':
                return $this->handleChargeSucceeded($event->data->object);
            
            case 'charge.failed':
                return $this->handleChargeFailed($event->data->object);
            
            case 'charge.refunded':
                return $this->handleChargeRefunded($event->data->object);
            
            // Dispute events
            case 'charge.dispute.created':
                return $this->handleDisputeCreated($event->data->object);
            
            case 'charge.dispute.closed':
                return $this->handleDisputeClosed($event->data->object);
            
            default:
                return ['message' => 'Event type not handled: ' . $event->type];
        }
    }
    
    // ========================================================================
    // SUBSCRIPTION EVENT HANDLERS
    // ========================================================================
    
    private function handleSubscriptionCreated($subscription): array
    {
        $tenantId = $subscription->metadata->tenant_id ?? null;
        
        if (!$tenantId) {
            throw new \Exception('Missing tenant_id in subscription metadata');
        }
        
        // Update tenant subscription status
        $this->subscriptionService->updateTenantSubscription($tenantId, [
            'stripe_subscription_id' => $subscription->id,
            'status' => $subscription->status,
            'plan_id' => $subscription->items->data[0]->price->id,
            'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
            'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
            'trial_end' => $subscription->trial_end ? date('Y-m-d H:i:s', $subscription->trial_end) : null
        ]);
        
        return ['message' => 'Subscription created', 'tenant_id' => $tenantId];
    }
    
    private function handleSubscriptionUpdated($subscription): array
    {
        $tenantId = $subscription->metadata->tenant_id ?? null;
        
        if (!$tenantId) {
            throw new \Exception('Missing tenant_id in subscription metadata');
        }
        
        // Update subscription details
        $this->subscriptionService->updateTenantSubscription($tenantId, [
            'status' => $subscription->status,
            'plan_id' => $subscription->items->data[0]->price->id,
            'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
            'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
            'cancel_at_period_end' => $subscription->cancel_at_period_end
        ]);
        
        return ['message' => 'Subscription updated', 'tenant_id' => $tenantId];
    }
    
    private function handleSubscriptionDeleted($subscription): array
    {
        $tenantId = $subscription->metadata->tenant_id ?? null;
        
        if (!$tenantId) {
            throw new \Exception('Missing tenant_id in subscription metadata');
        }
        
        // Mark subscription as cancelled
        $this->subscriptionService->updateTenantSubscription($tenantId, [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s')
        ]);
        
        // Disable tenant access
        $this->subscriptionService->disableTenantAccess($tenantId);
        
        return ['message' => 'Subscription cancelled', 'tenant_id' => $tenantId];
    }
    
    private function handleTrialWillEnd($subscription): array
    {
        $tenantId = $subscription->metadata->tenant_id ?? null;
        
        if (!$tenantId) {
            return ['message' => 'Missing tenant_id'];
        }
        
        // Send trial ending notification
        $this->subscriptionService->sendTrialEndingNotification($tenantId, $subscription->trial_end);
        
        return ['message' => 'Trial ending notification sent', 'tenant_id' => $tenantId];
    }
    
    // ========================================================================
    // PAYMENT EVENT HANDLERS
    // ========================================================================
    
    private function handlePaymentSucceeded($invoice): array
    {
        $tenantId = $invoice->subscription_details->metadata->tenant_id ?? null;
        
        if (!$tenantId) {
            return ['message' => 'Missing tenant_id'];
        }
        
        // Record successful payment
        $this->subscriptionService->recordPayment($tenantId, [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount_paid / 100,
            'currency' => $invoice->currency,
            'status' => 'succeeded',
            'paid_at' => date('Y-m-d H:i:s', $invoice->status_transitions->paid_at)
        ]);
        
        // Reset dunning attempts
        $this->dunningService->resetAttempts($tenantId);
        
        // Send receipt
        $this->subscriptionService->sendPaymentReceipt($tenantId, $invoice);
        
        return ['message' => 'Payment succeeded', 'tenant_id' => $tenantId];
    }
    
    private function handlePaymentFailed($invoice): array
    {
        $tenantId = $invoice->subscription_details->metadata->tenant_id ?? null;
        
        if (!$tenantId) {
            return ['message' => 'Missing tenant_id'];
        }
        
        // Record failed payment
        $this->subscriptionService->recordPayment($tenantId, [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount_due / 100,
            'currency' => $invoice->currency,
            'status' => 'failed',
            'attempted_at' => date('Y-m-d H:i:s')
        ]);
        
        // Start dunning process
        $this->dunningService->handleFailedPayment($tenantId, $invoice);
        
        return ['message' => 'Payment failed, dunning started', 'tenant_id' => $tenantId];
    }
    
    private function handleUpcomingInvoice($invoice): array
    {
        $tenantId = $invoice->subscription_details->metadata->tenant_id ?? null;
        
        if (!$tenantId) {
            return ['message' => 'Missing tenant_id'];
        }
        
        // Send upcoming invoice notification
        $this->subscriptionService->sendUpcomingInvoiceNotification($tenantId, $invoice);
        
        return ['message' => 'Upcoming invoice notification sent', 'tenant_id' => $tenantId];
    }
    
    private function handleInvoiceFinalized($invoice): array
    {
        $tenantId = $invoice->subscription_details->metadata->tenant_id ?? null;
        
        if (!$tenantId) {
            return ['message' => 'Missing tenant_id'];
        }
        
        // Store invoice details
        $this->subscriptionService->storeInvoice($tenantId, $invoice);
        
        return ['message' => 'Invoice finalized', 'tenant_id' => $tenantId];
    }
    
    // ========================================================================
    // CUSTOMER EVENT HANDLERS
    // ========================================================================
    
    private function handleCustomerCreated($customer): array
    {
        return ['message' => 'Customer created', 'customer_id' => $customer->id];
    }
    
    private function handleCustomerUpdated($customer): array
    {
        return ['message' => 'Customer updated', 'customer_id' => $customer->id];
    }
    
    private function handleCustomerDeleted($customer): array
    {
        return ['message' => 'Customer deleted', 'customer_id' => $customer->id];
    }
    
    // ========================================================================
    // PAYMENT METHOD EVENT HANDLERS
    // ========================================================================
    
    private function handlePaymentMethodAttached($paymentMethod): array
    {
        return ['message' => 'Payment method attached', 'payment_method_id' => $paymentMethod->id];
    }
    
    private function handlePaymentMethodDetached($paymentMethod): array
    {
        return ['message' => 'Payment method detached', 'payment_method_id' => $paymentMethod->id];
    }
    
    // ========================================================================
    // CHARGE EVENT HANDLERS
    // ========================================================================
    
    private function handleChargeSucceeded($charge): array
    {
        return ['message' => 'Charge succeeded', 'charge_id' => $charge->id];
    }
    
    private function handleChargeFailed($charge): array
    {
        return ['message' => 'Charge failed', 'charge_id' => $charge->id];
    }
    
    private function handleChargeRefunded($charge): array
    {
        return ['message' => 'Charge refunded', 'charge_id' => $charge->id, 'amount' => $charge->amount_refunded / 100];
    }
    
    // ========================================================================
    // DISPUTE EVENT HANDLERS
    // ========================================================================
    
    private function handleDisputeCreated($dispute): array
    {
        // Alert admin about dispute
        $this->subscriptionService->alertAdminAboutDispute($dispute);
        
        return ['message' => 'Dispute created', 'dispute_id' => $dispute->id];
    }
    
    private function handleDisputeClosed($dispute): array
    {
        return ['message' => 'Dispute closed', 'dispute_id' => $dispute->id, 'status' => $dispute->status];
    }
}
