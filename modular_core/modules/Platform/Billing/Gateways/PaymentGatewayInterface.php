<?php

namespace ModularCore\Modules\Platform\Billing\Gateways;

/**
 * Payment Gateway Interface: Abstraction for Global & Regional Providers (Requirement I4)
 * Standardizes charge processing, subscription lifecycle, and webhook verification.
 */
interface PaymentGatewayInterface
{
    /**
     * Requirement 732: createCharge(amount, currency, customerId)
     */
    public function createCharge($amount, $currency, $token);

    /**
     * Requirement 732: createSubscription(priceId, customerId)
     */
    public function startSubscription($customerEmail, $priceId, $metadata = []);

    /**
     * Requirement 768, 770: verifyWebhook(payload, signature)
     */
    public function verifyWebhook($payload, $signature);

    /**
     * Requirement 726: Fetch available payment methods per gateway
     */
    public function getPaymentMethods();
}
