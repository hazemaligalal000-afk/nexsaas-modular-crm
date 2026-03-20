<?php

namespace ModularCore\Modules\Billing\Gateways;

/**
 * Payment Gateway Interface (I4 Roadmap)
 * 
 * Consistent abstraction for Stripe, PayPal, Paymob, and Fawry.
 */
interface PaymentGatewayInterface
{
    /**
     * Create a one-time charge/payment intent
     */
    public function createCharge($amount, $currency, $description, $metadata = []);

    /**
     * Create a recurring subscription
     */
    public function createSubscription($planId, $customerId, $metadata = []);

    /**
     * Cancel an active subscription
     */
    public function cancelSubscription($subscriptionId);

    /**
     * Validate and process a webhook event
     */
    public function handleWebhook($payload, $signature);

    /**
     * Refund a transaction
     */
    public function refund($transactionId, $amount = null);

    /**
     * Get gateway-specific config requirements (API Keys, Secret, etc.)
     */
    public function getRequiredConfig();
}
