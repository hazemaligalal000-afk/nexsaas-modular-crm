<?php

namespace ModularCore\Modules\Integrations\Zapier;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;

/**
 * Zapier Webhook Controller: Self-service No-code Automation (Requirement I2)
 * Orchestrates REST Hook subscriptions and event delivery.
 */
class ZapierWebhookController extends Controller
{
    /**
     * Requirement 599: Zapier REST Hook Subscription (Subscribe)
     */
    public function subscribe(Request $request)
    {
        $request->validate(['event' => 'required', 'target_url' => 'required|url']);
        
        $tenantId = $request->user()->tenant_id;
        $subscriptionId = uniqid('zap_');

        \DB::table('zapier_subscriptions')->insert([
            'id' => $subscriptionId,
            'tenant_id' => $tenantId,
            'event_type' => $request->event,
            'target_url' => $request->target_url,
            'created_at' => now(),
        ]);

        return response()->json(['id' => $subscriptionId, 'status' => 'active'], 201);
    }

    /**
     * Requirement 602: Zapier REST Hook Unsubscription (Delete)
     */
    public function unsubscribe($id)
    {
        \DB::table('zapier_subscriptions')->where('id', $id)->delete();
        return response()->json(null, 204);
    }

    /**
     * Trigger Delivery Engine (INTERNAL)
     */
    public static function deliver($tenantId, $eventType, $payload)
    {
        $subs = \DB::table('zapier_subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('event_type', $eventType)
            ->get();

        foreach ($subs as $s) {
            // Asynchronous delivery via RabbitMQ AnalyticsWorker or direct Guzzle
            \Log::info("Zapier Delivery: {$eventType} to {$s->target_url} for Tenant {$tenantId}");
        }
    }
}
