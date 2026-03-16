<?php
error_reporting(E_ALL);
$m = new mysqli("db", "crm_user", "crm_secret", "crm_db");

// ── Drop existing tables for the subset we are trying to seed to ensure schema is correct ──
$tables_to_drop = [
    'vtiger_account', 'vtiger_contactdetails', 'vtiger_leaddetails', 'vtiger_potential',
    'vtiger_troubletickets', 'vtiger_products', 'vtiger_activity', 'vtiger_notes'
];
foreach ($tables_to_drop as $t) {
    if (!$m->query("DROP TABLE IF EXISTS {$t}")) {
        echo "Error dropping {$t}: " . $m->error . "\n";
    }
}

// ── Re-Create tables with correct schema ──
$creates = [
    "CREATE TABLE vtiger_contactdetails (contactid INT PRIMARY KEY, organization_id INT DEFAULT 1, firstname VARCHAR(255), lastname VARCHAR(255), email VARCHAR(255), phone VARCHAR(50))",
    "CREATE TABLE vtiger_account (accountid INT PRIMARY KEY, organization_id INT DEFAULT 1, accountname VARCHAR(255), website VARCHAR(255), industry VARCHAR(100))",
    "CREATE TABLE vtiger_leaddetails (leadid INT PRIMARY KEY, organization_id INT DEFAULT 1, firstname VARCHAR(255), lastname VARCHAR(255), company VARCHAR(255), email VARCHAR(255), phone VARCHAR(50), leadstatus VARCHAR(50))",
    "CREATE TABLE vtiger_potential (potentialid INT PRIMARY KEY, organization_id INT DEFAULT 1, potentialname VARCHAR(255), amount DECIMAL(14,2), sales_stage VARCHAR(50), closingdate DATE)",
    "CREATE TABLE vtiger_troubletickets (ticketid INT PRIMARY KEY, organization_id INT DEFAULT 1, title VARCHAR(255), status VARCHAR(50), priority VARCHAR(50))",
    "CREATE TABLE vtiger_products (productid INT PRIMARY KEY, organization_id INT DEFAULT 1, productname VARCHAR(255), unit_price DECIMAL(14,2), qty_per_unit INT DEFAULT 1)",
    "CREATE TABLE vtiger_activity (activityid INT PRIMARY KEY, organization_id INT DEFAULT 1, subject VARCHAR(255), activitytype VARCHAR(50), date_start DATE, time_start TIME, status VARCHAR(50))",
    "CREATE TABLE vtiger_notes (notesid INT PRIMARY KEY, organization_id INT DEFAULT 1, title VARCHAR(255), filename VARCHAR(255), filetype VARCHAR(50))",
];
foreach ($creates as $sql) {
    if (!$m->query($sql)) {
         echo "Create Error: " . $m->error . "\n";
    }
}

// Ensure crmentity has organization_id
$check = $m->query("SHOW COLUMNS FROM vtiger_crmentity LIKE 'organization_id'");
if ($check->num_rows == 0) {
    $m->query("ALTER TABLE vtiger_crmentity ADD COLUMN organization_id INT DEFAULT 1");
}

