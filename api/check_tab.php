<?php
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");
$res = $mysqli->query("SELECT * FROM vtiger_tab");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
$mysqli->close();
?>
