<?php
/**
 * tests/Unit/ModuleRegistryTest.php
 *
 * Unit tests for Bootstrap\ModuleRegistry.
 *
 * Covers:
 *  - Scanning and loading module.json manifests (Req 5.2)
 *  - Registering permissions into RBAC system (Req 5.3)
 *  - Topological sort for dependency order (Req 5.4)
 *  - Missing dependency: log error, disable module, continue (Req 5.5)
 */

declare(strict_types=1);

namespace Tests\Unit;

use Bootstrap\ModuleRegistry;
use PHPUnit\Framework\TestCase;

class ModuleRegistryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Write a module.json into a temp directory and return the dir path. */
    private function makeTempModulesDir(array $modules): string
    {
        $base = sys_get_temp_dir() . '/nexsaas_test_' . uniqid('', true);
        mkdir($base, 0777, true);

        foreach ($modules as $name => $manifest) {
            $dir = $base . '/' . $name;
            mkdir($dir, 0777, true);
            file_put_contents($dir . '/module.json', json_encode($manifest));
        }

        return $base;
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*', GLOB_ONLYDIR) as $sub) {
            array_map('unlink', glob($sub . '/*'));
            rmdir($sub);
        }
        rmdir($dir);
    }

    // -------------------------------------------------------------------------
    // 5.2 — Auto-discovery
    // -------------------------------------------------------------------------

    public function testBootstrapLoadsAllModuleJsonFiles(): void
    {
        $dir = $this->makeTempModulesDir([
            'Alpha' => ['name' => 'Alpha', 'version' => '1.0.0', 'dependencies' => [], 'permissions' => [], 'routes' => []],
            'Beta'  => ['name' => 'Beta',  'version' => '1.0.0', 'dependencies' => [], 'permissions' => [], 'routes' => []],
        ]);

        $registry = new ModuleRegistry($dir);
        $registry->bootstrap();

        $order = $registry->getLoadOrder();
        $this->assertContains('Alpha', $order);
        $this->assertContains('Beta', $order);

        $this->cleanup($dir);
    }

    public function testEmptyModulesDirProducesNoModules(): void
    {
        $dir = sys_get_temp_dir() . '/nexsaas_empty_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $registry = new ModuleRegistry($dir);
        $registry->bootstrap();

        $this->assertEmpty($registry->getLoadOrder());
        $this->assertNotEmpty($registry->getErrors()); // should log a warning

        rmdir($dir);
    }

    // -------------------------------------------------------------------------
    // 5.3 — Permission registration
    // -------------------------------------------------------------------------

    public function testPermissionsAreRegisteredForEnabledModules(): void
    {
        $dir = $this->makeTempModulesDir([
            'Platform' => [
                'name'         => 'Platform',
                'version'      => '1.0.0',
                'dependencies' => [],
                'permissions'  => ['platform.users.read', 'platform.users.create'],
                'routes'       => [],
            ],
        ]);

        $registry = new ModuleRegistry($dir);
        $registry->bootstrap();

        $perms = $registry->getAllPermissions();
        $this->assertArrayHasKey('Platform', $perms);
        $this->assertContains('platform.users.read', $perms['Platform']);
        $this->assertContains('platform.users.create', $perms['Platform']);

        $this->cleanup($dir);
    }

    public function testDisabledModulePermissionsAreNotRegistered(): void
    {
        $dir = $this->makeTempModulesDir([
            'Orphan' => [
                'name'         => 'Orphan',
                'version'      => '1.0.0',
                'dependencies' => ['NonExistent'],
                'permissions'  => ['orphan.do.something'],
                'routes'       => [],
            ],
        ]);

        $registry = new ModuleRegistry($dir);
        $registry->bootstrap();

        $perms = $registry->getAllPermissions();
        $this->assertArrayNotHasKey('Orphan', $perms);

        $this->cleanup($dir);
    }

    // -------------------------------------------------------------------------
    // 5.4 — Topological sort / dependency order
    // -------------------------------------------------------------------------

    public function testDependencyIsLoadedBeforeDependent(): void
    {
        $dir = $this->makeTempModulesDir([
            'CRM'      => ['name' => 'CRM',      'version' => '1.0.0', 'dependencies' => ['Platform'], 'permissions' => [], 'routes' => []],
            'Platform' => ['name' => 'Platform', 'version' => '1.0.0', 'dependencies' => [],           'permissions' => [], 'routes' => []],
        ]);

        $registry = new ModuleRegistry($dir);
        $registry->bootstrap();

        $order = $registry->getLoadOrder();
        $platformIdx = array_search('Platform', $order, true);
        $crmIdx      = array_search('CRM', $order, true);

        $this->assertNotFalse($platformIdx, 'Platform should be in load order');
        $this->assertNotFalse($crmIdx,      'CRM should be in load order');
        $this->assertLessThan($crmIdx, $platformIdx, 'Platform must load before CRM');

        $this->cleanup($dir);
    }

    public function testChainedDependenciesAreOrderedCorrectly(): void
    {
        // Accounting → ERP → Platform
        $dir = $this->makeTempModulesDir([
            'Accounting' => ['name' => 'Accounting', 'version' => '1.0.0', 'dependencies' => ['ERP'],      'permissions' => [], 'routes' => []],
            'ERP'        => ['name' => 'ERP',        'version' => '1.0.0', 'dependencies' => ['Platform'], 'permissions' => [], 'routes' => []],
            'Platform'   => ['name' => 'Platform',   'version' => '1.0.0', 'dependencies' => [],           'permissions' => [], 'routes' => []],
        ]);

        $registry = new ModuleRegistry($dir);
        $registry->bootstrap();

        $order = $registry->getLoadOrder();
        $pIdx  = array_search('Platform',   $order, true);
        $eIdx  = array_search('ERP',        $order, true);
        $aIdx  = array_search('Accounting', $order, true);

        $this->assertLessThan($eIdx, $pIdx, 'Platform must load before ERP');
        $this->assertLessThan($aIdx, $eIdx, 'ERP must load before Accounting');

        $this->cleanup($dir);
    }

    // -------------------------------------------------------------------------
    // 5.5 — Missing dependency handling
    // -------------------------------------------------------------------------

    public function testMissingDependencyDisablesModuleWithoutHaltingBootstrap(): void
    {
        $dir = $this->makeTempModulesDir([
            'Platform' => ['name' => 'Platform', 'version' => '1.0.0', 'dependencies' => [],              'permissions' => ['platform.users.read'], 'routes' => []],
            'CRM'      => ['name' => 'CRM',      'version' => '1.0.0', 'dependencies' => ['MissingDep'],  'permissions' => ['crm.contacts.read'],   'routes' => []],
        ]);

        $registry = new ModuleRegistry($dir);
        $registry->bootstrap(); // must not throw

        // Platform should still load
        $this->assertContains('Platform', $registry->getLoadOrder());

        // CRM should be disabled
        $this->assertFalse($registry->isEnabled('CRM'));
        $this->assertNotContains('CRM', $registry->getLoadOrder());

        // An error should have been logged
        $errors = $registry->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('MissingDep', implode(' ', $errors));

        $this->cleanup($dir);
    }

    public function testMultipleModulesWithMissingDepsAllDisabled(): void
    {
        $dir = $this->makeTempModulesDir([
            'A' => ['name' => 'A', 'version' => '1.0.0', 'dependencies' => ['Ghost1'], 'permissions' => [], 'routes' => []],
            'B' => ['name' => 'B', 'version' => '1.0.0', 'dependencies' => ['Ghost2'], 'permissions' => [], 'routes' => []],
            'C' => ['name' => 'C', 'version' => '1.0.0', 'dependencies' => [],         'permissions' => [], 'routes' => []],
        ]);

        $registry = new ModuleRegistry($dir);
        $registry->bootstrap();

        $this->assertFalse($registry->isEnabled('A'));
        $this->assertFalse($registry->isEnabled('B'));
        $this->assertTrue($registry->isEnabled('C'));
        $this->assertContains('C', $registry->getLoadOrder());

        $this->cleanup($dir);
    }

    // -------------------------------------------------------------------------
    // Route registration
    // -------------------------------------------------------------------------

    public function testRoutesAreRegisteredForEnabledModules(): void
    {
        $dir = $this->makeTempModulesDir([
            'Platform' => [
                'name'         => 'Platform',
                'version'      => '1.0.0',
                'dependencies' => [],
                'permissions'  => [],
                'routes'       => [
                    ['method' => 'POST', 'path' => '/api/v1/auth/login', 'handler' => 'Platform\\Auth\\AuthController::login'],
                ],
            ],
        ]);

        $registry = new ModuleRegistry($dir);
        $registry->bootstrap();

        $routes = $registry->getAllRoutes();
        $this->assertArrayHasKey('Platform', $routes);
        $this->assertCount(1, $routes['Platform']);
        $this->assertSame('/api/v1/auth/login', $routes['Platform'][0]['path']);

        $this->cleanup($dir);
    }

    // -------------------------------------------------------------------------
    // Invalid manifest handling
    // -------------------------------------------------------------------------

    public function testInvalidJsonIsSkippedWithError(): void
    {
        $dir = sys_get_temp_dir() . '/nexsaas_invalid_' . uniqid('', true);
        mkdir($dir . '/BadModule', 0777, true);
        file_put_contents($dir . '/BadModule/module.json', '{not valid json}');

        $registry = new ModuleRegistry($dir);
        $registry->bootstrap();

        $this->assertEmpty($registry->getLoadOrder());
        $this->assertNotEmpty($registry->getErrors());

        array_map('unlink', glob($dir . '/BadModule/*'));
        rmdir($dir . '/BadModule');
        rmdir($dir);
    }

    public function testManifestWithoutNameIsSkipped(): void
    {
        $dir = $this->makeTempModulesDir([
            'NoName' => ['version' => '1.0.0', 'dependencies' => [], 'permissions' => [], 'routes' => []],
        ]);

        $registry = new ModuleRegistry($dir);
        $registry->bootstrap();

        $this->assertEmpty($registry->getLoadOrder());
        $this->assertNotEmpty($registry->getErrors());

        $this->cleanup($dir);
    }

    // -------------------------------------------------------------------------
    // Real module.json stubs (task 3.2 integration smoke test)
    // -------------------------------------------------------------------------

    public function testRealModuleJsonStubsLoadCorrectly(): void
    {
        // Point at the actual modules directory
        $modulesDir = dirname(__DIR__, 2) . '/modules';

        if (!is_dir($modulesDir)) {
            $this->markTestSkipped('modules directory not found at ' . $modulesDir);
        }

        $registry = new ModuleRegistry($modulesDir);
        $registry->bootstrap();

        $order = $registry->getLoadOrder();

        // Platform has no deps — must be present
        $this->assertContains('Platform', $order, 'Platform module should be enabled');

        // CRM depends on Platform — Platform must come first
        if (in_array('CRM', $order, true)) {
            $pIdx = array_search('Platform', $order, true);
            $cIdx = array_search('CRM', $order, true);
            $this->assertLessThan($cIdx, $pIdx, 'Platform must load before CRM');
        }

        // Accounting depends on ERP which depends on Platform
        if (in_array('Accounting', $order, true) && in_array('ERP', $order, true)) {
            $eIdx = array_search('ERP',        $order, true);
            $aIdx = array_search('Accounting', $order, true);
            $this->assertLessThan($aIdx, $eIdx, 'ERP must load before Accounting');
        }

        // No errors expected for the real stubs
        $this->assertEmpty(
            $registry->getErrors(),
            'Unexpected errors: ' . implode('; ', $registry->getErrors())
        );
    }
}
