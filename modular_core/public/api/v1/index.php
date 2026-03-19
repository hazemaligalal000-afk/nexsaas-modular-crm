<?php
/**
 * public/api/v1/index.php
 *
 * Front-controller for the v1 REST API.
 *
 * - Reads routes from ModuleRegistry (module.json files)
 * - Dispatches to the correct controller::method
 * - Handles JWT validation for protected routes
 * - Injects tenant/user context into controllers
 *
 * Public routes (no JWT required):
 *   POST /api/v1/auth/login
 *   POST /api/v1/auth/refresh
 *
 * Protected routes require a valid Bearer JWT.
 */

declare(strict_types=1);

// PSR-4 autoloader
spl_autoload_register(function (string $class): void {
    $baseDir = __DIR__ . '/../../../';
    $file    = $baseDir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Composer autoload (firebase/php-jwt, etc.)
$composerAutoload = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

use Bootstrap\ModuleRegistry;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// -------------------------------------------------------------------------
// CORS + headers
// -------------------------------------------------------------------------
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Forwarded-For, X-Real-IP');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// -------------------------------------------------------------------------
// Bootstrap modules and collect routes
// -------------------------------------------------------------------------
$modulesDir = realpath(__DIR__ . '/../../../modules');
$registry   = new ModuleRegistry($modulesDir);
$registry->bootstrap();

$allRoutes = $registry->getAllRoutes(); // keyed by module name

// Flatten into a single list
$routes = [];
foreach ($allRoutes as $moduleRoutes) {
    foreach ($moduleRoutes as $route) {
        $routes[] = $route;
    }
}

// -------------------------------------------------------------------------
// Match incoming request
// -------------------------------------------------------------------------
$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalise: strip /api/v1 prefix if present
$path = preg_replace('#^/api/v1#', '', $requestUri);
$path = '/' . ltrim($path, '/');

$matchedRoute  = null;
$routeParams   = [];

foreach ($routes as $route) {
    if (strtoupper($route['method']) !== strtoupper($method)) {
        continue;
    }

    // Convert route path params like {provider} to regex capture groups
    $pattern = preg_replace('#\{[^}]+\}#', '([^/]+)', $route['path']);
    // Strip /api/v1 prefix from route path for matching
    $pattern = preg_replace('#^/api/v1#', '', $pattern);
    $pattern = '#^' . $pattern . '$#';

    if (preg_match($pattern, $path, $matches)) {
        $matchedRoute = $route;
        array_shift($matches); // remove full match
        $routeParams  = $matches;
        break;
    }
}

if ($matchedRoute === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint not found.']);
    exit;
}

// -------------------------------------------------------------------------
// Public routes — no JWT required
// -------------------------------------------------------------------------
$publicRoutes = [
    'POST /api/v1/auth/login',
    'POST /api/v1/auth/refresh',
    'POST /api/v1/crm/leads/capture',   // Req 7.2: public web-to-lead capture endpoint
    'GET /api/v1/crm/inbox/canned-responses/public', // Req 12.8: widget canned responses (no auth)
    'GET /api/v1/email/track/{token}/open.gif', // Req 13.4: tracking pixel (no auth)
    'GET /api/v1/email/track/{token}/click',    // Req 13.4: click redirect (no auth)
];

$routeKey    = strtoupper($method) . ' ' . $matchedRoute['path'];
$isPublic    = in_array($routeKey, $publicRoutes, true);

// -------------------------------------------------------------------------
// JWT validation for protected routes
// -------------------------------------------------------------------------
$jwtPayload = null;

if (!$isPublic) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authorization header required.']);
        exit;
    }

    $token      = substr($authHeader, 7);
    $pubKeyPath = __DIR__ . '/../../../keys/jwt_public.pem';

    if (!file_exists($pubKeyPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'JWT public key not configured.']);
        exit;
    }

    try {
        $pubKey     = file_get_contents($pubKeyPath);
        $jwtPayload = JWT::decode($token, new Key($pubKey, 'RS256'));
    } catch (\Exception $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired token.']);
        exit;
    }
}

// -------------------------------------------------------------------------
// Instantiate controller and inject context
// -------------------------------------------------------------------------
[$controllerClass, $methodName] = explode('::', $matchedRoute['handler']);

if (!class_exists($controllerClass)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Controller '{$controllerClass}' not found."]);
    exit;
}

// Build dependencies for known controllers
$controller = buildController($controllerClass, $jwtPayload);

