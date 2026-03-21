<?php
/**
 * Auth/SSOService.php
 * 
 * CORE → ADVANCED: Enterprise Identity Federation & SSO (Phase 5)
 */

declare(strict_types=1);

namespace Modules\Auth;

use Core\BaseService;

class SSOService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Map an external OAuth2/SAML identity to a local NexSaaS user
     * Providers: 'google', 'microsoft', 'saml-custom'
     */
    public function resolveUser(string $provider, string $externalId, string $email): int
    {
        // 1. Fetch persistent mapping for external identity
        $sql = "SELECT user_id FROM user_sso_mappings 
                WHERE provider = ? AND external_id = ?";
        
        $userId = $this->db->GetOne($sql, [$provider, $externalId]);

        if (!$userId) {
            // Logic: Automated User Provisioning (JIT - Just In Time)
            // Rule: Identify tenant based on email domain (If enabled for the tenant)
            // $this->fireEvent('auth.jit_provisioning', ['email' => $email]);
        }

        return (int)$userId;
    }

    /**
     * Enable/Disable SSO provider for a specific tenant
     */
    public function configureTenantSSO(string $tenantId, string $provider, array $config): void
    {
        $this->db->AutoExecute('tenant_sso_config', [
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'config' => json_encode($config),
            'is_active' => true
        ], 'INSERT');
    }
}
