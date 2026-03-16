<?php
error_reporting(E_ALL);
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");
if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
    exit();
}

// Add Super Admin User
$sql = "INSERT IGNORE INTO `vtiger_users` (`id`, `user_name`, `organization_id`, `status`, `user_hash`) 
        VALUES (2, 'superadmin', 1, 'Active', 'password123');";

if ($mysqli->query($sql)) {
    echo "Super Admin User Created.\n";
} else {
    echo "Error: " . $mysqli->error . "\n";
}

$mysqli->close();
?>
