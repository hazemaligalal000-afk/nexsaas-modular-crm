<?php
/**
 * Public/api/index.php
 * Unified Entrypoint Router for the entire SaaS Custom Modular CRM.
 */

// --- 1. Basic PSR-4 Autoloader Shim ---
spl_autoload_register(function ($class) {
    $prefix = '';
    $base_dir = __DIR__ . '/../../';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

use Core\TenantEnforcer;
use Core\ModuleManager;

// --- 2. Security & CORS ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // 3. Authenticate Identity & Register Tenant Context
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    TenantEnforcer::initializeFromToken($authHeader);

    // 4. Initialize Module Manager
    $moduleManager = new ModuleManager(realpath(__DIR__ . '/../../modules'));
    $moduleManager->loadActiveModules(TenantEnforcer::getTenantId());

    // 5. Dispatch API Request
    // Remove /api/ prefix if present from URI path
    $uri = str_replace('/api/', '/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $method = $_SERVER['REQUEST_METHOD'];

    $response = $moduleManager->dispatchApiRequest($method, $uri);

    echo $response;

} catch (\Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
