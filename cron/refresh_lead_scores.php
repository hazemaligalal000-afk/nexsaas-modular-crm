<?php
/**
 * Cron Job: Refresh all Lead Scores (Phase 1 / 1.1)
 */

require_once(__DIR__ . '/../include/database/PearDatabase.php');
require_once(__DIR__ . '/../api/controllers/LeadsController.php');

$adb = PearDatabase::getInstance();
$controller = new LeadsController($adb);

// 1. Get all active leads
$sql = "SELECT leadid FROM vtiger_leaddetails 
        JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_leaddetails.leadid
        WHERE vtiger_crmentity.deleted = 0";
$result = $adb->query($sql);

echo "Starting AI Score Refresh for " . $adb->num_rows($result) . " leads...\n";

while ($row = $adb->fetch_array($result)) {
    $leadId = $row['leadid'];
    echo "Scoring Lead ID: $leadId... ";
    
    // Capture output to avoid polluting cron logs with raw JSON
    ob_start();
    $controller->score($leadId);
    $response = ob_get_clean();
    
    $data = json_decode($response, true);
    if ($data && $data['status'] === 'success') {
        echo "Score: " . $data['data']['score'] . " ✅\n";
    } else {
        echo "FAILED ❌\n";
    }
}

echo "Refresh Complete.\n";
