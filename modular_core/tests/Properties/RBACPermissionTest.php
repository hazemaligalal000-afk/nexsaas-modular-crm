<?php
/**
 * Property 4: RBAC Permission Enforcement
 *
 * Validates: Requirements 2.3, 2.4, 2.5
 *
 * Properties verified:
 *   P4-a  A user whose role holds permission P can access the operation
 *         (check returns true).
 *   P4-b  A user whose role does NOT hold permission P is denied
 *         (check returns false).
 *   P4-c  PermissionMiddleware returns null (allow) when check passes.
 *   P4-d  PermissionMiddleware returns HTTP 403 with a descriptive error
 *         when check fails.
 *   P4-e  Cache hit: resolvePermissions returns the same set as the DB
 *         when the cache is pre-populated.
 *   P4-f  Cache miss: resolvePermissions falls back to DB and stores result.
 *   P4-g  Permission strings must follow the module.action format.
 */

declare(strict_types=1);

namespace Tests\Properties;

use Core\BaseController;
use Platform\RBAC\PermissionMiddleware;
use Platform\RBAC\RBACService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Platform\RBAC\RBACService
 * @covers \Platform\RBAC\PermissionMiddleware
 */
class RBACPermissionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function randomUuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /** Generate a random permission string in module.action format. */
    private function randomPermission(): string
    {
        $modules = ['crm', 'erp', 'accounting', 'platform', 'hr'];
        $actions = ['read', 'create', 'update', 'delete', 'export', 'approve'];
        return $modules[array_rand($modules)] . '.' . $actions[array_rand($actions)];
    }

    /** Generate a random role name. */
    private function randomRole(): string
    {
        $roles = ['Owner', 'Admin', 'Manager', 'Agent', 'Support', 'Accountant', 'Reviewer', 'Viewer'];
        return $roles[array_rand($roles)];
    }

    /**
     * Build a mock Redis client.
     *
     * @param  array<string,string> $store  Initial key→value store.
     * @return object
     */
    private function buildMockRedis(array $store = []): object
    {
        return new class($store) {
            private array $store;
            public array $published = [];

            public function __construct(array $store)
            {
                $this->store = $store;
            }

            public function get(string $key): string|false
            {
                return $this->store[$key] ?? false;
            }

            public function setex(string $key, int $ttl, string $value): bool
            {
                $this->store[$key] = $value;
                return true;
            }

            public function del(string $key): int
            {
                if (isset($this->store[$key])) {
                    unset($this->store[$key]);
                    return 1;
                }
                return 0;
            }

            public function publish(string $channel, string $message): int
            {
                $this->published[] = ['channel' => $channel, 'message' => $message];
                return 1;
            }

            public function has(string $key): bool
            {
                return isset($this->store[$key]);
            }

            public function getStore(): array
            {
                return $this->store;
            }
        };
    }

    /**
     * Build a mock ADOdb connection.
     *
     * @param  string $platformRole    User's platform role.
     * @param  string|null $accountingRole User's accounting role.
     * @param  array<string> $permissions  Permissions for the role in DB.
     * @param  string $tenantId
     * @param  int    $userId
     * @return object
     */
    private function buildMockDb(
        string  $platformRole,
        ?string $accountingRole,
        array   $permissions,
        string  $tenantId,
        int     $userId
    ): object {
        return new class($platformRole, $accountingRole, $permissions, $tenantId, $userId) {
            private string  $platformRole;
            private ?string $accountingRole;
            private array   $permissions;
            private string  $tenantId;
            private int     $userId;
            private int     $affectedRows = 0;

            public function __construct(
                string  $platformRole,
                ?string $accountingRole,
                array   $permissions,
                string  $tenantId,
                int     $userId
            ) {
                $this->platformRole   = $platformRole;
                $this->accountingRole = $accountingRole;
                $this->permissions    = $permissions;
                $this->tenantId       = $tenantId;
                $this->userId         = $userId;
            }

            public function Execute(string $sql, array $params = [])
            {
                // User lookup query
                if (stripos($sql, 'FROM users') !== false) {
                    $uid = $params[0] ?? null;
                    $tid = $params[1] ?? null;

                    if ((int) $uid !== $this->userId || $tid !== $this->tenantId) {
                        return $this->emptyRs();
                    }

                    return $this->singleRowRs([
                        'platform_role'   => $this->platformRole,
                        'accounting_role' => $this->accountingRole,
                    ]);
                }

                // Permissions lookup query
                if (stripos($sql, 'FROM role_permissions') !== false) {
                    $rows = array_map(
                        static fn(string $p): array => ['permission' => $p],
                        $this->permissions
                    );
                    return $this->multiRowRs($rows);
                }

                return $this->emptyRs();
            }

            public function ErrorMsg(): string { return ''; }
            public function Affected_Rows(): int { return $this->affectedRows; }
            public function Insert_ID(): int { return 1; }
            public function BeginTrans(): void {}
            public function CommitTrans(): void {}
            public function RollbackTrans(): void {}

            private function emptyRs(): object
            {
                return new class {
                    public bool  $EOF    = true;
                    public array $fields = [];
                    public function MoveNext(): void {}
                };
            }

            private function singleRowRs(array $row): object
            {
                return new class($row) {
                    public bool  $EOF;
                    public array $fields;
                    private bool $moved = false;

                    public function __construct(array $row)
                    {
                        $this->EOF    = false;
                        $this->fields = $row;
                    }

                    public function MoveNext(): void
                    {
                        $this->EOF = true;
                    }
                };
            }

            private function multiRowRs(array $rows): object
            {
                return new class($rows) {
                    private array $rows;
                    private int   $cursor = 0;
                    public bool   $EOF;
                    public array  $fields = [];

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
            }
        };
    }

    /** Build a concrete BaseController for use with PermissionMiddleware. */
    private function makeController(string $tenantId, string $userId): BaseController
    {
        $ctrl = new class extends BaseController {};
        $ctrl->setTenantId($tenantId);
        $ctrl->setUserId($userId);
        $ctrl->setCompanyCode('01');
        $ctrl->setCurrency('EGP');
        $ctrl->setFinPeriod('202501');
        return $ctrl;
    }

    // -------------------------------------------------------------------------
    // P4-a: User with permission → check returns true
    // Validates: Requirements 2.3, 2.4
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 2.3, 2.4**
     *
     * Property: for any user whose role holds permission P,
     * RBACService::check(userId, P) returns true.
     */
    public function testUserWithPermissionIsAllowed(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId   = $this->randomUuid();
            $userId     = random_int(1, 9999);
            $role       = $this->randomRole();
            $permission = $this->randomPermission();

            // DB has the permission for this role
            $db    = $this->buildMockDb($role, null, [$permission], $tenantId, $userId);
            $redis = $this->buildMockRedis();

            $service = new RBACService($db, $redis, $tenantId);

            $this->assertTrue(
                $service->check($userId, $permission),
                "Iteration $i: user with permission '$permission' must be allowed"
            );
        }
    }

    // -------------------------------------------------------------------------
    // P4-b: User without permission → check returns false
    // Validates: Requirements 2.3, 2.4
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 2.3, 2.4**
     *
     * Property: for any user whose role does NOT hold permission P,
     * RBACService::check(userId, P) returns false.
     */
    public function testUserWithoutPermissionIsDenied(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId        = $this->randomUuid();
            $userId          = random_int(1, 9999);
            $role            = $this->randomRole();
            $heldPermission  = $this->randomPermission();
            // Ensure the checked permission is different from the held one
            do {
                $checkedPermission = $this->randomPermission();
            } while ($checkedPermission === $heldPermission);

            // DB only has $heldPermission, not $checkedPermission
            $db    = $this->buildMockDb($role, null, [$heldPermission], $tenantId, $userId);
            $redis = $this->buildMockRedis();

            $service = new RBACService($db, $redis, $tenantId);

            $this->assertFalse(
                $service->check($userId, $checkedPermission),
                "Iteration $i: user without permission '$checkedPermission' must be denied"
            );
        }
    }

    // -------------------------------------------------------------------------
    // P4-c: PermissionMiddleware returns null when allowed
    // Validates: Requirements 2.4, 2.5
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 2.4, 2.5**
     *
     * Property: PermissionMiddleware::handle() returns null (pass-through)
     * when the user holds the required permission.
     */
    public function testMiddlewareAllowsAuthorizedUser(): void
    {
        $iterations = 40;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId   = $this->randomUuid();
            $userId     = random_int(1, 9999);
            $role       = $this->randomRole();
            $permission = $this->randomPermission();

            $db         = $this->buildMockDb($role, null, [$permission], $tenantId, $userId);
            $redis      = $this->buildMockRedis();
            $service    = new RBACService($db, $redis, $tenantId);
            $controller = $this->makeController($tenantId, (string) $userId);
            $middleware = new PermissionMiddleware($service, $controller);

            $result = $middleware->handle($userId, $permission);

            $this->assertNull(
                $result,
                "Iteration $i: middleware must return null for authorized user"
            );
        }
    }

    // -------------------------------------------------------------------------
    // P4-d: PermissionMiddleware returns HTTP 403 when denied
    // Validates: Requirement 2.5
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 2.5**
     *
     * Property: PermissionMiddleware::handle() returns a Response with
     * HTTP status 403 and success=false when the user lacks the permission.
     */
    public function testMiddlewareReturns403ForUnauthorizedUser(): void
    {
        $iterations = 40;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId        = $this->randomUuid();
            $userId          = random_int(1, 9999);
            $role            = $this->randomRole();
            $heldPermission  = $this->randomPermission();
            do {
                $checkedPermission = $this->randomPermission();
            } while ($checkedPermission === $heldPermission);

            $db         = $this->buildMockDb($role, null, [$heldPermission], $tenantId, $userId);
            $redis      = $this->buildMockRedis();
            $service    = new RBACService($db, $redis, $tenantId);
            $controller = $this->makeController($tenantId, (string) $userId);
            $middleware = new PermissionMiddleware($service, $controller);

            $response = $middleware->handle($userId, $checkedPermission);

            $this->assertNotNull(
                $response,
                "Iteration $i: middleware must return a Response for unauthorized user"
            );
            $this->assertSame(
                403,
                $response->status,
                "Iteration $i: HTTP status must be 403"
            );
            $this->assertFalse(
                $response->body['success'],
                "Iteration $i: success must be false in 403 response"
            );
            $this->assertNotEmpty(
                $response->body['error'],
                "Iteration $i: error message must be non-empty in 403 response"
            );
            $this->assertStringContainsString(
                $checkedPermission,
                $response->body['error'],
                "Iteration $i: error message must mention the missing permission"
            );
        }
    }

    // -------------------------------------------------------------------------
    // P4-e: Cache hit returns same permissions as DB
    // Validates: Requirement 2.6
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 2.6**
     *
     * Property: when the Redis cache is pre-populated with a user's permissions,
     * resolvePermissions returns exactly those permissions without hitting the DB.
     */
    public function testCacheHitReturnsCachedPermissions(): void
    {
        $iterations = 40;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId   = $this->randomUuid();
            $userId     = random_int(1, 9999);
            $role       = $this->randomRole();

            // Generate a random set of permissions to cache
            $count       = random_int(1, 5);
            $permissions = array_map(fn() => $this->randomPermission(), range(1, $count));
            $permissions = array_values(array_unique($permissions));

            $cacheKey = "permissions:{$tenantId}:{$userId}";
            $redis    = $this->buildMockRedis([$cacheKey => json_encode($permissions)]);

            // DB has different permissions — should NOT be consulted
            $db      = $this->buildMockDb($role, null, ['db.only.permission'], $tenantId, $userId);
            $service = new RBACService($db, $redis, $tenantId);

            $resolved = $service->resolvePermissions($userId);

            $this->assertSame(
                $permissions,
                $resolved,
                "Iteration $i: cache hit must return exactly the cached permissions"
            );
        }
    }

    // -------------------------------------------------------------------------
    // P4-f: Cache miss falls back to DB and stores result in Redis
    // Validates: Requirement 2.6
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 2.6**
     *
     * Property: on a cache miss, resolvePermissions loads from DB and
     * stores the result in Redis so subsequent calls hit the cache.
     */
    public function testCacheMissFallsBackToDbAndPopulatesCache(): void
    {
        $iterations = 40;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId   = $this->randomUuid();
            $userId     = random_int(1, 9999);
            $role       = $this->randomRole();
            $permission = $this->randomPermission();

            // Empty Redis — cache miss
            $redis   = $this->buildMockRedis();
            $db      = $this->buildMockDb($role, null, [$permission], $tenantId, $userId);
            $service = new RBACService($db, $redis, $tenantId);

            $resolved = $service->resolvePermissions($userId);

            // DB permissions must be returned
            $this->assertContains(
                $permission,
                $resolved,
                "Iteration $i: DB permission must be in resolved set on cache miss"
            );

            // Cache must now be populated
            $cacheKey = "permissions:{$tenantId}:{$userId}";
            $this->assertTrue(
                $redis->has($cacheKey),
                "Iteration $i: Redis cache must be populated after DB fallback"
            );

            // Cached value must match resolved permissions
            $cached = json_decode($redis->get($cacheKey), true);
            $this->assertSame(
                $resolved,
                $cached,
                "Iteration $i: cached value must match resolved permissions"
            );
        }
    }

    // -------------------------------------------------------------------------
    // P4-g: Permission strings follow module.action format
    // Validates: Requirement 2.3
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 2.3**
     *
     * Property: all permission strings in the system follow the module.action
     * format (at least one dot, only alphanumeric/underscore segments).
     */
    public function testPermissionStringsFollowModuleActionFormat(): void
    {
        $validPermissions = [
            'crm.contacts.create',
            'crm.contacts.read',
            'erp.invoices.approve',
            'accounting.vouchers.post',
            'platform.roles.assign',
            'platform.permissions.revoke',
            'hr.payroll.run',
        ];

        $invalidPermissions = [
            '',
            'nodot',
            '.leading_dot',
            'trailing_dot.',
            'has space.action',
            'module..double_dot',
        ];

        $pattern = '/^[a-z0-9_]+(\.[a-z0-9_]+)+$/i';

        foreach ($validPermissions as $perm) {
            $this->assertMatchesRegularExpression(
                $pattern,
                $perm,
                "Permission '$perm' must match module.action format"
            );
        }

        foreach ($invalidPermissions as $perm) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $perm,
                "Permission '$perm' must NOT match module.action format"
            );
        }
    }

    // -------------------------------------------------------------------------
    // P4-h: User with no role has no permissions
    // Validates: Requirements 2.3, 2.4
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 2.3, 2.4**
     *
     * Property: a user that does not exist in the DB (or has no role)
     * resolves to an empty permission set and is denied all checks.
     */
    public function testNonExistentUserHasNoPermissions(): void
    {
        $iterations = 30;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId   = $this->randomUuid();
            $userId     = random_int(1, 9999);
            $permission = $this->randomPermission();

            // DB returns no user row
            $db    = $this->buildMockDb('Agent', null, [], $tenantId, $userId + 1); // different userId
            $redis = $this->buildMockRedis();

            $service = new RBACService($db, $redis, $tenantId);

            $this->assertFalse(
                $service->check($userId, $permission),
                "Iteration $i: non-existent user must be denied all permissions"
            );
        }
    }
}
