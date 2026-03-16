<?php
error_reporting(E_ALL);
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");

$seeds = [
    // Contacts
    "CREATE TABLE IF NOT EXISTS vtiger_contactdetails (contactid INT PRIMARY KEY, organization_id INT, firstname VARCHAR(255), lastname VARCHAR(255))",
    "INSERT IGNORE INTO vtiger_crmentity (crmid, organization_id, setype, deleted) VALUES (401, 1, 'Contacts', 0)",
    "INSERT IGNORE INTO vtiger_contactdetails (contactid, organization_id, firstname, lastname) VALUES (401, 1, 'Elon', 'Musk')",
    
    // HelpDesk Tickets
    "CREATE TABLE IF NOT EXISTS vtiger_troubletickets (ticketid INT PRIMARY KEY, organization_id INT, title VARCHAR(255), status VARCHAR(50))",
    "INSERT IGNORE INTO vtiger_crmentity (crmid, organization_id, setype, deleted) VALUES (501, 1, 'HelpDesk', 0)",
    "INSERT IGNORE INTO vtiger_troubletickets (ticketid, organization_id, title, status) VALUES (501, 1, 'Starship Landing Pad Issue', 'Open')",

    // Products
    "CREATE TABLE IF NOT EXISTS vtiger_products (productid INT PRIMARY KEY, organization_id INT, productname VARCHAR(255))",
    "INSERT IGNORE INTO vtiger_crmentity (crmid, organization_id, setype, deleted) VALUES (601, 1, 'Products', 0)",
    "INSERT IGNORE INTO vtiger_products (productid, organization_id, productname) VALUES (601, 1, 'Model S Plaid')",
];

foreach ($seeds as $sql) {
    if (!$mysqli->query($sql)) {
        echo "Error: " . $mysqli->error . "\n";
    }
}

echo "Everything Vtiger Seeded.";
$mysqli->close();
?>
