<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$m = new mysqli("db", "crm_user", "crm_secret", "crm_db");
if ($m->connect_error) {
    die("Connection failed: " . $m->connect_error);
}

// ── Check if organization_id exists in vtiger_crmentity ──
$check = $m->query("SHOW COLUMNS FROM vtiger_crmentity LIKE 'organization_id'");
if ($check->num_rows == 0) {
    echo "Adding organization_id to vtiger_crmentity...\n";
    $m->query("ALTER TABLE vtiger_crmentity ADD COLUMN organization_id INT DEFAULT 1");
    if ($m->error) { echo "Error adding col: " . $m->error . "\n"; }
}

echo "Running seed script...\n";
require_once "seed_all_modules.php";

echo "Done.\n";
?>
