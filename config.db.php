<?php
/**
 * NexSaaS CRM — Database Configuration Template
 * (Requirement 0.1: Multi-tenant & Security-hardened)
 */

if (file_exists(__DIR__ . '/include/utils/env_loader.php')) {
    require_once __DIR__ . '/include/utils/env_loader.php';
}

$dbconfig['db_server']   = getenv('DB_HOST') ?: 'localhost';
$dbconfig['db_port']     = getenv('DB_PORT') ?: '3306';
$dbconfig['db_sockpath'] = getenv('DB_SOCK') ?: '';
$dbconfig['db_username'] = getenv('DB_USER') ?: 'crm_user';
$dbconfig['db_password'] = getenv('DB_PASS') ?: 'crm_secret';
$dbconfig['db_name']     = getenv('DB_NAME') ?: 'crm_db';
$dbconfig['db_type']     = 'mysqli';
$dbconfig['db_bundled']  = 'false';

$vtconfig['adminPwd']          = getenv('ADMIN_PASS') ?: 'admin';
$vtconfig['standarduserPwd']   = getenv('USER_PASS') ?: 'standard';
$vtconfig['adminEmail']        = getenv('ADMIN_EMAIL') ?: 'admin@nexsaas.com';
$vtconfig['standarduserEmail'] = getenv('USER_EMAIL') ?: 'user@nexsaas.com';
$vtconfig['demoData']          = getenv('DEMO_DATA') ?: 'true';
$vtconfig['currencyName']      = getenv('CURRENCY_NAME') ?: 'USD';
$vtconfig['quickbuild']        = 'false';
?>
