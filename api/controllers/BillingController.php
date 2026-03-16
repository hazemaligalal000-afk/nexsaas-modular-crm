<?php
/**
 * Integrates Stripe Subscriptions per Tenant Company.
 */

class BillingController {
    protected $adb;

    public function __construct($adb) {
        $this->adb = $adb;
    }

    public function store($data) {
        // Typically Stripe returns a Checkout Session reference or Webhook payload here.
        // E.g. Updating vtiger_organizations plan type.
        
        $org_id = TenantHelper::$current_organization_id;
        $plan_id = $data['stripe_plan_id'];
        
        if (!$org_id || !$plan_id) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid Payload"]);
            return;
        }
        
        // Upgrade the Plan safely isolating using the TenantHelper scope!
        $sql = "UPDATE vtiger_organizations SET plan_type = ? WHERE organization_id = ?";
        $this->adb->pquery($sql, array($plan_id, $org_id));

        echo json_encode(["status" => "success", "message" => "Tenant subscription updated via Stripe."]);
    }
}
?>
