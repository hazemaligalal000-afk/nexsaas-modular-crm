<?php
/**
 * Core/ModuleManager.php
 * Dynamic module loader and API router for nexsaas-modular-crm.
 */

namespace Core;

class ModuleManager {
    private $modulesPath;
    private $loadedModules = [];

    public function __construct($modulesPath) {
        $this->modulesPath = $modulesPath;
    }

    /**
     * Finds and bootstraps active modules based on directory structure.
     */
    public function loadActiveModules($tenantId) {
        $dirs = array_filter(glob($this->modulesPath . '/*'), 'is_dir');
        
        foreach ($dirs as $dir) {
            $moduleName = basename($dir);
            $configFile = $dir . '/module.json';
            
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                if ($config['enabled'] ?? false) {
                    $this->loadedModules[$moduleName] = $config;
                }
            }
        }
    }

    /**
     * Dispatch incoming API requests to the respective module's ApiController.
     * URI Pattern: /api/{module}/{action} or /api/{module}/{id}/{action}
     */
    public function dispatchApiRequest($method, $uri) {
        // Clean up URI (e.g., remove /api/ prefix if present)
        $uri = trim($uri, '/');
        $parts = explode('/', $uri);

        // Expecting: [0] => module, [1] => action/id
        if (count($parts) < 1) {
            throw new \Exception("Invalid API endpoint structure", 400);
        }

        $moduleName = ucfirst($parts[0]);
        $action = $parts[1] ?? 'index';
        $id = null;

        // Simple RESTful routing logic:
        // GET /leads           => index()
        // POST /leads          => store($data)
        // GET /leads/123       => show(123)
        // PATCH /leads/123     => update(123, $data)
        // DELETE /leads/123    => destroy(123)
        
        if (is_numeric($action)) {
            $id = $action;
            if ($method === 'GET') $action = 'show';
            elseif ($method === 'PATCH' || $method === 'PUT') $action = 'update';
            elseif ($method === 'DELETE') $action = 'destroy';
            else $action = $parts[2] ?? 'index';
        }

        if (!isset($this->loadedModules[$moduleName])) {
            throw new \Exception("Module '{$moduleName}' not found or disabled.", 404);
        }

        $controllerClass = "Modules\\{$moduleName}\\ApiController";
        
        if (!class_exists($controllerClass)) {
            // Lazy load the class if autoloader is not configured for modules
            $file = $this->modulesPath . "/{$moduleName}/ApiController.php";
            if (file_exists($file)) {
                require_once $file;
            } else {
                throw new \Exception("Controller for module '{$moduleName}' not found.", 404);
            }
        }

        $controller = new $controllerClass();
        
        if (!method_exists($controller, $action)) {
            throw new \Exception("Action '{$action}' not found in '{$moduleName}' controller.", 404);
        }

        // Get JSON input for POST/PATCH
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Execute and return response
        if ($id) {
            return $controller->$action($id, $data);
        } else {
            return $controller->$action($data);
        }
    }
}
