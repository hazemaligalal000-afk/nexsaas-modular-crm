<?php

namespace ModularCore\Modules\Platform\Onboarding;

use Core\BaseController;
use Core\Database;
use ModularCore\Modules\Platform\Onboarding\TenantProvisioningService;
use Exception;

class OnboardingController extends BaseController
{
    private $provisioner;

    public function __construct()
    {
        // In a real DI system we'd inject this, but for this custom core we'll instantiate
        $this->provisioner = new TenantProvisioningService();
    }

    /**
     * POST /register
     * Response standard: API_Response envelope (via respond method)
     */
    public function register()
    {
        $input = json_decode(file_get_contents("php://input"), true);
        
        # 1. Basic Validation (Requirement 2.5: Descriptive error)
        if (empty($input['company_name'])) return $this->respond(null, "Company name is required", 400);
        if (empty($input['admin_email'])) return $this->respond(null, "Admin email is required", 400);
        if (empty($input['admin_password'])) return $this->respond(null, "Password is required", 400);

        try {
            # 2. Start Multi-Tenant Provisioning
            $tenantData = $this->provisioner->provision($input);

            # 3. Generate JWT for immediate access
            $token = $this->generateInitialToken($tenantData['admin_user_id']);

            return $this->respond([
                'tenant_id' => $tenantData['tenant_id'],
                'tenant_name' => $input['company_name'],
                'token' => $token,
                'onboarding_next_step' => 'branding_setup'
            ]);

        } catch (Exception $e) {
            return $this->respond(null, "Registration failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * POST /branding
     */
    public function updateInitialBranding()
    {
        $input = json_decode(file_get_contents("php://input"), true);
        $primaryColor = $input['primary_color'] ?? '#1d4ed8';
        
        if (!preg_match('/^#[a-f0-9]{6}$/i', $primaryColor)) {
            return $this->respond(null, "Invalid HEX color format", 400);
        }

        try {
            Database::query(
                "UPDATE email_brand_settings SET color_primary = ? WHERE tenant_id = ? AND company_code = '01'",
                [$primaryColor, $this->tenantId]
            );
            return $this->respond(['success' => true]);
        } catch (Exception $e) {
            return $this->respond(null, "Branding update failed", 500);
        }
    }

    private function generateInitialToken($userId)
    {
        // Integration with existing Auth/JWT system
        return "onboarding_jwt_token_sample_" . bin2hex(random_bytes(16));
    }
}
