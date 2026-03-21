<?php
/**
 * ModularCore/Modules/Platform/Billing/BillingWebhookController.php
 * Premium Stripe Webhook Orchestrator.
 * Handles specialized events: Tax, Overage, and Dunning.
 */

namespace ModularCore\Modules\Platform\Billing;

use ModularCore\Core\BaseController;
use ModularCore\Modules\Platform\Billing\SubscriptionService;
use Core\AuditLogger;

class BillingWebhookController extends BaseController {
    
    protected $subscriptionService;

    public function __construct() {
        $this->subscriptionService = new SubscriptionService();
    }

    /**
     * POST /api/billing/webhook
     */
    public function handle() {
        $payload = @file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $endpointSecret = getenv('STRIPE_WEBHOOK_SECRET');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            return $this->error("Invalid signature", 400);
        }

        // Fulfilling Requirement 47.7: Global Webhook Handling
        switch ($event->type) {
            case 'customer.subscription.trial_will_end':
                $this->handleTrialEnding($event->data->object);
                break;
            
            case 'invoice.payment_action_required':
                $this->handleActionRequired($event->data->object);
                break;
            
            case 'invoice.upcoming':
                // Fulfilling Requirement 4.3: Inject AI Overage before invoice creation
                $this->injectUsageMeter($event->data->object);
                break;

            case 'customer.tax_id.created':
                // Integration with Stripe Tax
                AuditLogger::log($event->data->object->customer, 'STRIPE', 'TAX_ID_CREATED', 'INFO', 'Global Tax Identity established.', 1);
                break;

            default:
                $this->subscriptionService->handleWebhook($event->toArray());
        }

        return $this->success(['received' => true]);
    }

    /**
     * Phase 4: Handle 14-day trial no CC conversion
     */
    protected function handleTrialEnding($subscription) {
        $tenantId = $subscription->metadata->tenant_id;
        // Trigger dunning sequence (Phase 10) or direct notification
        AuditLogger::log($tenantId, 'SYSTEM', 'TRIAL_ENDING', 'WARNING', '14-day trial expires in 3 days. Upgrading required.', 1);
    }

    /**
     * Inject Metered Usage (AI Overage) into upcoming invoice
     */
    protected function injectUsageMeter($invoice) {
        $tenantId = $invoice->metadata->tenant_id;
        $overageCost = $this->subscriptionService->getAIOverageCost($tenantId);
        
        if ($overageCost > 0) {
            // Create a pending invoice item for the overage
            \Stripe\InvoiceItem::create([
                'customer' => $invoice->customer,
                'amount' => $overageCost * 100, // in cents
                'currency' => 'usd',
                'description' => 'AI Revenue OS - Usage Overage (Excess Tokens)',
                'subscription' => $invoice->subscription,
            ]);
        }
    }

    protected function handleActionRequired($invoice) {
        // Handle 3D Secure / SCA requirements
        AuditLogger::log($invoice->customer, 'STRIPE', 'SCA_REQUIRED', 'CRITICAL', 'Strong Customer Authentication needed for payment.', 1);
    }
}
