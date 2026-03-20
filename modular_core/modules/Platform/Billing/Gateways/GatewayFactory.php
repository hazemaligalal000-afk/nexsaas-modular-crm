<?php

namespace ModularCore\Modules\Platform\Billing\Gateways;

use Exception;

/**
 * Gateway Factory: Dynamic Resolver for Global/Regional Payments (Requirement 737)
 */
class GatewayFactory
{
    /**
     * Requirement 725, 737: Resolve Gateway per Tenant
     */
    public static function make($tenantId, $gatewayName)
    {
        // 1. Fetch encrypted gateway config from DB
        $config = \DB::table('payment_gateways')
            ->where('tenant_id', $tenantId)
            ->where('gateway_name', $gatewayName)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            throw new Exception("Gateway '{$gatewayName}' is not enabled or configured for this tenant.");
        }

        $decryptedConfig = json_decode(decrypt($config->config_json), true);

        // 2. Instantiate correct gateway
        switch ($gatewayName) {
            case 'stripe': return new StripeGateway($decryptedConfig);
            case 'paymob': return new PaymobGateway($decryptedConfig);
            case 'fawry':  return new FawryGateway($decryptedConfig);
            case 'paypal': return new PayPalGateway($decryptedConfig);
            default:
                throw new Exception("Gateway '{$gatewayName}' is not supported.");
        }
    }

    /**
     * Requirement 751: Get Active Gateways per Tenant
     */
    public static function getActiveGateways($tenantId)
    {
        return \DB::table('payment_gateways')
             ->where('tenant_id', $tenantId)
             ->where('is_active', true)
             ->pluck('gateway_name');
    }
}
