<?php
/**
 * Core/TenantEnforcer.php
 * Handles Multi-Tenant architecture (Task 4)
 * Ensures every database query or module request is scoped to a specific organization.
 */

namespace Core;

use Core\Auth\JwtManager;

class TenantEnforcer {
    private static $currentTenantId = null;
    private static $currentUserRole = null;
    private static $tenantConfig = null;

    /**
     * Initializes the tenant context from the Bearer Token in the Authorization header.
     */
    public static function initializeFromToken($authHeader) {
        if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
            // Some endpoints (like login) are public. 
            // The ModuleManager will handle RBAC check later.
            return;
        }

        $token = substr($authHeader, 7);
        
        try {
            $payload = JwtManager::decode($token);
            
            self::$currentTenantId = $payload['tenant_id'];
            self::$currentUserRole = $payload['role'];
            
            // Fetch Tenant Config (Shared vs Dedicated) from Central DB cache/Redis
            self::resolveTenantConfig(self::$currentTenantId);

        } catch (\Exception $e) {
            throw new \Exception("Unauthorized: " . $e->getMessage(), 401);
        }
    }

    private static function resolveTenantConfig($tenantId) {
        // In a real system, we consult Redis or a Central DB.
        // For now, we fetch from the 'tenants' table.
        $pdo = \Core\Database::getCentralConnection();
        $stmt = $pdo->prepare("SELECT db_strategy, db_config FROM tenants WHERE id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        self::$tenantConfig = $stmt->fetch();
    }

    public static function getTenantId() {
        return self::$currentTenantId;
    }

    public static function getRole() {
        return self::$currentUserRole;
    }

    public static function getTenantConfig() {
        return self::$tenantConfig;
    }

    /**
     * Scopes SQL queries by tenant_id for Shared Database mode.
     */
    public static function scopeQuery($sql) {
        $tenantId = self::getTenantId();
        if (!$tenantId) return $sql;

        // Only append scoping if we are in Shared mode
        if ((self::$tenantConfig['db_strategy'] ?? 'shared') === 'shared') {
            if (stripos($sql, 'WHERE') !== false) {
                return str_ireplace('WHERE', "WHERE tenant_id = '{$tenantId}' AND", $sql);
            } else {
                return $sql . " WHERE tenant_id = '{$tenantId}'";
            }
        }
        return $sql;
    }
}
