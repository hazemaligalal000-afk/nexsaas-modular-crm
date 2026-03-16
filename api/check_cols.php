<?php
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");
$res = $mysqli->query("DESCRIBE vtiger_crmentity");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " | ";
}
$mysqli->close();
?>
