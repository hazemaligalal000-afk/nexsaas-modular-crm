<?php
/**
 * NexSaaS CRM — Core Configuration
 * (Requirement 0.1: Multi-tenant & Security-hardened)
 */

if (file_exists(__DIR__ . '/include/utils/env_loader.php')) {
    require_once __DIR__ . '/include/utils/env_loader.php';
}

// ─── DATABASE (Requirement 2.1) ─────────────────────────────────────────────
$dbconfig['db_hostname'] = getenv('DB_HOST') ?: 'localhost';
$dbconfig['db_port']     = getenv('DB_PORT') ?: '3306';
$dbconfig['db_username'] = getenv('DB_USER') ?: 'crm_user';
$dbconfig['db_password'] = getenv('DB_PASS') ?: 'crm_secret';
$dbconfig['db_name']     = getenv('DB_NAME') ?: 'crm_db';
$dbconfig['db_type']     = 'mysqli';

// ─── SITE CONFIGURATION ──────────────────────────────────────────────────────
$site_URL = getenv('APP_URL') ?: 'http://localhost:8080/';
$root_directory = __DIR__ . '/';

// ─── SECURITY ────────────────────────────────────────────────────────────────
$application_unique_key = getenv('APP_KEY') ?: 'nexa_intelligence_secret_key_2026';

// ─── MULTI-TENANCY ──────────────────────────────────────────────────────────
$saas_mode = true;
$default_org_id = 1;

// ─── LOCALIZATION ────────────────────────────────────────────────────────────
$default_language = 'en_us';
$default_charset = 'UTF-8';

// ─── PERFORMANCE ─────────────────────────────────────────────────────────────
$PERFORMANCE_CONFIG = [
    'LOG4PHP_DEBUG'          => getenv('APP_DEBUG') === 'true',
    'SQL_LOG_INCLUDE_CALLER' => false
];

// PHP Global Integration for Vtiger Compatibility
$dbconfigoption['db_server']   = $dbconfig['db_hostname'];
$dbconfigoption['db_user']     = $dbconfig['db_username'];
$dbconfigoption['db_password'] = $dbconfig['db_password'];
$dbconfigoption['db_name']     = $dbconfig['db_name'];
$host_name                     = $dbconfig['db_hostname'];

?>