// ── Seed CRM Entity + Module Data ──
$seeds = [
    // Accounts (FAANG)
    [301, 'Accounts', "REPLACE INTO vtiger_account (accountid, organization_id, accountname, website, industry) VALUES (301, 1, 'Tesla Inc', 'tesla.com', 'Automotive')"],
    [302, 'Accounts', "REPLACE INTO vtiger_account (accountid, organization_id, accountname, website, industry) VALUES (302, 1, 'SpaceX', 'spacex.com', 'Aerospace')"],
    [303, 'Accounts', "REPLACE INTO vtiger_account (accountid, organization_id, accountname, website, industry) VALUES (303, 1, 'Alphabet Inc', 'abc.xyz', 'Technology')"],
    [304, 'Accounts', "REPLACE INTO vtiger_account (accountid, organization_id, accountname, website, industry) VALUES (304, 1, 'Meta Platforms', 'meta.com', 'Social Media')"],
    [305, 'Accounts', "REPLACE INTO vtiger_account (accountid, organization_id, accountname, website, industry) VALUES (305, 1, 'Amazon', 'amazon.com', 'E-Commerce')"],

    // Contacts
    [401, 'Contacts', "REPLACE INTO vtiger_contactdetails (contactid, organization_id, firstname, lastname, email, phone) VALUES (401, 1, 'Elon', 'Musk', 'elon@tesla.com', '+1-555-0001')"],
    [402, 'Contacts', "REPLACE INTO vtiger_contactdetails (contactid, organization_id, firstname, lastname, email, phone) VALUES (402, 1, 'Jeff', 'Bezos', 'jeff@amazon.com', '+1-555-0002')"],
    [403, 'Contacts', "REPLACE INTO vtiger_contactdetails (contactid, organization_id, firstname, lastname, email, phone) VALUES (403, 1, 'Sundar', 'Pichai', 'sundar@google.com', '+1-555-0003')"],
    [404, 'Contacts', "REPLACE INTO vtiger_contactdetails (contactid, organization_id, firstname, lastname, email, phone) VALUES (404, 1, 'Satya', 'Nadella', 'satya@microsoft.com', '+1-555-0004')"],

    // Leads
    [201, 'Leads', "REPLACE INTO vtiger_leaddetails (leadid, organization_id, firstname, lastname, company, email, phone, leadstatus) VALUES (201, 1, 'Sundar', 'Pichai', 'Alphabet', 'sundar@google.com', '+1-555-0101', 'Hot')"],
    [202, 'Leads', "REPLACE INTO vtiger_leaddetails (leadid, organization_id, firstname, lastname, company, email, phone, leadstatus) VALUES (202, 1, 'Mark', 'Zuckerberg', 'Meta', 'mark@meta.com', '+1-555-0102', 'Qualified')"],
    [203, 'Leads', "REPLACE INTO vtiger_leaddetails (leadid, organization_id, firstname, lastname, company, email, phone, leadstatus) VALUES (203, 1, 'Tim', 'Cook', 'Apple', 'tim@apple.com', '+1-555-0103', 'New')"],
    [204, 'Leads', "REPLACE INTO vtiger_leaddetails (leadid, organization_id, firstname, lastname, company, email, phone, leadstatus) VALUES (204, 1, 'Jensen', 'Huang', 'NVIDIA', 'jensen@nvidia.com', '+1-555-0104', 'Nurture')"],

    // Deals/Potentials
    [101, 'Potentials', "REPLACE INTO vtiger_potential (potentialid, organization_id, potentialname, amount, sales_stage, closingdate) VALUES (101, 1, 'Netflix Content Delivery AI', 95000, 'Prospecting', '2026-06-01')"],
    [102, 'Potentials', "REPLACE INTO vtiger_potential (potentialid, organization_id, potentialname, amount, sales_stage, closingdate) VALUES (102, 1, 'Alphabet Global Expansion', 1250000, 'Qualification', '2026-07-15')"],
    [103, 'Potentials', "REPLACE INTO vtiger_potential (potentialid, organization_id, potentialname, amount, sales_stage, closingdate) VALUES (103, 1, 'Meta Ad Platform Integration', 850000, 'Needs Analysis', '2026-08-01')"],
    [104, 'Potentials', "REPLACE INTO vtiger_potential (potentialid, organization_id, potentialname, amount, sales_stage, closingdate) VALUES (104, 1, 'Amazon Web Services Migration', 3500000, 'Proposal', '2026-09-30')"],
    [105, 'Potentials', "REPLACE INTO vtiger_potential (potentialid, organization_id, potentialname, amount, sales_stage, closingdate) VALUES (105, 1, 'Apple Supply Chain Optimization', 450000, 'Negotiation', '2026-05-15')"],

    // Support Tickets
    [501, 'HelpDesk', "REPLACE INTO vtiger_troubletickets (ticketid, organization_id, title, status, priority) VALUES (501, 1, 'API Integration Failing', 'Open', 'High')"],
    [502, 'HelpDesk', "REPLACE INTO vtiger_troubletickets (ticketid, organization_id, title, status, priority) VALUES (502, 1, 'Dashboard Loading Slow', 'In Progress', 'Medium')"],
    [503, 'HelpDesk', "REPLACE INTO vtiger_troubletickets (ticketid, organization_id, title, status, priority) VALUES (503, 1, 'Data Export Feature Request', 'Open', 'Low')"],

    // Products
    [601, 'Products', "REPLACE INTO vtiger_products (productid, organization_id, productname, unit_price, qty_per_unit) VALUES (601, 1, 'Nexa CRM Enterprise', 12500, 1)"],
    [602, 'Products', "REPLACE INTO vtiger_products (productid, organization_id, productname, unit_price, qty_per_unit) VALUES (602, 1, 'AI Analytics Add-on', 5000, 1)"],
    [603, 'Products', "REPLACE INTO vtiger_products (productid, organization_id, productname, unit_price, qty_per_unit) VALUES (603, 1, 'Implementation Service', 25000, 1)"],
];

foreach ($seeds as $s) {
    if (!$m->query($s[2])) {
        echo "Seed Error: " . $m->error . "\n";
    }
    
    // Also insert crmentity for this new ID
    $crmid = $s[0];
    $setype = $s[1];
    $m->query("REPLACE INTO vtiger_crmentity (crmid, organization_id, setype, deleted, createdtime, modifiedtime) VALUES ($crmid, 1, '$setype', 0, NOW(), NOW())");
}

echo "✅ All CRM modules seeded successfully with enterprise data.\n";
$m->close();
?>