if ($jwtPayload !== null) {
    $controller->setTenantId((string) ($jwtPayload->tenant_id ?? ''));
    $controller->setUserId((string) ($jwtPayload->sub ?? ''));
    $controller->setCompanyCode((string) ($jwtPayload->company_code ?? '01'));
}

// -------------------------------------------------------------------------
// Dispatch
// -------------------------------------------------------------------------
$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$headers = getallheaders() ?: [];

if (!method_exists($controller, $methodName)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => "Method '{$methodName}' not found."]);
    exit;
}

/** @var \Core\Response $response */
$response = dispatchMethod($controller, $methodName, $body, $headers, $routeParams, $_GET);

http_response_code($response->status);
echo json_encode($response->body);

// -------------------------------------------------------------------------
// Controller factory
// -------------------------------------------------------------------------

/**
 * Smart dispatcher: uses reflection to match controller method parameters
 * against the available sources (route params, request body, query string).
 *
 * Parameter resolution order:
 *   1. Route path params (e.g. {id}) — cast to declared type
 *   2. Request body (array) — passed when param is named $body or typed array
 *   3. Query string (array) — passed when param is named $queryParams
 *   4. Headers (array) — passed when param is named $headers
 *
 * @param  object $controller
 * @param  string $method
 * @param  array  $body         Decoded JSON request body
 * @param  array  $headers      HTTP request headers
 * @param  array  $routeParams  Positional path param values (strings)
 * @param  array  $queryParams  Parsed query string ($_GET)
 * @return \Core\Response
 */
function dispatchMethod(
    object $controller,
    string $method,
    array  $body,
    array  $headers,
    array  $routeParams,
    array  $queryParams
): \Core\Response {
    $ref    = new \ReflectionMethod($controller, $method);
    $params = $ref->getParameters();
    $args   = [];
    $routeIdx = 0;

    foreach ($params as $param) {
        $name = $param->getName();
        $type = $param->getType();
        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : '';

        // Route path params (int/string scalars that aren't body/headers/queryParams)
        if (!in_array($name, ['body', 'headers', 'queryParams'], true)
            && in_array($typeName, ['int', 'string', ''], true)
            && isset($routeParams[$routeIdx])
        ) {
            $val = $routeParams[$routeIdx++];
            $args[] = $typeName === 'int' ? (int) $val : (string) $val;
            continue;
        }

        // Body array
        if ($name === 'body' || ($typeName === 'array' && $name !== 'headers' && $name !== 'queryParams')) {
            $args[] = $body;
            continue;
        }

        // Query params
        if ($name === 'queryParams') {
            $args[] = $queryParams;
            continue;
        }

        // Headers
        if ($name === 'headers') {
            $args[] = $headers;
            continue;
        }

        // Default / optional
        if ($param->isOptional()) {
            $args[] = $param->getDefaultValue();
        } else {
            $args[] = null;
        }
    }

    return $ref->invokeArgs($controller, $args);
}

/**
 * Build a controller instance with its required dependencies.
 *
 * @param  string      $class
 * @param  object|null $jwtPayload
 * @return object
 */
function buildController(string $class, ?object $jwtPayload): object
{
    switch ($class) {
        case 'Platform\\Auth\\AuthController':
            return buildAuthController();

        case 'Platform\\Auth\\SSOController':
            return buildSSOController();

        case 'Platform\\Auth\\TwoFactorController':
            return buildTwoFactorController();

        case 'CRM\\Contacts\\ContactController':
            return buildContactController($jwtPayload);

        case 'CRM\\Leads\\LeadController':
            return buildLeadController($jwtPayload);

        case 'CRM\\Accounts\\AccountController':
            return buildAccountController($jwtPayload);

        case 'CRM\\Deals\\DealController':
        case 'CRM\\Pipeline\\DealController':
            return buildPipelineDealController($jwtPayload);

        case 'CRM\\Pipeline\\PipelineController':
            return buildPipelineController($jwtPayload);

        case 'CRM\\Inbox\\InboxController':
            return buildInboxController($jwtPayload);

        case 'CRM\\Inbox\\CannedResponseController':
            return buildCannedResponseController($jwtPayload);

        case 'CRM\\Email\\MailboxController':
            return buildMailboxController($jwtPayload);

        case 'CRM\\Workflows\\WorkflowController':
            return buildWorkflowController($jwtPayload);

        default:
            // Attempt no-arg construction for simple controllers
            return new $class();
    }
}

