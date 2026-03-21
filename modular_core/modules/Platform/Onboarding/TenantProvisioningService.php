<?php

namespace ModularCore\Modules\Platform\Onboarding;

use Core\Database;
use ModularCore\Modules\Platform\Billing\StripeService;
use Exception;

/**
 * Tenant Provisioning Service (Phase 2 Task 2.1)
 * 
 * Orchestrates the creation of a new client workspace.
 */
class TenantProvisioningService
{
    private $stripe;

    public function __construct()
    {
        // Use the refactored StripeService
        $this->stripe = new StripeService();
    }

    /**
     * Complete Onboarding Flow
     */
    public function provision(array $data): array
    {
        $tenantId = $this->generateUuid();
        
        # 1. Create Tenant Record (Core\Database)
        Database::query(
            "INSERT INTO tenants (id, name, company_code, subscription_status, current_tier, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$tenantId, $data['company_name'], '01', 'trialing', 'starter']
        );

        # 2. Create Initial Admin User
        $userId = $this->generateUuid();
        Database::query(
            "INSERT INTO users (id, tenant_id, name, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$userId, $tenantId, $data['admin_name'], $data['admin_email'], password_hash($data['admin_password'], PASSWORD_BCRYPT), 'admin', 1]
        );

        # 3. Initialize Default Brand
        $this->seedDefaultBranding($tenantId, $data['company_name']);

        return [
            'tenant_id' => $tenantId,
            'admin_user_id' => $userId
        ];
    }

    private function seedDefaultBranding(string $tenantId, string $companyName)
    {
        Database::query(
            "INSERT INTO email_brand_settings (tenant_id, company_code, company_name_en, color_primary, sender_name_en, sender_email, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$tenantId, '01', $companyName, '#1d4ed8', $companyName . ' CRM', 'noreply@example.com']
        );
    }

    private function generateUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
