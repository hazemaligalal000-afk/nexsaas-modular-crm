<?php

namespace ModularCore\Modules\Platform\Billing;

use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Exception;

class StripeService
{
    private $stripe;
    private $webhookSecret;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('stripe.secret_key'));
        $this->webhookSecret = config('stripe.webhook_secret');
    }

    /**
     * 1. Create Checkout Session for Subscription
     */
    public function createCheckoutSession(string $customerId, string $priceId, string $tenantId, string $tier): object
    {
        try {
            return $this->stripe->checkout->sessions->create([
                'customer' => $customerId,
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => config('app.url') . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.url') . '/billing/cancel',
                'metadata' => [
                    'tenant_id' => $tenantId,
                    'tier' => $tier
                ],
                'subscription_data' => [
                    'metadata' => [
                        'tenant_id' => $tenantId
                    ]
                ]
            ]);
        } catch (ApiErrorException $e) {
            throw new Exception("Stripe Checkout Error: " . $e->getMessage());
        }
    }

    /**
     * 2. Update Subscription Tier
     */
    public function updateSubscription(string $subscriptionId, string $newPriceId): object
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);
            return $this->stripe->subscriptions->update($subscriptionId, [
                'items' => [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'price' => $newPriceId,
                    ],
                ],
                'proration_behavior' => 'always_invoice',
            ]);
        } catch (ApiErrorException $e) {
            throw new Exception("Stripe Update Error: " . $e->getMessage());
        }
    }

    /**
     * 3. Cancel Subscription
     */
    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd = true): object
    {
        try {
            if ($atPeriodEnd) {
                return $this->stripe->subscriptions->update($subscriptionId, [
                    'cancel_at_period_end' => true
                ]);
            }
            return $this->stripe->subscriptions->cancel($subscriptionId);
        } catch (ApiErrorException $e) {
            throw new Exception("Stripe Cancel Error: " . $e->getMessage());
        }
    }

    /**
     * 4. Verify Webhook Signature
     */
    public function constructEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
    }
}
