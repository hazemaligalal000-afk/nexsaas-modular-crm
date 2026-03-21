<?php
/**
 * SaaS/OnboardingService.php
 * 
 * CORE → ADVANCED: Automated Tenant Self-Service Provisioning
 */

declare(strict_types=1);

namespace Modules\SaaS;

use Core\BaseService;

class OnboardingService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Handle new tenant signup and automated resource provisioning
     * Used by: Main Landing Page Signup Form
     */
    public function onboardTenant(string $companyName, string $adminEmail, string $adminPhone, int $planId): array
    {
        // 1. Create Tenant Record (UUID based)
        $tenantId = bin2hex(random_bytes(16));
        $this->db->Execute(
            "INSERT INTO tenants (tenant_id, company_name, admin_email, status, created_at)
             VALUES (?, ?, ?, 'trialing', NOW())",
            [$tenantId, $companyName, $adminEmail]
        );

        // 2. Initialize Seed Data for the new Tenant (Batch ERP-A)
        // Rule: Create default Company 01, Base Currency EGP/SAR, and COA Shell
        $this->db->Execute(
            "INSERT INTO companies (tenant_id, company_code, name_en, country) VALUES (?, '01', ?, 'EG')",
            [$tenantId, $companyName]
        );

        // 3. Create Default Subscription
        $this->db->Execute(
            "INSERT INTO billing_subscriptions (tenant_id, plan_id, status, start_date, end_date)
             VALUES (?, ?, 'trialing', NOW(), NOW() + INTERVAL '14 days')",
            [$tenantId, $planId]
        );

        // 4. FIRE EVENT: Tenant Created (Triggers welcome email & database migrations)
        // $this->fireEvent('saas.tenant_onboarded', ['tenant_id' => $tenantId, 'email' => $adminEmail]);

        return [
            'tenant_id' => $tenantId,
            'company_id' => '01',
            'status' => 'provisioned',
            'dashboard_url' => 'https://' . $tenantId . '.nexsaas.com/login'
        ];
    }
}
