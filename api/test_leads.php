<?php
require_once '../include/database/PearDatabase.php';
require_once '../include/TenantHelper.php';

$adb = PearDatabase::getInstance();
TenantHelper::setOrganizationId(1);

$sql = "SELECT leaddetails.leadid, leaddetails.firstname, leaddetails.lastname, leaddetails.company,
               leaddetails.email, leaddetails.phone, leaddetails.leadstatus
        FROM vtiger_leaddetails AS leaddetails
        JOIN vtiger_crmentity AS crm ON crm.crmid = leaddetails.leadid
        WHERE crm.deleted = 0";

$result = $adb->query($sql);
$leads = [];
while ($row = $adb->fetch_array($result)) {
    $leads[] = $row;
}

echo "Count: " . count($leads) . "\n";
print_r($leads);
?>
