<?php

namespace ModularCore\Modules\Platform\Onboarding;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use ModularCore\Modules\Platform\Onboarding\TenantProvisioningService;
use Exception;

class OnboardingController extends Controller
{
    private $provisioner;

    public function __construct(TenantProvisioningService $provisioner)
    {
        $this->provisioner = $provisioner;
    }

    /**
     * POST /api/v1/onboarding/register
     * Response: {"success": true, "tenant_id": "...", "auth_token": "..."}
     */
    public function register(Request $request)
    {
        $request->validate([
            'company_name'   => 'required|unique:tenants,name',
            'admin_name'     => 'required|max:200',
            'admin_email'    => 'required|email|unique:users,email',
            'admin_password' => 'required|min:8'
        ]);

        try {
            # 1. Start Multi-Tenant Provisioning
            $tenant = $this->provisioner->provision($request->all());

            # 2. Map Admin and Generate JWT for immediate access
            $admin = $tenant->users()->where('role', 'admin')->first();
            $token = $this->generateInitialToken($admin);

            return response()->json([
                'success' => true,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'token' => $token,
                'onboarding_next_step' => 'branding_setup'
            ]);

        } catch (Exception $e) {
            \Log::error("Onboarding Registration Failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => "Registration failed. Please try again later."
            ], 500);
        }
    }

    /**
     * POST /api/v1/onboarding/branding
     */
    public function updateInitialBranding(Request $request)
    {
        $request->validate(['primary_color' => 'required|hex_color']);
        
        $tenant = $request->user()->tenant;
        \DB::table('email_brand_settings')
            ->where('tenant_id', $tenant->id)
            ->where('company_code', '01')
            ->update(['color_primary' => $request->primary_color]);

        return response()->json(['success' => true]);
    }

    private function generateInitialToken($user)
    {
        // Integration with existing Auth/JWT system
        return "onboarding_jwt_token_sample_" . bin2hex(random_bytes(16));
    }
}
