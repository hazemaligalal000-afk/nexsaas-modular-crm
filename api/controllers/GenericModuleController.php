<?php
/**
 * Generic Module Controller for NexSaaS
 * Handles all Vtiger modules via simple table queries.
 * No dependency on legacy CRMEntity classes.
 */

class GenericModuleController {
    protected $adb;
    protected $module;

    // Map module names to their database tables and primary keys
    private static $moduleTableMap = [
        'Accounts'    => ['table' => 'vtiger_account',        'pk' => 'accountid',   'name_field' => 'accountname'],
        'Contacts'    => ['table' => 'vtiger_contactdetails',  'pk' => 'contactid',   'name_field' => 'lastname'],
        'Leads'       => ['table' => 'vtiger_leaddetails',     'pk' => 'leadid',      'name_field' => 'lastname'],
        'Potentials'  => ['table' => 'vtiger_potential',       'pk' => 'potentialid', 'name_field' => 'potentialname'],
        'HelpDesk'    => ['table' => 'vtiger_troubletickets',  'pk' => 'ticketid',    'name_field' => 'title'],
        'Products'    => ['table' => 'vtiger_products',        'pk' => 'productid',   'name_field' => 'productname'],
        'Calendar'    => ['table' => 'vtiger_activity',        'pk' => 'activityid',  'name_field' => 'subject'],
        'Documents'   => ['table' => 'vtiger_notes',           'pk' => 'notesid',     'name_field' => 'title'],
        'Invoice'     => ['table' => 'vtiger_invoice',         'pk' => 'invoiceid',   'name_field' => 'subject'],
        'Quotes'      => ['table' => 'vtiger_quotes',          'pk' => 'quoteid',     'name_field' => 'subject'],
        'SalesOrder'  => ['table' => 'vtiger_salesorder',      'pk' => 'salesorderid','name_field' => 'subject'],
        'PurchaseOrder'=>['table' => 'vtiger_purchaseorder',   'pk' => 'purchaseorderid','name_field' => 'subject'],
        'Vendors'     => ['table' => 'vtiger_vendor',          'pk' => 'vendorid',    'name_field' => 'vendorname'],
        'Campaigns'   => ['table' => 'vtiger_campaign',        'pk' => 'campaignid',  'name_field' => 'campaignname'],
        'Faq'         => ['table' => 'vtiger_faq',             'pk' => 'id',          'name_field' => 'question'],
    ];

    public function __construct($adb, $module) {
        $this->adb = $adb;
        $this->module = $module;
    }

    private function getTableInfo() {
        return self::$moduleTableMap[$this->module] ?? null;
    }

    public function index() {
        $info = $this->getTableInfo();
        if (!$info) {
            echo json_encode(["status" => "success", "module" => $this->module, "count" => 0, "data" => []]);
            return;
        }

        $table = $info['table'];
        $pk = $info['pk'];

        $sql = "SELECT t.*, crm.crmid, crm.setype, crm.createdtime, crm.modifiedtime
                FROM {$table} AS t
                INNER JOIN vtiger_crmentity AS crm ON crm.crmid = t.{$pk}
                WHERE crm.deleted = 0";

        try {
            $result = $this->adb->query($sql);
            $records = [];
            while($row = $this->adb->fetch_array($result)) {
                // Clean numeric keys
                $clean = [];
                foreach ($row as $k => $v) {
                    if (!is_numeric($k)) {
                        $clean[$k] = $v;
                    }
                }
                $records[] = $clean;
            }
            echo json_encode(["status" => "success", "module" => $this->module, "count" => count($records), "data" => $records]);
        } catch (Exception $e) {
            echo json_encode(["status" => "success", "module" => $this->module, "count" => 0, "data" => []]);
        }
    }

    public function show($id) {
        $info = $this->getTableInfo();
        if (!$info) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Module not configured"]);
            return;
        }

        $table = $info['table'];
        $pk = $info['pk'];

        $sql = "SELECT t.*, crm.crmid, crm.setype, crm.createdtime, crm.modifiedtime
                FROM {$table} AS t
                INNER JOIN vtiger_crmentity AS crm ON crm.crmid = t.{$pk}
                WHERE t.{$pk} = ? AND crm.deleted = 0";

        $result = $this->adb->pquery($sql, [$id]);
        if ($this->adb->num_rows($result) > 0) {
            $row = $this->adb->fetch_array($result);
            $clean = [];
            foreach ($row as $k => $v) {
                if (!is_numeric($k)) $clean[$k] = $v;
            }
            echo json_encode(["status" => "success", "data" => $clean]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Record not found"]);
        }
    }

    public function store($data) {
        echo json_encode(["status" => "success", "message" => "Record creation for {$this->module} coming soon"]);
    }

    public function update($id, $data) {
        echo json_encode(["status" => "success", "message" => "Record update for {$this->module} coming soon"]);
    }

    public function destroy($id) {
        echo json_encode(["status" => "success", "message" => "Record deletion for {$this->module} coming soon"]);
    }
}
?>
