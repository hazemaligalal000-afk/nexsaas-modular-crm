<?php
/**
 * bootstrap/ModuleRegistry.php
 *
 * Auto-discovers and registers all platform modules.
 *
 * Responsibilities:
 *  - Scan /modular_core/modules/{module}/module.json for module manifests
 *  - Resolve load order via topological sort (dependency-first)
 *  - On missing dependency: log error, mark dependent module disabled, continue
 *  - Register each enabled module's routes and permissions into the RBAC system
 *
 * module.json schema:
 *   {
 *     "name":         string,
 *     "version":      string,
 *     "dependencies": string[],
 *     "permissions":  string[],
 *     "routes":       { "method": string, "path": string, "handler": string }[]
 *   }
 *
 * Requirements: 5.2, 5.3, 5.4, 5.5
 */

declare(strict_types=1);

namespace Bootstrap;

class ModuleRegistry
{
    /** @var array<string, array> Loaded manifests keyed by module name */
    private array $manifests = [];

    /** @var array<string, bool> Enabled/disabled state per module name */
    private array $enabled = [];

    /** @var string[] Ordered list of module names after topological sort */
    private array $loadOrder = [];

    /** @var array<string, string[]> Registered permissions per module */
    private array $permissions = [];

    /** @var array<string, array[]> Registered routes per module */
    private array $routes = [];

    /** @var string[] Log of errors encountered during bootstrap */
    private array $errors = [];

    /**
     * @param string $modulesDir  Absolute path to the modules directory
     *                            (e.g. /var/www/modular_core/modules)
     */
    public function __construct(private readonly string $modulesDir) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Run the full bootstrap sequence:
     *   1. Scan module.json files
     *   2. Topological sort
     *   3. Register routes + permissions for enabled modules
     *
     * Requirements: 5.2, 5.3, 5.4, 5.5
     */
    public function bootstrap(): void
    {
        $this->scan();
        $this->resolveOrder();
        $this->register();
    }

    /** Return the ordered list of enabled module names. */
    public function getLoadOrder(): array
    {
        return array_values(array_filter(
            $this->loadOrder,
            fn(string $name) => $this->enabled[$name] ?? false
        ));
    }

    /** Return all registered permissions across enabled modules. */
    public function getAllPermissions(): array
    {
        return $this->permissions;
    }

    /** Return all registered routes across enabled modules. */
    public function getAllRoutes(): array
    {
        return $this->routes;
    }

    /** Return bootstrap error log entries. */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** Check whether a specific module is enabled. */
    public function isEnabled(string $moduleName): bool
    {
        return $this->enabled[$moduleName] ?? false;
    }

    /** Return the manifest for a module, or null if not found. */
    public function getManifest(string $moduleName): ?array
    {
        return $this->manifests[$moduleName] ?? null;
    }

    // -------------------------------------------------------------------------
    // Step 1 — Scan
    // -------------------------------------------------------------------------

    /**
     * Scan the modules directory for module.json files and load manifests.
     *
     * Requirement 5.2 — auto-discover modules at bootstrap.
     */
    private function scan(): void
    {
        $pattern = rtrim($this->modulesDir, '/') . '/*/module.json';
        $files   = glob($pattern);

        if ($files === false || $files === []) {
            $this->logError('ModuleRegistry: no module.json files found under ' . $this->modulesDir);
            return;
        }

        foreach ($files as $file) {
            $this->loadManifest($file);
        }
    }

    /**
     * Parse and validate a single module.json file.
     */
    private function loadManifest(string $file): void
    {
        $raw = file_get_contents($file);

        if ($raw === false) {
            $this->logError("ModuleRegistry: cannot read {$file}");
            return;
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $this->logError("ModuleRegistry: invalid JSON in {$file}");
            return;
        }

        // Validate required fields
        if (empty($data['name']) || !is_string($data['name'])) {
            $this->logError("ModuleRegistry: missing or invalid 'name' in {$file}");
            return;
        }

        $name = $data['name'];

        // Normalise optional array fields
        $data['dependencies'] = isset($data['dependencies']) && is_array($data['dependencies'])
            ? $data['dependencies']
            : [];

        $data['permissions'] = isset($data['permissions']) && is_array($data['permissions'])
            ? $data['permissions']
            : [];

        $data['routes'] = isset($data['routes']) && is_array($data['routes'])
            ? $data['routes']
            : [];

        $data['version'] = isset($data['version']) && is_string($data['version'])
            ? $data['version']
            : '0.0.0';

        $this->manifests[$name] = $data;
        $this->enabled[$name]   = true; // optimistic; may be disabled in resolveOrder
    }

