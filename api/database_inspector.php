<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

// Set include path to root so legacy CRM code finds libraries
set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../'));

require_once 'include/database/PearDatabase.php';

$adb = PearDatabase::getInstance();

echo "--- TABLES ---\n";
$res = $adb->query("SHOW TABLES LIKE 'vtiger_%'");
while($row = $adb->fetch_array($res)) {
    echo $row[0] . "\n";
}

echo "\n--- REGISTERED MODULES (vtiger_tab) ---\n";
try {
    $res = $adb->query("SELECT tabid, name, presence FROM vtiger_tab WHERE presence IN (0, 2)");
    if ($res) {
        while($row = $adb->fetch_array($res)) {
            echo "ID: " . $row['tabid'] . " | Name: " . $row['name'] . " | Presence: " . $row['presence'] . "\n";
        }
    } else {
        echo "vtiger_tab table might be empty or query failed.\n";
    }
} catch (Exception $e) {
    echo "vtiger_tab not found or accessible: " . $e->getMessage() . "\n";
}
?>
