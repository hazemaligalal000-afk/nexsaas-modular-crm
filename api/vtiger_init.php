<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");

$sqls = [
    // 1. Core Module Registration Table
    "CREATE TABLE IF NOT EXISTS `vtiger_tab` (
        `tabid` int(11) NOT NULL,
        `name` varchar(50) DEFAULT NULL,
        `presence` int(11) DEFAULT '1',
        `tabsequence` int(11) DEFAULT '-1',
        `tablabel` varchar(100) DEFAULT NULL,
        `modifiedby` int(11) DEFAULT NULL,
        `modifiedtime` datetime DEFAULT NULL,
        `customized` int(11) DEFAULT '0',
        `ownedby` int(11) DEFAULT '0',
        `isentitytype` int(11) DEFAULT '1',
        PRIMARY KEY (`tabid`),
        UNIQUE KEY `name` (`name`)
    )",

    // 2. Field Definitions
    "CREATE TABLE IF NOT EXISTS `vtiger_field` (
        `tabid` int(11) NOT NULL,
        `fieldid` int(11) NOT NULL,
        `columnname` varchar(30) NOT NULL,
        `tablename` varchar(100) NOT NULL,
        `generatedtype` int(11) DEFAULT '0',
        `uitype` int(11) DEFAULT '0',
        `fieldname` varchar(30) NOT NULL,
        `fieldlabel` varchar(50) NOT NULL,
        `readonly` int(11) DEFAULT '0',
        `presence` int(11) DEFAULT '2',
        `selected` int(11) DEFAULT '0',
        `maximumlength` int(11) DEFAULT '100',
        `sequence` int(11) DEFAULT NULL,
        `block` int(11) DEFAULT NULL,
        `displaytype` int(11) DEFAULT '1',
        `typeofdata` varchar(100) DEFAULT NULL,
        `quickcreate` int(11) DEFAULT '1',
        `quickcreatesequence` int(11) DEFAULT NULL,
        `info_type` varchar(20) DEFAULT 'BAS',
        `masseditable` int(11) DEFAULT '1',
        PRIMARY KEY (`fieldid`),
        KEY `tabid` (`tabid`),
        KEY `fieldname` (`fieldname`)
    )",

    // 3. Blocks for UI
    "CREATE TABLE IF NOT EXISTS `vtiger_block` (
        `blockid` int(11) NOT NULL,
        `tabid` int(11) NOT NULL,
        `blocklabel` varchar(100) NOT NULL,
        `sequence` int(11) DEFAULT NULL,
        `show_title` int(11) DEFAULT '0',
        `visible` int(11) DEFAULT '0',
        `create_view` int(11) DEFAULT '0',
        `edit_view` int(11) DEFAULT '0',
        `detail_view` int(11) DEFAULT '0',
        `display_status` int(11) DEFAULT '1',
        `iscustom` int(11) DEFAULT '0',
        PRIMARY KEY (`blockid`),
        KEY `tabid` (`tabid`)
    )",

    // 4. Profiles & Roles
    "CREATE TABLE IF NOT EXISTS `vtiger_role` (
        `roleid` varchar(255) PRIMARY KEY,
        `rolename` varchar(200) DEFAULT NULL,
        `parentrole` varchar(255) DEFAULT NULL,
        `depth` int(11) DEFAULT '0'
    )",
    "CREATE TABLE IF NOT EXISTS `vtiger_profile` (
        `profileid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `profilename` varchar(200) DEFAULT NULL,
        `description` text
    )",
    "CREATE TABLE IF NOT EXISTS `vtiger_user2role` (
        `userid` int(11) NOT NULL,
        `roleid` varchar(255) NOT NULL,
        PRIMARY KEY (`userid`)
    )",

    // Standard Modules Seed Data
    "INSERT IGNORE INTO `vtiger_tab` (tabid, name, presence, tabsequence, tablabel, isentitytype) VALUES 
        (1, 'Organizations', 0, 1, 'Organizations', 1),
        (2, 'Contacts', 0, 2, 'Contacts', 1),
        (3, 'Leads', 0, 3, 'Leads', 1),
        (4, 'Potentials', 0, 4, 'Potentials', 1),
        (5, 'HelpDesk', 0, 5, 'Tickets', 1),
        (6, 'Products', 0, 6, 'Products', 1),
        (7, 'Invoice', 0, 7, 'Invoices', 1),
        (8, 'Quotes', 0, 8, 'Quotes', 1),
        (9, 'SalesOrder', 0, 9, 'Sales Orders', 1),
        (10, 'PurchaseOrder', 0, 10, 'Purchase Orders', 1),
        (11, 'Vendors', 0, 11, 'Vendors', 1),
        (12, 'PriceBooks', 0, 12, 'Price Books', 1),
        (13, 'Calendar', 0, 13, 'Calendar', 1),
        (14, 'Documents', 0, 14, 'Documents', 1),
        (15, 'Campaigns', 0, 15, 'Campaigns', 1),
        (16, 'Assets', 0, 16, 'Assets', 1),
        (17, 'Project', 0, 17, 'Projects', 1),
        (18, 'ServiceContracts', 0, 18, 'Service Contracts', 1),
        (19, 'Services', 0, 19, 'Services', 1)",

    // Standard Roles Seed Data
    "INSERT IGNORE INTO `vtiger_role` (roleid, rolename, parentrole, depth) VALUES 
        ('H1', 'Organization', '', 0),
        ('H2', 'CEO', 'H1', 1),
        ('H3', 'Sales Manager', 'H1::H2', 2),
        ('H4', 'Sales Man', 'H1::H2::H3', 3),
        ('H5', 'Support Manager', 'H1::H2', 2),
        ('H6', 'Support Agent', 'H1::H2::H5', 3)"
];

foreach ($sqls as $sql) {
    if (!$mysqli->query($sql)) {
        echo "Error: " . $mysqli->error . "\n";
    }
}

// Map existing users to roles
$mysqli->query("INSERT IGNORE INTO vtiger_user2role (userid, roleid) VALUES (1, 'H2')"); // admin
$mysqli->query("INSERT IGNORE INTO vtiger_user2role (userid, roleid) VALUES (2, 'H2')"); // superadmin

echo "Vtiger Core Schema & standard modules initialized.\n";
$mysqli->close();
?>
