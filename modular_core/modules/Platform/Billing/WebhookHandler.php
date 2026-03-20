<?php

namespace ModularCore\Modules\Platform\Billing;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use ModularCore\Modules\Platform\Billing\StripeService;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class WebhookHandler extends Controller
{
    private $stripe;

    public function __construct(StripeService $stripe)
    {
        $this->stripe = $stripe;
    }

    /**
     * Handle incoming Stripe webhooks POST /api/webhooks/stripe
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        # 1. Verify Webhook Signature
        try {
            $event = $this->stripe->constructEvent($payload, $sigHeader);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        # 2. Prevent Duplicate Events (Idempotency)
        if ($this->isEventProcessed($event->id)) {
            return response()->json(['success' => true, 'idempotent' => true]);
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
            default:
                Log::info("Unhandled Stripe webhook: " . $event->type);
        }

        # 4. Mark as processed
        $this->markAsProcessed($event->id, $event->type);

        return response()->json(['success' => true]);
    }

    private function handleCheckoutCompleted($session)
    {
        $tenantId = $session->metadata->tenant_id;
        $tier = $session->metadata->tier;
        $subscriptionId = $session->subscription;
        
        $tenant = Tenant::find($tenantId);
        if ($tenant) {
            $tenant->update([
                'stripe_subscription_id' => $subscriptionId,
                'stripe_customer_id'     => $session->customer,
                'current_tier'           => $tier,
                'subscription_status'    => 'active',
                'access_locked_at'       => null
            ]);
            Log::info("Subscription activated for Tenant: " . $tenantId);
        }
    }

    private function handlePaymentSucceeded($invoice)
    {
        $tenant = Tenant::where('stripe_customer_id', $invoice->customer)->first();
        if ($tenant) {
            $tenant->update([
                'subscription_status' => 'active',
                'access_locked_at'    => null,
                'grace_period_end'    => null
            ]);
            Log::info("Payment succeeded for Tenant: " . $tenant->id);
        }
    }

    private function handlePaymentFailed($invoice)
    {
        $tenant = Tenant::where('stripe_customer_id', $invoice->customer)->first();
        if ($tenant) {
            $tenant->update([
                'subscription_status' => 'past_due',
                'grace_period_end'    => now()->addDays(7)
            ]);
            Log::warning("Payment failed! Grace period started for Tenant: " . $tenant->id);
            // Trigger Notification Job Here
        }
    }

    private function handleSubscriptionDeleted($subscription)
    {
        $tenant = Tenant::where('stripe_subscription_id', $subscription->id)->first();
        if ($tenant) {
            $tenant->update([
                'subscription_status' => 'canceled',
                'access_locked_at'    => now()
            ]);
            Log::error("Subscription canceled! Tenant locked: " . $tenant->id);
        }
    }

    // --- Helper Methods ---
    private function isEventProcessed($eventId) { return \DB::table('stripe_webhook_history')->where('event_id', $eventId)->exists(); }
    private function markAsProcessed($eventId, $type) { \DB::table('stripe_webhook_history')->insert(['event_id' => $eventId, 'type' => $type, 'processed_at' => now()]); }
}
