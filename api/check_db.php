<?php
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");
$res = $mysqli->query("SELECT COUNT(*) as cnt FROM vtiger_leaddetails");
$row = $res->fetch_assoc();
echo "Leads count: " . $row['cnt'] . "\n";

$res = $mysqli->query("SELECT * FROM vtiger_leaddetails LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
$mysqli->close();
?>
