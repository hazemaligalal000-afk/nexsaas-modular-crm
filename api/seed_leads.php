<?php
error_reporting(E_ALL);
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");

// Seed Leads
$leads = [
    [201, 'Sundar', 'Pichai', 'Google', 'sundar@google.com', '+1-555-0101', 'Hot'],
    [202, 'Mark', 'Zuckerberg', 'Meta', 'mark@meta.com', '+1-555-0102', 'Qualified'],
    [203, 'Satya', 'Nadella', 'Microsoft', 'satya@microsoft.com', '+1-555-0103', 'New'],
    [204, 'Tim', 'Cook', 'Apple', 'tim@apple.com', '+1-555-0104', 'Nurture'],
];

foreach ($leads as $l) {
    $mysqli->query("INSERT IGNORE INTO vtiger_crmentity (crmid, organization_id, setype, deleted) VALUES ({$l[0]}, 1, 'Leads', 0)");
    $sql = "INSERT IGNORE INTO vtiger_leaddetails (leadid, organization_id, firstname, lastname, company, email, phone, leadstatus) 
            VALUES (?, 1, ?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("issssss", $l[0], $l[1], $l[2], $l[3], $l[4], $l[5], $l[6]);
    $stmt->execute();
}

echo "Leads Seeded Successfully.";
$mysqli->close();
?>
