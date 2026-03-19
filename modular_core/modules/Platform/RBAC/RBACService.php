<?php
/**
 * Platform/RBAC/RBACService.php
 *
 * Role-Based Access Control service.
 *
 * Responsibilities:
 *  - check(userId, permission): Redis-first permission lookup, DB fallback.
 *  - invalidate(userId): delete the Redis cache key for a user.
 *  - assignPermissions / revokePermissions: mutate role_permissions and
 *    publish rbac.invalidate:{user_id} so every app instance drops the cache.
 *
 * Cache key  : permissions:{tenant_id}:{user_id}
 * Cache TTL  : 300 seconds (Requirement 2.6)
 * Invalidation: publish rbac.invalidate:{user_id} on Redis pub/sub;
 *               all subscribers call invalidate() within 5 s (Requirement 2.7)
 *
 * Requirements: 2.4, 2.5, 2.6, 2.7
 */

declare(strict_types=1);

namespace Platform\RBAC;

use Core\BaseService;

class RBACService extends BaseService
{
    private const CACHE_TTL = 300; // seconds — Requirement 2.6

    /** @var \Redis|\RedisClient|object  Redis client instance */
    private object $redis;

    /** @var string  Current tenant UUID */
    private string $tenantId;

    /**
     * @param \ADOConnection $db       ADOdb connection
     * @param object         $redis    Redis client (must support get/setex/del/publish)
     * @param string         $tenantId Current tenant UUID
     */
    public function __construct($db, object $redis, string $tenantId)
    {
        parent::__construct($db);
        $this->redis    = $redis;
        $this->tenantId = $tenantId;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Check whether the given user holds the specified permission.
     *
     * Algorithm (Requirement 2.4):
     *  1. Build cache key permissions:{tenant_id}:{user_id}.
     *  2. Try Redis GET — if hit, decode JSON and check membership.
     *  3. On cache miss: query DB for the user's role, then load all
     *     permissions for that role from role_permissions.
     *  4. Store the resolved set in Redis with TTL 300 s.
     *  5. Return true iff the permission string is in the set.
     *
     * Requirements: 2.4, 2.5, 2.6
     *
     * @param  int    $userId     Authenticated user's primary key.
     * @param  string $permission Permission string in module.action format.
     * @return bool               true = allowed, false = denied.
     */
    public function check(int $userId, string $permission): bool
    {
        $permissions = $this->resolvePermissions($userId);
        return in_array($permission, $permissions, true);
    }

    /**
     * Resolve (and cache) the full permission set for a user.
     *
     * @param  int   $userId
     * @return array<string>  List of permission strings.
     */
    public function resolvePermissions(int $userId): array
    {
        $cacheKey = $this->cacheKey($userId);

        // 1. Redis-first (Requirement 2.6)
        $cached = $this->redis->get($cacheKey);
        if ($cached !== false && $cached !== null) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 2. DB fallback — fetch user's role, then their permissions
        $permissions = $this->loadPermissionsFromDb($userId);

        // 3. Store in Redis with TTL (Requirement 2.6)
        $this->redis->setex($cacheKey, self::CACHE_TTL, json_encode($permissions));

        return $permissions;
    }

    /**
     * Invalidate the Redis cache entry for a user.
     *
     * Called locally by this instance when it mutates permissions, and also
     * called by the pub/sub subscriber on every other app instance.
     *
     * Requirement 2.7
     *
     * @param  int $userId
     * @return void
     */
    public function invalidate(int $userId): void
    {
        $this->redis->del($this->cacheKey($userId));
    }

    /**
     * Assign a list of permissions to a role, then invalidate all affected users.
     *
     * Requirement 2.8
     *
     * @param  string        $role        Role name (Owner|Admin|Manager|Agent|Support|…)
     * @param  array<string> $permissions Permission strings to assign.
     * @param  int           $actorId     User performing the change (for created_by).
     * @return void
     */
    public function assignPermissions(string $role, array $permissions, int $actorId): void
    {
        $this->transaction(function () use ($role, $permissions, $actorId): void {
            foreach ($permissions as $permission) {
                $this->upsertPermission($role, $permission, $actorId);
            }
        });

        $this->publishInvalidationForRole($role);
    }

    /**
     * Revoke a list of permissions from a role, then invalidate all affected users.
     *
     * Requirement 2.8
     *
     * @param  string        $role
     * @param  array<string> $permissions
     * @return void
     */
    public function revokePermissions(string $role, array $permissions): void
    {
        $this->transaction(function () use ($role, $permissions): void {
            foreach ($permissions as $permission) {
                $this->softDeletePermission($role, $permission);
            }
        });

        $this->publishInvalidationForRole($role);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build the Redis cache key for a user's permission set.
     *
     * Format: permissions:{tenant_id}:{user_id}
     *
     * @param  int $userId
     * @return string
     */
    private function cacheKey(int $userId): string
    {
        return "permissions:{$this->tenantId}:{$userId}";
    }

    /**
     * Load the user's permissions from the database.
     *
     * Steps:
     *  a) Fetch the user's platform_role (and optionally accounting_role).
     *  b) Query role_permissions for all active permissions for that role
     *     within the current tenant.
     *
     * @param  int           $userId
     * @return array<string> Permission strings.
     */
    private function loadPermissionsFromDb(int $userId): array
    {
        // a) Fetch user roles
        $userSql = "SELECT platform_role, accounting_role FROM users
                    WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL";
        $userRs  = $this->db->Execute($userSql, [$userId, $this->tenantId]);

        if ($userRs === false || $userRs->EOF) {
            return [];
        }

        $platformRole    = $userRs->fields['platform_role']    ?? null;
        $accountingRole  = $userRs->fields['accounting_role']  ?? null;

        // Collect all roles this user holds
        $roles = array_filter([$platformRole, $accountingRole]);
        if (empty($roles)) {
            return [];
        }

        // b) Fetch permissions for all roles
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $permSql = "SELECT DISTINCT permission FROM role_permissions
                    WHERE tenant_id = ?
                      AND role IN ({$placeholders})
                      AND deleted_at IS NULL";

        $params = array_merge([$this->tenantId], array_values($roles));
        $permRs = $this->db->Execute($permSql, $params);

        if ($permRs === false) {
            return [];
        }

        $permissions = [];
        while (!$permRs->EOF) {
            $permissions[] = $permRs->fields['permission'];
            $permRs->MoveNext();
        }

        return $permissions;
    }

    /**
     * Insert or restore a permission row (upsert via soft-delete awareness).
     *
     * @param  string $role
     * @param  string $permission
     * @param  int    $actorId
     * @return void
     */
    private function upsertPermission(string $role, string $permission, int $actorId): void
    {
        // Try to restore a soft-deleted row first
        $restoreSql = "UPDATE role_permissions
                       SET deleted_at = NULL, updated_at = NOW(), created_by = ?
                       WHERE tenant_id = ? AND role = ? AND permission = ?
                         AND deleted_at IS NOT NULL";
        $rs = $this->db->Execute($restoreSql, [$actorId, $this->tenantId, $role, $permission]);

        if ($rs !== false && $this->db->Affected_Rows() > 0) {
            return; // restored
        }

        // Insert new row (ignore if already active — unique constraint)
        $insertSql = "INSERT INTO role_permissions
                          (tenant_id, company_code, role, permission, created_by, created_at, updated_at)
                      VALUES (?, '01', ?, ?, ?, NOW(), NOW())
                      ON CONFLICT (tenant_id, role, permission) DO NOTHING";
        $this->db->Execute($insertSql, [$this->tenantId, $role, $permission, $actorId]);
    }

    /**
     * Soft-delete a permission row.
     *
     * @param  string $role
     * @param  string $permission
     * @return void
     */
    private function softDeletePermission(string $role, string $permission): void
    {
        $sql = "UPDATE role_permissions
                SET deleted_at = NOW(), updated_at = NOW()
                WHERE tenant_id = ? AND role = ? AND permission = ?
                  AND deleted_at IS NULL";
        $this->db->Execute($sql, [$this->tenantId, $role, $permission]);
    }

    /**
     * Publish rbac.invalidate:{user_id} for every user that holds the given role.
     *
     * All app instances subscribe to this channel and call invalidate() within 5 s.
     *
     * Requirement 2.7
     *
     * @param  string $role
     * @return void
     */
    private function publishInvalidationForRole(string $role): void
    {
        // Fetch all active user IDs that hold this role in the tenant
        $sql = "SELECT id FROM users
                WHERE tenant_id = ?
                  AND (platform_role = ? OR accounting_role = ?)
                  AND deleted_at IS NULL";
        $rs = $this->db->Execute($sql, [$this->tenantId, $role, $role]);

        if ($rs === false) {
            return;
        }

        while (!$rs->EOF) {
            $uid = (int) $rs->fields['id'];
            // Publish invalidation event; subscribers delete the cache key
            $this->redis->publish("rbac.invalidate:{$uid}", (string) $uid);
            $rs->MoveNext();
        }
    }

    /**
     * Get database connection (for controller access)
     * 
     * @return \ADOConnection
     */
    public function getDb()
    {
        return $this->db;
    }
}
