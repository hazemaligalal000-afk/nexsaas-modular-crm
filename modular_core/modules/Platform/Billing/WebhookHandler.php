<?php

namespace ModularCore\Modules\Platform\Billing;

use Core\BaseController;
use Core\Database;
use ModularCore\Modules\Platform\Billing\StripeService;
use Exception;

class WebhookHandler extends BaseController
{
    private $stripe;

    public function __construct()
    {
        $this->stripe = new StripeService();
    }

    /**
     * Handle incoming Stripe webhooks POST
     */
    public function handle()
    {
        $payload = file_get_contents('php://input');
        $headers = apache_request_headers();
        $sigHeader = $headers['Stripe-Signature'] ?? '';

        # 1. Verify Webhook Signature
        try {
            $event = $this->stripe->constructEvent($payload, $sigHeader);
        } catch (Exception $e) {
            return $this->respond(null, 'Invalid signature: ' . $e->getMessage(), 400);
        }

        # 2. Prevent Duplicate Events
        if ($this->isEventProcessed($event->id)) {
            return $this->respond(['idempotent' => true]);
        }

        # 3. Route to specific handler
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($event->data->object);
                break;
            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($event->data->object);
                break;
            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event->data->object);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;
        }

        # 4. Mark as processed
        $this->markAsProcessed($event->id, $event->type);

        return $this->respond(['success' => true]);
    }

    private function handleCheckoutCompleted($session)
    {
        $tenantId = $session->metadata->tenant_id;
        $tier = $session->metadata->tier;
        $subscriptionId = $session->subscription;
        
        Database::query(
            "UPDATE tenants SET stripe_subscription_id = ?, stripe_customer_id = ?, current_tier = ?, subscription_status = 'active' WHERE id = ?",
            [$subscriptionId, $session->customer, $tier, $tenantId]
        );
    }

    private function handlePaymentSucceeded($invoice)
    {
        Database::query(
            "UPDATE tenants SET subscription_status = 'active', access_locked_at = NULL WHERE stripe_customer_id = ?",
            [$invoice->customer]
        );
    }

    private function handlePaymentFailed($invoice)
    {
        Database::query(
            "UPDATE tenants SET subscription_status = 'past_due' WHERE stripe_customer_id = ?",
            [$invoice->customer]
        );
    }

    private function handleSubscriptionDeleted($subscription)
    {
        Database::query(
            "UPDATE tenants SET subscription_status = 'canceled', access_locked_at = NOW() WHERE stripe_subscription_id = ?",
            [$subscription->id]
        );
    }

    private function isEventProcessed($eventId) 
    { 
        $stmt = Database::query("SELECT 1 FROM stripe_webhook_history WHERE event_id = ?", [$eventId]);
        return $stmt->fetch() !== false;
    }

    private function markAsProcessed($eventId, $type) 
    { 
        Database::query(
            "INSERT INTO stripe_webhook_history (event_id, event_type, processed_at) VALUES (?, ?, NOW())",
            [$eventId, $type]
        ); 
    }
}
