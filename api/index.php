<?php
/**
 * Modern REST API Router for Multi-Tenant SaaS
 * Handles Authentication, Tenant Resolution, and routing.
 */

// 1. Ensure CORS and error reporting are active immediately
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key");
header("Access-Control-Max-Age: 86400");

// Security Headers (Requirement 10.172)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://js.stripe.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self' http://localhost:8000; frame-ancestors 'none';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Production mode: only report errors to prevent JSON pollution
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
ini_set('display_errors', 'Off');

// 2. Set include path to root so legacy CRM code finds libraries
set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../'));

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once('../include/logging.php');
require_once('../include/utils/utils.php');
require_once('../include/database/PearDatabase.php');
require_once('../include/TenantHelper.php');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 3. Simple Router Dispatcher
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Route parsing
$route = str_replace('/api/', '', $uri);
$route_parts = explode('/', trim($route, '/'));
$resource = isset($route_parts[0]) ? strtolower($route_parts[0]) : '';
$id = $route_parts[1] ?? null;

// Allow public access to login endpoint
if ($resource === 'login' && $method === 'POST') {
    require_once 'controllers/AuthController.php';
    $controller = new AuthController(PearDatabase::getInstance());
    $input = json_decode(file_get_contents("php://input"), true);
    $controller->login($input);
    exit;
}

// 1. Authenticate Request via API Key for protected routes
$headers = apache_request_headers();
$api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : null;

if (!$api_key) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Missing X-API-Key header"]);
    exit;
}

$adb = PearDatabase::getInstance();
// Lookup the organization_id associated with this API key
$query = "SELECT organization_id FROM saas_api_keys WHERE api_key = ?";
$result = $adb->pquery($query, array($api_key));

if ($adb->num_rows($result) === 0) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid API Key"]);
    exit;
}

$organization_id = $adb->query_result($result, 0, 'organization_id');

// 2. Register Tenant Context Globally!
TenantHelper::setOrganizationId($organization_id);



// Routing logic for protected resources
switch ($resource) {
    case 'leads':
        require_once 'controllers/LeadsController.php';
        $controller = new LeadsController($adb);
        break;
    case 'deals':
    case 'potentials':
        require_once 'controllers/DealsController.php';
        $controller = new DealsController($adb);
        break;
    case 'analytics':
        require_once 'controllers/AnalyticsController.php';
        $controller = new AnalyticsController($adb);
        break;
    case 'ai':
        require_once 'controllers/AIProxyController.php';
        $controller = new AIProxyController($adb);
        break;
    default:
        // Generic Module Handler for "Everything Vtiger"
        // Convert plural to singular if needed (e.g. accounts -> Accounts)
        $moduleName = ucfirst($resource);
        if ($resource == 'accounts') $moduleName = 'Accounts';
        if ($resource == 'contacts') $moduleName = 'Contacts';
        if ($resource == 'opportunities') $moduleName = 'Potentials';
        if ($resource == 'tickets') $moduleName = 'HelpDesk';

        $tabId = getTabid($moduleName);
        if ($tabId) {
            require_once 'controllers/GenericModuleController.php';
            $controller = new GenericModuleController($adb, $moduleName);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Resource '$resource' (Module: $moduleName) not found"]);
            exit;
        }
        break;
}

// Dispatch
if ($method == 'GET') {
    if ($id) {
        if ($resource === 'leads' && isset($route_parts[2]) && $route_parts[2] === 'score') {
            $controller->score($id);
        } else {
            $controller->show($id);
        }
    } else {
        $controller->index();
    }
} elseif ($method == 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    $controller->store($input);
} elseif ($method == 'PUT') {
    $input = json_decode(file_get_contents("php://input"), true);
    $controller->update($id, $input);
} elseif ($method == 'DELETE') {
    $controller->destroy($id);
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
?>
