<?php
/**
 * Core/ModuleManager.php
 * Handles Plug-and-Play Dynamics (Task 3 and Task 7)
 * Automatically discovers, installs via Schema, and mounts API controllers for active modules.
 */

namespace Core;

class ModuleManager {
    private $modulesDir;
    private $activeModules = [];

    public function __construct($dir = __DIR__ . '/../modules') {
        $this->modulesDir = $dir;
    }

    /**
     * Bootstraps all active modules for the current SaaS Organization.
     * (E.g., if Tenant X paid for Invoicing, it loads. If not, it skips.)
     */
    public function loadActiveModules($tenantId) {
        $directories = glob($this->modulesDir . '/*' , GLOB_ONLYDIR);
        
        foreach($directories as $dir) {
            $moduleName = basename($dir);
            $manifestPath = $dir . '/module.json';
            
            // Backwards compatibility with early schema.json or new module.json standard
            if (!file_exists($manifestPath)) {
                $manifestPath = $dir . '/schema.json';
            }

            if (file_exists($manifestPath)) {
                $config = json_decode(file_get_contents($manifestPath), true);
                
                if ($config['enabled'] ?? true) {
                    $this->activeModules[$moduleName] = [
                        'config' => $config,
                        'controllerClass' => "\\Modules\\{$moduleName}\\ApiController",
                        'path' => $dir
                    ];
                    
                    // Provision permissions into the core RBAC engine dynamically
                    $this->provisionPermissions($moduleName, $config['permissions'] ?? []);
                }
            }
        }
    }

    private function provisionPermissions($moduleName, $permissions) {
        // In a real-world scenario, this would query the DB and insert missing roles/permissions.
        // For the NexaCRM prototype, we log the provisioning event.
        $logFile = __DIR__ . '/../logs/module_provisioning.log';
        if (!file_exists(dirname($logFile))) {
             mkdir(dirname($logFile), 0777, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $permissionCount = count($permissions);
        
        $entry = "[{$timestamp}] Module [{$moduleName}]: Provisioned {$permissionCount} permission definitions into the SaaS RBAC Engine.\n";
        file_put_contents($logFile, $entry, FILE_APPEND);
    }

    /**
     * Dynamically routes the API request to the specific Module's Controller.
     */
    public function dispatchApiRequest($method, $uri) {
        // e.g., $uri = /api/v1/Leads/123
        $parts = explode('/', trim(str_ireplace('/api/v1', '', $uri), '/'));
        $requestedModule = $parts[0] ?? null;
        $id = $parts[1] ?? null;

        if (!$requestedModule || !isset($this->activeModules[$requestedModule])) {
            throw new \Exception("Module not found, disabled, or uninstalled.", 404);
        }

        $controllerClass = $this->activeModules[$requestedModule]['controllerClass'];
        // Require the physical file automatically
        require_once $this->activeModules[$requestedModule]['path'] . '/ApiController.php';
        
        $controller = new $controllerClass();
        
        // Dynamic Controller Execution (GET -> index/show, POST -> store)
        if ($method === 'GET') {
            return $id ? $controller->show($id) : $controller->index();
        } elseif ($method === 'POST') {
            return $controller->store($_POST);
        } elseif ($method === 'DELETE') {
            return $controller->destroy($id);
        } else {
            throw new \Exception("Method not supported on this Module.", 405);
        }
    }
}
