<?php
error_reporting(E_ALL);
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");
if ($mysqli->connect_errno) {
    die("Connection failed: " . $mysqli->connect_error);
}

// 1. Create Tables
$tables = [
    "CREATE TABLE IF NOT EXISTS `vtiger_leaddetails` (
        `leadid` INT PRIMARY KEY,
        `organization_id` INT,
        `firstname` VARCHAR(255),
        `lastname` VARCHAR(255),
        `company` VARCHAR(255),
        `email` VARCHAR(255),
        `phone` VARCHAR(50),
        `leadstatus` VARCHAR(50)
    )",
    "CREATE TABLE IF NOT EXISTS `vtiger_potential` (
        `potentialid` INT PRIMARY KEY,
        `potentialname` VARCHAR(255) NOT NULL,
        `organization_id` INT,
        `amount` DECIMAL(25,8),
        `closingdate` DATE,
        `sales_stage` VARCHAR(100),
        `probability` DECIMAL(7,3),
        `nextstep` VARCHAR(100)
    )",
    "CREATE TABLE IF NOT EXISTS `vtiger_organizations` (
        `organization_id` INT AUTO_INCREMENT PRIMARY KEY,
        `company_name` VARCHAR(255) NOT NULL,
        `plan_type` VARCHAR(50) DEFAULT 'Trial'
    )",
];

foreach ($tables as $sql) {
    $mysqli->query($sql);
}

// 2. Seed Data for Nexa Intelligence HQ
$orgSql = "INSERT IGNORE INTO vtiger_organizations (organization_id, company_name, plan_type) VALUES (1, 'Nexa Intelligence HQ', 'Enterprise')";
$mysqli->query($orgSql);

// Seed Deals (Potentials)
$deals = [
    [101, 'Alphabet Global Expansion', 1, 1250000, '2026-12-31', 'Qualified', 20],
    [102, 'Meta Ad Platform Integration', 1, 850000, '2026-06-15', 'Demo', 40],
    [103, 'Amazon Web Services Migration', 1, 3500000, '2027-01-01', 'Proposal', 60],
    [104, 'Apple Supply Chain Sync', 1, 450000, '2026-04-20', 'Negotiation', 80],
    [105, 'Netflix Content Delivery AI', 1, 95000, '2026-03-25', 'New Lead', 10],
];

foreach ($deals as $deal) {
    $sql = "INSERT IGNORE INTO vtiger_potential (potentialid, potentialname, organization_id, amount, closingdate, sales_stage, probability) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("isidssd", $deal[0], $deal[1], $deal[2], $deal[3], $deal[4], $deal[5], $deal[6]);
    $stmt->execute();
}

echo "Database Seeded with FAANG-Level Data Successfully.";
$mysqli->close();
?>
