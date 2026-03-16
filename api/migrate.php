<?php
error_reporting(E_ALL);
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");

$alters = [
    "ALTER TABLE vtiger_leaddetails ADD COLUMN IF NOT EXISTS firstname VARCHAR(255)",
    "ALTER TABLE vtiger_leaddetails ADD COLUMN IF NOT EXISTS lastname VARCHAR(255)",
    "ALTER TABLE vtiger_leaddetails ADD COLUMN IF NOT EXISTS company VARCHAR(255)",
    "ALTER TABLE vtiger_leaddetails ADD COLUMN IF NOT EXISTS email VARCHAR(255)",
    "ALTER TABLE vtiger_leaddetails ADD COLUMN IF NOT EXISTS phone VARCHAR(50)",
    "ALTER TABLE vtiger_leaddetails ADD COLUMN IF NOT EXISTS leadstatus VARCHAR(50)",

    "ALTER TABLE vtiger_contactdetails ADD COLUMN IF NOT EXISTS firstname VARCHAR(255)",
    "ALTER TABLE vtiger_contactdetails ADD COLUMN IF NOT EXISTS lastname VARCHAR(255)",

    "ALTER TABLE vtiger_account ADD COLUMN IF NOT EXISTS accountname VARCHAR(255)",
];

foreach ($alters as $sql) {
    if (!$mysqli->query($sql)) {
        echo "Error: " . $mysqli->error . "\n";
    }
}

echo "Migrations completed.";
$mysqli->close();
?>