    // -------------------------------------------------------------------------
    // Step 2 — Topological sort
    // -------------------------------------------------------------------------

    /**
     * Resolve module load order using Kahn's algorithm (BFS topological sort).
     *
     * Modules with missing dependencies are marked disabled and excluded from
     * the sort. The platform continues bootstrapping with the remaining modules.
     *
     * Requirements: 5.4, 5.5
     */
    private function resolveOrder(): void
    {
        // First pass: disable modules whose dependencies are absent
        foreach ($this->manifests as $name => $manifest) {
            foreach ($manifest['dependencies'] as $dep) {
                if (!isset($this->manifests[$dep])) {
                    $this->logError(
                        "ModuleRegistry: module '{$name}' requires missing dependency '{$dep}'. " .
                        "Module '{$name}' has been disabled. (Req 5.5)"
                    );
                    $this->enabled[$name] = false;
                    break;
                }
            }
        }

        // Build adjacency list and in-degree map for enabled modules only
        $enabledNames = array_keys(array_filter($this->enabled));
        $inDegree     = array_fill_keys($enabledNames, 0);
        $adjList      = array_fill_keys($enabledNames, []);

        foreach ($enabledNames as $name) {
            foreach ($this->manifests[$name]['dependencies'] as $dep) {
                // Only consider enabled dependencies
                if (!in_array($dep, $enabledNames, true)) {
                    continue;
                }
                // dep → name edge (dep must load before name)
                $adjList[$dep][] = $name;
                $inDegree[$name]++;
            }
        }

        // Kahn's BFS
        $queue = [];
        foreach ($inDegree as $name => $degree) {
            if ($degree === 0) {
                $queue[] = $name;
            }
        }
        // Sort queue for deterministic ordering when multiple roots exist
        sort($queue);

        $sorted = [];
        while (!empty($queue)) {
            $current  = array_shift($queue);
            $sorted[] = $current;

            $neighbors = $adjList[$current] ?? [];
            sort($neighbors); // deterministic
            foreach ($neighbors as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                    sort($queue); // keep sorted
                }
            }
        }

        // Detect cycles — any enabled module not in sorted list is part of a cycle
        if (count($sorted) < count($enabledNames)) {
            $cycleModules = array_diff($enabledNames, $sorted);
            foreach ($cycleModules as $name) {
                $this->logError(
                    "ModuleRegistry: circular dependency detected involving module '{$name}'. " .
                    "Module '{$name}' has been disabled."
                );
                $this->enabled[$name] = false;
            }
        }

        $this->loadOrder = $sorted;
    }

    // -------------------------------------------------------------------------
    // Step 3 — Register
    // -------------------------------------------------------------------------

    /**
     * Register routes and permissions for each enabled module in load order.
     *
     * Requirement 5.3 — load Permission definitions into the RBAC system.
     */
    private function register(): void
    {
        foreach ($this->loadOrder as $name) {
            if (!($this->enabled[$name] ?? false)) {
                continue;
            }

            $manifest = $this->manifests[$name];

            $this->registerPermissions($name, $manifest['permissions']);
            $this->registerRoutes($name, $manifest['routes']);
        }
    }

    /**
     * Store a module's permission strings.
     *
     * Requirement 5.3 — permissions loaded into RBAC system at bootstrap.
     *
     * @param string   $moduleName
     * @param string[] $permissions  e.g. ["crm.contacts.read", "crm.contacts.write"]
     */
    private function registerPermissions(string $moduleName, array $permissions): void
    {
        $this->permissions[$moduleName] = $permissions;
    }

    /**
     * Store a module's route definitions.
     *
     * @param string  $moduleName
     * @param array[] $routes  Each entry: { method, path, handler }
     */
    private function registerRoutes(string $moduleName, array $routes): void
    {
        $this->routes[$moduleName] = $routes;
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    /**
     * Append an error message to the internal log and emit to error_log().
     *
     * Requirement 5.5 — log error on missing dependency.
     */
    private function logError(string $message): void
    {
        $this->errors[] = $message;
        error_log($message);
    }
}