function buildAuthController(): \Platform\Auth\AuthController
{
    $redis = buildRedis();
    $db    = buildDb();

    $authService = new \Platform\Auth\AuthService($db, $redis);
    return new \Platform\Auth\AuthController($authService);
}

function buildSSOController(): \Platform\Auth\SSOController
{
    $redis = buildRedis();
    $db    = buildDb();

    $ssoService = new \Platform\Auth\SSOService($db, $redis);
    return new \Platform\Auth\SSOController($ssoService);
}

function buildTwoFactorController(): object
{
    // Placeholder — TwoFactorController built in task 5.3
    throw new \RuntimeException('TwoFactorController not yet implemented.');
}

function buildLeadController(?object $jwtPayload): \CRM\Leads\LeadController
{
    $db    = buildDb();

    $tenantId    = (string) ($jwtPayload->tenant_id    ?? '');
    $companyCode = (string) ($jwtPayload->company_code ?? '01');

    // RabbitMQ stub — replace with real AMQPStreamConnection in production
    $rabbitMQ = new class {
        public function publish(string $exchange, string $event, array $payload): void
        {
            // no-op stub; real implementation uses php-amqplib
        }
    };

    $service     = new \CRM\Leads\LeadService($db, $rabbitMQ, $tenantId, $companyCode);
    $formBuilder = new \CRM\Leads\LeadFormBuilder();

    return new \CRM\Leads\LeadController($service, $formBuilder);
}

function buildContactController(?object $jwtPayload): \CRM\Contacts\ContactController
{
    $db          = buildDb();
    $tenantId    = (string) ($jwtPayload->tenant_id    ?? '');
    $companyCode = (string) ($jwtPayload->company_code ?? '01');

    $service = new \CRM\Contacts\ContactService($db, $tenantId, $companyCode);
    return new \CRM\Contacts\ContactController($service);
}

function buildAccountController(?object $jwtPayload): \CRM\Accounts\AccountController
{
    $db          = buildDb();
    $tenantId    = (string) ($jwtPayload->tenant_id    ?? '');
    $companyCode = (string) ($jwtPayload->company_code ?? '01');

    $service = new \CRM\Accounts\AccountService($db, $tenantId, $companyCode);
    return new \CRM\Accounts\AccountController($service);
}

function buildPipelineDealController(?object $jwtPayload): \CRM\Pipeline\DealController
{
    $db          = buildDb();
    $tenantId    = (string) ($jwtPayload->tenant_id    ?? '');
    $companyCode = (string) ($jwtPayload->company_code ?? '01');

    $rabbitMQ = new class {
        public function publish(string $exchange, string $event, array $payload): void {}
    };

    $service = new \CRM\Deals\DealService($db, $rabbitMQ, $tenantId, $companyCode);
    return new \CRM\Pipeline\DealController($service);
}

function buildPipelineController(?object $jwtPayload): \CRM\Pipeline\PipelineController
{
    $db          = buildDb();
    $tenantId    = (string) ($jwtPayload->tenant_id    ?? '');
    $companyCode = (string) ($jwtPayload->company_code ?? '01');

    $rabbitMQ = new class {
        public function publish(string $exchange, string $event, array $payload): void {}
    };

    $pipelineService = new \CRM\Deals\PipelineService($db, $tenantId, $companyCode);
    $dealService     = new \CRM\Deals\DealService($db, $rabbitMQ, $tenantId, $companyCode);
    return new \CRM\Pipeline\PipelineController($pipelineService, $dealService);
}

function buildDealController(?object $jwtPayload): \CRM\Pipeline\DealController
{
    return buildPipelineDealController($jwtPayload);
}

function buildInboxController(?object $jwtPayload): \CRM\Inbox\InboxController
{
    $db          = buildDb();
    $redis       = buildRedis();
    $tenantId    = (string) ($jwtPayload->tenant_id    ?? '');
    $companyCode = (string) ($jwtPayload->company_code ?? '01');

    $service       = new \CRM\Inbox\InboxService($db, $tenantId, $companyCode);
    $cannedService = new \CRM\Inbox\CannedResponseService($db, $tenantId, $companyCode);
    return new \CRM\Inbox\InboxController($service, $redis, $cannedService);
}

