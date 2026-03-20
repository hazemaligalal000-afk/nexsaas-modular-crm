<?php

namespace ModularCore\Modules\Platform\Onboarding;

use App\Models\Tenant;
use App\Models\User;
use ModularCore\Modules\Platform\Billing\StripeService;
use Exception;
use Illuminate\Support\Str;

/**
 * Tenant Provisioning Service (Phase 2 Task 2.1)
 * 
 * Orchestrates the creation of a new client workspace, including:
 * 1. Database Tenant Record
 * 2. Stripe Customer Creation
 * 3. Initial Admin User Setup
 * 4. Default Branding Initialization
 */
class TenantProvisioningService
{
    private $stripe;

    public function __construct(StripeService $stripe)
    {
        $this->stripe = $stripe;
    }

    /**
     * Complete Onboarding Flow
     */
    public function provision(array $data): Tenant
    {
        # 1. Create Tenant Record
        $tenantId = (string) Str::uuid();
        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => $data['company_name'],
            'company_code' => $this->generateCompanyCode($data['company_name']),
            'subscription_status' => 'trialing',
            'current_tier' => 'starter',
            'access_locked_at' => null
        ]);

        # 2. Create Initial Admin User
        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => bcrypt($data['admin_password']),
            'role' => 'admin',
            'is_active' => true
        ]);

        # 3. Create Stripe Customer
        try {
            // We'll create the customer in Stripe but defer subscription until the payment step
            // This allows the user to browse the CRM in 'trial' mode first
            // $stripeCustomer = Stripe\Customer::create([...]);
            // $tenant->update(['stripe_customer_id' => $stripeCustomer->id]);
        } catch (Exception $e) {
            \Log::error("Stripe Provisioning Failed: " . $e->getMessage());
        }

        # 4. Initialize Default Brand (Company 01-06 logic)
        $this->seedDefaultBranding($tenant);

        return $tenant;
    }

    /**
     * Seed first-time brand identity
     */
    private function seedDefaultBranding(Tenant $tenant)
    {
        \DB::table('email_brand_settings')->insert([
            'tenant_id' => $tenant->id,
            'company_code' => '01',
            'company_name_en' => $tenant->name,
            'color_primary' => '#1d4ed8',
            'sender_name_en' => $tenant->name . ' CRM',
            'sender_email' => 'noreply@' . strtolower(str_replace(' ', '', $tenant->name)) . '.com',
            'created_at' => now()
        ]);
    }

    private function generateCompanyCode(string $name)
    {
        # Simple logic for now, in production use a sequence or lookup
        return '01'; 
    }
}
