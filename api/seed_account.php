<?php
error_reporting(E_ALL);
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");

// Seed an Account
$mysqli->query("CREATE TABLE IF NOT EXISTS vtiger_account (accountid INT PRIMARY KEY, organization_id INT, accountname VARCHAR(255))");
$mysqli->query("INSERT IGNORE INTO vtiger_crmentity (crmid, organization_id, setype, deleted) VALUES (301, 1, 'Accounts', 0)");
$mysqli->query("INSERT IGNORE INTO vtiger_account (accountid, organization_id, accountname) VALUES (301, 1, 'Tesla Inc')");

echo "Account seeded.";
$mysqli->close();
?>
