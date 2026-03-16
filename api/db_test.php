<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");
if ($mysqli->connect_error) {
    die("Connect Error (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
}
echo "Connected successfully\n";
$res = $mysqli->query("SHOW TABLES LIKE 'vtiger_tab'");
if ($res->num_rows > 0) {
    echo "vtiger_tab EXISTS\n";
} else {
    echo "vtiger_tab NOT FOUND\n";
}
$mysqli->close();
?>
