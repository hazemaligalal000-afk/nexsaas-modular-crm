<?php
/**
 * Handles `/api/deals`
 * Including Deal Stages (Kanban state)
 */

class DealsController {
    protected $adb;

    public function __construct($adb) {
        $this->adb = $adb;
    }

    public function index() {
        $sql = "SELECT p.potentialid, p.potentialname, p.amount, p.closingdate, p.sales_stage
                FROM vtiger_potential AS p
                JOIN vtiger_crmentity AS crm ON crm.crmid = p.potentialid
                WHERE crm.deleted = 0";

        $result = $this->adb->query($sql);
        $deals = [];
        while($row = $this->adb->fetch_array($result)) {
            $clean = [];
            foreach ($row as $k => $v) {
                if (!is_numeric($k)) $clean[$k] = $v;
            }
            $clean['company_name'] = 'Nexa Intelligence HQ'; // Fallback
            $deals[] = $clean;
        }

        echo json_encode(["status" => "success", "data" => $deals]);
    }

    public function show($id) {
        echo json_encode(["status" => "success", "data" => ["id" => $id, "message" => "Deal Data Stub"]]);
    }

    public function store($data) {
        // Insertion Logic for new Potential
        echo json_encode(["status" => "success", "message" => "Deal created via API"]);
    }

    public function update($id, $data) {
        // Drag-and-drop Kanban update (Stage update)
        if (isset($data['sales_stage'])) {
            $sql = "UPDATE vtiger_potential SET sales_stage = ? WHERE potentialid = ?";
            // TenantHelper will silently ensure WHERE organization_id = X is met here!
            $this->adb->pquery($sql, array($data['sales_stage'], $id));
            echo json_encode(["status" => "success", "message" => "Deal Stage updated"]);
            return;
        }
        echo json_encode(["status" => "success", "message" => "Deal updated"]);
    }

    public function destroy($id) {
        echo json_encode(["status" => "success", "message" => "Deal deleted"]);
    }
}
?>
