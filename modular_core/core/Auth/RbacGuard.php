<?php
/**
 * Core/Auth/RbacGuard.php
 * Enterprise Role-Based Access Control Engine.
 * Supports multi-role users with merged permission matrices.
 */

namespace Core\Auth;

use Core\Database;
use Core\TenantEnforcer;

class RbacGuard {
    private static $permissionsCache = [];

    /**
     * Load and merge all permissions for a user across their assigned roles.
     * Uses Redis cache in production.
     */
    public static function loadPermissions($userId, $tenantId) {
        $cacheKey = "rbac:{$tenantId}:{$userId}";

        // Check in-memory cache first
        if (isset(self::$permissionsCache[$cacheKey])) {
            return self::$permissionsCache[$cacheKey];
        }

        // In production: Check Redis cache
        // $cached = Redis::get($cacheKey);
        // if ($cached) return json_decode($cached, true);

        $pdo = Database::getCentralConnection();
        $stmt = $pdo->prepare(
            "SELECT r.permissions_json 
             FROM user_roles ur
             JOIN roles r ON ur.role_id = r.id
             JOIN users u ON ur.user_id = u.id
             WHERE ur.user_id = ? AND u.tenant_id = ? AND r.tenant_id = ?"
        );
        $stmt->execute([$userId, $tenantId, $tenantId]);
        $rows = $stmt->fetchAll();

        // Merge permissions from all assigned roles (OR logic — most permissive wins)
        $merged = [];
        foreach ($rows as $row) {
            $perms = json_decode($row['permissions_json'], true);
            foreach ($perms as $module => $actions) {
                if (!isset($merged[$module])) {
                    $merged[$module] = [];
                }
                foreach ($actions as $action => $allowed) {
                    // If ANY role grants the permission, user has it
                    if ($allowed === true) {
                        $merged[$module][$action] = true;
                    } elseif (!isset($merged[$module][$action])) {
                        $merged[$module][$action] = false;
                    }
                }
            }
        }

        // Cache result (in-memory + Redis)
        self::$permissionsCache[$cacheKey] = $merged;
        // Redis::setex($cacheKey, 300, json_encode($merged)); // 5 min TTL

        return $merged;
    }

    /**
     * Check if the current user has permission for a specific module action.
     * Usage: RbacGuard::can('leads', 'create')
     */
    public static function can($module, $action) {
        $userId   = JwtManager::getCurrentUserId();
        $tenantId = TenantEnforcer::getTenantId();

        if (!$userId || !$tenantId) return false;

        $permissions = self::loadPermissions($userId, $tenantId);
        return ($permissions[$module][$action] ?? false) === true;
    }

    /**
     * Enforce permission or throw 403.
     * Usage: RbacGuard::enforce('leads', 'delete');
     */
    public static function enforce($module, $action) {
        if (!self::can($module, $action)) {
            throw new \Exception(
                "Forbidden: You do not have '{$action}' permission on '{$module}'.", 
                403
            );
        }
    }

    /**
     * Get the full merged permissions matrix for frontend rendering.
     * Called by GET /api/auth/me to power dynamic UI.
     */
    public static function getPermissionsMatrix() {
        $userId   = JwtManager::getCurrentUserId();
        $tenantId = TenantEnforcer::getTenantId();
        return self::loadPermissions($userId, $tenantId);
    }

    /**
     * Invalidate cache when roles/permissions change.
     */
    public static function invalidateCache($userId, $tenantId) {
        $cacheKey = "rbac:{$tenantId}:{$userId}";
        unset(self::$permissionsCache[$cacheKey]);
        // Redis::del($cacheKey);
    }
}
