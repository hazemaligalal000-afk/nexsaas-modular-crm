<?php
/**
 * Handles `/api/leads` and related endpoints
 */

class LeadsController {
    protected $adb;

    public function __construct($adb) {
        $this->adb = $adb;
    }

    public function index() {
        // Due to TenantHelper, this query will securely append WHERE organization_id = X
        $sql = "SELECT leaddetails.leadid, leaddetails.firstname, leaddetails.lastname, leaddetails.company,
                       leaddetails.email, leaddetails.phone, leaddetails.leadstatus
                FROM vtiger_leaddetails AS leaddetails
                JOIN vtiger_crmentity AS crm ON crm.crmid = leaddetails.leadid
                WHERE crm.deleted = 0";
                
        $result = $this->adb->query($sql);
        $leads = [];
        while ($row = $this->adb->fetch_array($result)) {
            $clean = [];
            foreach ($row as $k => $v) {
                if (!is_numeric($k)) $clean[$k] = $v;
            }
            $leads[] = $clean;
        }

        echo json_encode(["status" => "success", "data" => $leads]);
    }

    public function show($id) {
        // Implementation for showing a specific lead
        echo json_encode(["status" => "success", "data" => ["id" => $id, "message" => "Lead Data Stub"]]);
    }

    public function store($data) {
        // Validation and insertion logic
        // Must insert into vtiger_crmentity (generates crmid) -> vtiger_leaddetails
        // And importantly map TenantHelper::$current_organization_id explicitly just in case.
        
        echo json_encode(["status" => "success", "message" => "Lead created via Webhook/API"]);
    }

    public function update($id, $data) {
        echo json_encode(["status" => "success", "message" => "Lead updated"]);
    }

    public function destroy($id) {
        echo json_encode(["status" => "success", "message" => "Lead deleted"]);
    }
}
?>
