<?php
/**
 * Modules/Billing/ApiController.php
 * Handles Stripe Checkout, Subscription Management, and Webhook Processing.
 */

namespace Modules\Billing;

use Core\Database;
use Core\TenantEnforcer;

class ApiController {

    // Stripe API Keys (Production: Load from ENV / Vault)
    private static $stripeSecretKey = '';
    private static $webhookSecret   = '';

    /**
     * POST /api/billing/checkout-session
     * Creates a Stripe Checkout Session for the current tenant.
     */
    public function createCheckoutSession($data) {
        try {
            $tenantId = TenantEnforcer::getTenantId();
            $planTier = $data['plan'] ?? 'starter';

            $priceMap = [
                'starter'    => 'price_starter_monthly_id',
                'growth'     => 'price_growth_monthly_id',
                'agency'     => 'price_agency_monthly_id',
                'enterprise' => 'price_enterprise_monthly_id',
            ];

            if (!isset($priceMap[$planTier])) {
                throw new \Exception("Invalid plan tier: {$planTier}", 400);
            }

            // In production: Use Stripe PHP SDK
            // \Stripe\Stripe::setApiKey(self::$stripeSecretKey);
            // $session = \Stripe\Checkout\Session::create([...]);

            // Mock response for architecture demonstration
            $sessionId = 'cs_test_' . bin2hex(random_bytes(16));

            return json_encode([
                'success'    => true,
                'session_id' => $sessionId,
                'url'        => "https://checkout.stripe.com/pay/{$sessionId}",
                'plan'       => $planTier
            ]);

        } catch (\Exception $e) {
            http_response_code($e->getCode() ?: 500);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/billing/manage
     * Returns a link to the Stripe Customer Portal for plan changes / cancellation.
     */
    public function manageSubscription() {
        try {
            $tenantId = TenantEnforcer::getTenantId();

            // Fetch Stripe Customer ID from the subscriptions table
            $pdo = Database::getCentralConnection();
            $stmt = $pdo->prepare("SELECT stripe_customer_id FROM subscriptions WHERE tenant_id = ? LIMIT 1");
            $stmt->execute([$tenantId]);
            $sub = $stmt->fetch();

            if (!$sub || !$sub['stripe_customer_id']) {
                throw new \Exception("No active subscription found.", 404);
            }

            // In production: \Stripe\BillingPortal\Session::create([...]);
            $portalUrl = "https://billing.stripe.com/session/" . bin2hex(random_bytes(12));

            return json_encode([
                'success' => true,
                'portal_url' => $portalUrl
            ]);

        } catch (\Exception $e) {
            http_response_code($e->getCode() ?: 500);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/webhooks/stripe
     * Handles Stripe Webhook Events for subscription lifecycle management.
     * This endpoint is PUBLIC (no JWT required) but validated via Stripe signature.
     */
    public function handleWebhook($data) {
        try {
            // 1. Validate Stripe Signature (production)
            // $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'];
            // $event = \Stripe\Webhook::constructEvent($rawBody, $sig, self::$webhookSecret);

            $eventType = $data['type'] ?? '';
            $object    = $data['data']['object'] ?? [];

            switch ($eventType) {

                case 'checkout.session.completed':
                    // A new subscription was created successfully
                    $this->activateTenant($object);
                    break;

                case 'invoice.payment_succeeded':
                    // Recurring payment went through — keep tenant active
                    $this->updateSubscriptionPeriod($object);
                    break;

                case 'invoice.payment_failed':
                    // Payment failed — suspend tenant access
                    $this->suspendTenant($object);
                    break;

                case 'customer.subscription.deleted':
                    // Subscription cancelled entirely
                    $this->deactivateTenant($object);
                    break;

                default:
                    // Unhandled event type — log and ignore
                    break;
            }

            return json_encode(['success' => true, 'received' => $eventType]);

        } catch (\Exception $e) {
            http_response_code(400);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ── Private Lifecycle Methods ──

    private function activateTenant($session) {
        $pdo = Database::getCentralConnection();
        $customerId = $session['customer'] ?? '';
        $subscriptionId = $session['subscription'] ?? '';
        $metadata = $session['metadata'] ?? [];
        $tenantId = $metadata['tenant_id'] ?? null;

        if (!$tenantId) return;

        // Upsert subscription record
        $stmt = $pdo->prepare(
            "INSERT INTO subscriptions (id, tenant_id, stripe_customer_id, stripe_subscription_id, plan_tier, current_period_end)
             VALUES (UUID(), ?, ?, ?, 'starter', DATE_ADD(NOW(), INTERVAL 30 DAY))
             ON DUPLICATE KEY UPDATE stripe_customer_id = VALUES(stripe_customer_id), stripe_subscription_id = VALUES(stripe_subscription_id)"
        );
        $stmt->execute([$tenantId, $customerId, $subscriptionId]);

        // Activate the tenant
        $pdo->prepare("UPDATE tenants SET status = 'active' WHERE id = ?")->execute([$tenantId]);
    }

    private function updateSubscriptionPeriod($invoice) {
        $pdo = Database::getCentralConnection();
        $customerId = $invoice['customer'] ?? '';
        $periodEnd  = $invoice['lines']['data'][0]['period']['end'] ?? time();

        $stmt = $pdo->prepare(
            "UPDATE subscriptions SET current_period_end = FROM_UNIXTIME(?) WHERE stripe_customer_id = ?"
        );
        $stmt->execute([$periodEnd, $customerId]);
    }

    private function suspendTenant($invoice) {
        $pdo = Database::getCentralConnection();
        $customerId = $invoice['customer'] ?? '';

        // Find tenant by Stripe customer
        $stmt = $pdo->prepare("SELECT tenant_id FROM subscriptions WHERE stripe_customer_id = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $row = $stmt->fetch();

        if ($row) {
            $pdo->prepare("UPDATE tenants SET status = 'suspended' WHERE id = ?")->execute([$row['tenant_id']]);
        }
    }

    private function deactivateTenant($subscription) {
        $pdo = Database::getCentralConnection();
        $stripeSubId = $subscription['id'] ?? '';

        $stmt = $pdo->prepare("SELECT tenant_id FROM subscriptions WHERE stripe_subscription_id = ? LIMIT 1");
        $stmt->execute([$stripeSubId]);
        $row = $stmt->fetch();

        if ($row) {
            $pdo->prepare("UPDATE tenants SET status = 'suspended' WHERE id = ?")->execute([$row['tenant_id']]);
        }
    }
}
