<?php
error_reporting(E_ALL);
$mysqli = new mysqli("db", "crm_user", "crm_secret", "crm_db");

$mysqli->query("DELETE FROM vtiger_tab"); // Reset for clean mapping

$standard_modules = [
    [1, 'Accounts', 0, 1, 'Accounts', 1],
    [2, 'Contacts', 0, 2, 'Contacts', 1],
    [3, 'Leads', 0, 3, 'Leads', 1],
    [4, 'Potentials', 0, 4, 'Opportunities', 1],
    [5, 'HelpDesk', 0, 5, 'Tickets', 1],
    [6, 'Products', 0, 6, 'Products', 1],
    [7, 'Invoice', 0, 7, 'Invoices', 1],
    [8, 'Quotes', 0, 8, 'Quotes', 1],
    [9, 'SalesOrder', 0, 9, 'Sales Orders', 1],
    [10, 'PurchaseOrder', 0, 10, 'Purchase Orders', 1],
    [11, 'Vendors', 0, 11, 'Vendors', 1],
    [12, 'PriceBooks', 0, 12, 'Price Books', 1],
    [13, 'Calendar', 0, 13, 'Calendar', 1],
    [14, 'Documents', 0, 14, 'Documents', 1],
    [15, 'Campaigns', 0, 15, 'Campaigns', 1],
    [16, 'Assets', 0, 16, 'Assets', 1],
    [17, 'Project', 0, 17, 'Projects', 1],
    [18, 'ServiceContracts', 0, 18, 'Service Contracts', 1],
    [19, 'Services', 0, 19, 'Services', 1],
    [20, 'Faq', 0, 20, 'FAQ', 1],
];

foreach ($standard_modules as $m) {
    $sql = "INSERT INTO vtiger_tab (tabid, name, presence, tabsequence, tablabel, isentitytype) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("isissi", $m[0], $m[1], $m[2], $m[3], $m[4], $m[5]);
    $stmt->execute();
}

echo "Vtiger standard modules mapped correctly.";
$mysqli->close();
?>
