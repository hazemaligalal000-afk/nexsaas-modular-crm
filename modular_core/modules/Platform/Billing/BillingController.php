<?php

namespace ModularCore\Modules\Platform\Billing;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use ModularCore\Modules\Platform\Billing\StripeService;
use Exception;

class BillingController extends Controller
{
    private $stripe;

    public function __construct(StripeService $stripe)
    {
        $this->stripe = $stripe;
    }

    /**
     * POST /api/v1/billing/checkout
     * Response: {"checkout_url": "...", "session_id": "..."}
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'tier' => 'required|in:starter,growth,enterprise',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url'
        ]);

        $tenant = $request->user()->tenant;
        $tier = $request->tier;
        $priceId = $this->getPriceIdForTier($tier);

        try {
            // 1. Ensure tenant has a stripe_customer_id
            if (!$tenant->stripe_customer_id) {
                $customer = \Stripe\Customer::create([
                    'email' => $request->user()->email,
                    'metadata' => ['tenant_id' => $tenant->id]
                ]);
                $tenant->update(['stripe_customer_id' => $customer->id]);
            }

            // 2. Create Checkout Session
            $session = $this->stripe->createCheckoutSession(
                $tenant->stripe_customer_id,
                $priceId,
                $tenant->id,
                $tier
            );

            return response()->json([
                'success' => true,
                'checkout_url' => $session->url,
                'session_id' => $session->id
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/v1/billing/change-tier
     */
    public function changeTier(Request $request)
    {
        $request->validate(['new_tier' => 'required|in:starter,growth,enterprise']);
        $tenant = $request->user()->tenant;
        $newPriceId = $this->getPriceIdForTier($request->new_tier);

        try {
            $subscription = $this->stripe->updateSubscription($tenant->stripe_subscription_id, $newPriceId);
            $tenant->update(['current_tier' => $request->new_tier]);

            return response()->json([
                'success' => true,
                'new_tier' => $request->new_tier,
                'status' => $subscription->status
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function getPriceIdForTier($tier)
    {
        $map = [
            'starter'    => env('STRIPE_PRICE_STARTER', 'price_starter_mock'),
            'growth'     => env('STRIPE_PRICE_GROWTH', 'price_growth_mock'),
            'enterprise' => env('STRIPE_PRICE_ENTERPRISE', 'price_enterprise_mock')
        ];
        return $map[$tier];
    }
}
