<?php
error_reporting(E_ALL);
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");

$columns = [
    "vtiger_leaddetails" => ["firstname", "lastname", "company", "email", "phone", "leadstatus"],
    "vtiger_contactdetails" => ["firstname", "lastname"],
    "vtiger_account" => ["accountname"],
];

foreach ($columns as $table => $cols) {
    foreach ($cols as $col) {
        $check = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($check->num_rows == 0) {
            $mysqli->query("ALTER TABLE `$table` ADD COLUMN `$col` VARCHAR(255)");
            echo "Added $col to $table\n";
        }
    }
}

echo "Migrations completed.";
$mysqli->close();
?>
