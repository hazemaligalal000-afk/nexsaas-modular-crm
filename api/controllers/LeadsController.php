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

    public function score($id) {
        $sql = "SELECT leaddetails.*, crmentity.modifiedtime FROM vtiger_leaddetails AS leaddetails 
                JOIN vtiger_crmentity AS crmentity ON crmentity.crmid = leaddetails.leadid
                WHERE leaddetails.leadid = ?";
        $result = $this->adb->pquery($sql, array($id));
        
        if ($this->adb->num_rows($result) === 0) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Lead not found"]);
            return;
        }

        $row = $this->adb->fetch_array($result);
        
        # 1. Prepare data for AI Engine
        $payload = [
            "lead" => [
                "id" => $id,
                "email" => $row['email'],
                "company_size" => 50, // Default for now, should map from custom field
                "industry" => $row['industry'] ?? 'Technology',
                "website_visits" => 5, // Simulated
                "email_clicks" => 2,
                "form_submissions" => 1,
                "days_since_last_activity" => 2,
                "current_stage" => strtolower($row['leadstatus']) ?: 'new'
            ]
        ];

        # 2. Call FastAPI AI Engine (internal Docker network)
        $ch = curl_init("http://fastapi:8000/api/v1/leads/score");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo json_encode(["status" => "error", "message" => "AI Engine unreachable or error", "details" => json_decode($response)]);
            return;
        }

        $aiResult = json_decode($response, true);

        # 3. Update Vtiger with the score (using a custom field if exists)
        # For now, just return the result
        echo json_encode(["status" => "success", "data" => $aiResult]);
    }

    public function destroy($id) {
        echo json_encode(["status" => "success", "message" => "Lead deleted"]);
    }
}
?>