function buildCannedResponseController(?object $jwtPayload): \CRM\Inbox\CannedResponseController
{
    $db          = buildDb();
    $redis       = buildRedis();
    $tenantId    = (string) ($jwtPayload->tenant_id    ?? '');
    $companyCode = (string) ($jwtPayload->company_code ?? '01');

    $service = new \CRM\Inbox\CannedResponseService($db, $tenantId, $companyCode);
    return new \CRM\Inbox\CannedResponseController($service, $redis);
}

function buildMailboxController(?object $jwtPayload): \CRM\Email\MailboxController
{
    $db          = buildDb();
    $redis       = buildRedis();
    $tenantId    = (string) ($jwtPayload->tenant_id    ?? '');
    $companyCode = (string) ($jwtPayload->company_code ?? '01');

    $connectionService = new \CRM\Email\MailboxConnectionService($db, $redis);
    $syncService       = new \CRM\Email\EmailSyncService($db, $connectionService);
    $trackingService   = new \CRM\Email\EmailTrackingService($db);

    $controller = new \CRM\Email\MailboxController($connectionService, $syncService, $trackingService);
    $controller->setTenantId($tenantId);
    $controller->setCompanyCode($companyCode);
    if ($jwtPayload !== null) {
        $controller->setUserId((string) ($jwtPayload->sub ?? ''));
    }
    return $controller;
}

function buildWorkflowController(?object $jwtPayload): \CRM\Workflows\WorkflowController
{
    $db          = buildDb();
    $tenantId    = (string) ($jwtPayload->tenant_id    ?? '');
    $companyCode = (string) ($jwtPayload->company_code ?? '01');

    $service    = new \CRM\Workflows\WorkflowService($db, $tenantId, $companyCode);
    $controller = new \CRM\Workflows\WorkflowController($service);
    $controller->setTenantId($tenantId);
    $controller->setCompanyCode($companyCode);
    if ($jwtPayload !== null) {
        $controller->setUserId((string) ($jwtPayload->sub ?? ''));
    }
    return $controller;
}

function buildRedis(): object
{
    $host     = $_ENV['REDIS_HOST']     ?? getenv('REDIS_HOST')     ?: '127.0.0.1';
    $port     = (int) ($_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6379);
    $password = $_ENV['REDIS_PASSWORD'] ?? getenv('REDIS_PASSWORD') ?: null;

    $redis = new \Redis();
    $redis->connect($host, $port);
    if ($password) {
        $redis->auth($password);
    }
    return $redis;
}

function buildDb(): object
{
    // ADOdb connection — reads from environment
    $dsn  = $_ENV['DB_DSN']  ?? getenv('DB_DSN')  ?: 'pgsql://postgres:postgres@127.0.0.1/nexsaas';
    $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'postgres';
    $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

    $adodbPath = __DIR__ . '/../../../vendor/adodb/adodb-php/adodb.inc.php';
    if (file_exists($adodbPath)) {
        require_once $adodbPath;
        $db = ADONewConnection('pgsql');
        $db->Connect(
            $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1',
            $user,
            $pass,
            $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'nexsaas'
        );
        return $db;
    }

    // Fallback: PDO wrapper that mimics ADOdb interface
    return new class($dsn, $user, $pass) {
        private \PDO $pdo;
        private int  $affectedRows = 0;
        private mixed $lastId      = null;

        public function __construct(string $dsn, string $user, string $pass)
        {
            $this->pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        }

        public function Execute(string $sql, array $params = []): object|false
        {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $this->affectedRows = $stmt->rowCount();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                return new class($rows) {
                    public bool  $EOF;
                    public array $fields = [];
                    private array $rows;
                    private int   $cursor = 0;

                    public function __construct(array $rows)
                    {
                        $this->rows = $rows;
                        $this->EOF  = empty($rows);
                        if (!$this->EOF) {
                            $this->fields = $rows[0];
                        }
                    }

                    public function MoveNext(): void
                    {
                        $this->cursor++;
                        if ($this->cursor >= count($this->rows)) {
                            $this->EOF = true;
                        } else {
                            $this->fields = $this->rows[$this->cursor];
                        }
                    }
                };
            } catch (\PDOException $e) {
                return false;
            }
        }

        public function ErrorMsg(): string { return ''; }
        public function Affected_Rows(): int { return $this->affectedRows; }
        public function Insert_ID(): mixed { return $this->pdo->lastInsertId(); }
        public function BeginTrans(): void { $this->pdo->beginTransaction(); }
        public function CommitTrans(): void { $this->pdo->commit(); }
        public function RollbackTrans(): void { $this->pdo->rollBack(); }
    };
}
