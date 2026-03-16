<?php
/**
 * Enables Automations + Zapier Integration
 * Manages Webhook Subscriptions per SaaS Company
 */

class WebhooksController {
    protected $adb;

    public function __construct($adb) {
        $this->adb = $adb;
    }

    public function index() {
        $org_id = TenantHelper::$current_organization_id;
        $sql = "SELECT id, event_name, endpoint_url, is_active FROM saas_webhooks WHERE organization_id = ?";
        
        $result = $this->adb->pquery($sql, array($org_id));
        $hooks = [];
        while($row = $this->adb->fetch_array($result)) {
            $hooks[] = $row;
        }

        echo json_encode(["status" => "success", "data" => $hooks]);
    }

    public function store($data) {
        $org_id = TenantHelper::$current_organization_id;
        
        // E.g., Event Name: lead.created -> fires to Zapier URL
        $sql = "INSERT INTO saas_webhooks (organization_id, event_name, endpoint_url, is_active) VALUES (?, ?, ?, 1)";
        $this->adb->pquery($sql, array($org_id, $data['event_name'], $data['endpoint_url']));

        echo json_encode(["status" => "success", "message" => "Webhook Registered mapping: " . $data['event_name']]);
    }

    /**
     * Engine Hook Method (Triggered internally upon Save Actions)
     */
    public static function triggerEvent($adb, $org_id, $event_name, $payload) {
        $sql = "SELECT endpoint_url FROM saas_webhooks WHERE organization_id = ? AND event_name = ? AND is_active = 1";
        $result = $adb->pquery($sql, array($org_id, $event_name));
        
        while($row = $adb->fetch_array($result)) {
            $url = $row['endpoint_url'];
            
            // Fire background cURL to Zapier/Make.com
            // Not waiting for response to keep CRM UI instantly snappy.
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
?>
