<?php
/**
 * Public/api/index.php
 * Unified Entrypoint Router for the entire SaaS Custom Modular CRM.
 */

require_once '../../core/TenantEnforcer.php';
require_once '../../core/ModuleManager.php';

use Core\TenantEnforcer;
use Core\ModuleManager;

header("Content-Type: application/json");

try {
    // 1. Authenticate Identity & Register Tenant Context globally
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    
    // Authenticate and set the global organization context (JWT-aware)
    TenantEnforcer::initializeFromToken($authHeader);

    // 2. Initialize Module Manager
    // Finds and bootstraps /modules/Leads/, /modules/Deals/, etc.
    $moduleManager = new ModuleManager(realpath(__DIR__ . '/../../modules'));
    $moduleManager->loadActiveModules(TenantEnforcer::getTenantId());

    // 3. Dispatch the API Request cleanly to the respective modular Controller
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    $response = $moduleManager->dispatchApiRequest($method, $uri);

    echo $response;

} catch (\Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
