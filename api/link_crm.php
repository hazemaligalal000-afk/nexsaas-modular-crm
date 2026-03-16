<?php
error_reporting(E_ALL);
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");

$potentialIds = [101, 102, 103, 104, 105];
foreach ($potentialIds as $id) {
    $mysqli->query("INSERT IGNORE INTO vtiger_crmentity (crmid, organization_id, setype, deleted) VALUES ($id, 1, 'Potentials', 0)");
}

echo "CRM Entity records linked.";
$mysqli->close();
?>
