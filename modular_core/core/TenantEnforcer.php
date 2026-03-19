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
    private static $currentCompanyCode = '01'; // Default to Company 01
    private static $currentUserRole = null;
    private static $tenantConfig = null;

    /**
     * Initializes the tenant and company context from the Bearer Token in the Authorization header.
     */
    public static function initializeFromToken($authHeader) {
        if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
            return;
        }

        $token = substr($authHeader, 7);
        
        try {
            $payload = JwtManager::decode($token);
            
            self::$currentTenantId = $payload['tenant_id'];
            self::$currentCompanyCode = $payload['company_code'] ?? '01'; // Switched via JWT
            self::$currentUserRole = $payload['roles'] ?? [];
            
            // Fetch Tenant Config (Shared vs Dedicated) from Central DB cache/Redis
            self::resolveTenantConfig(self::$currentTenantId);

        } catch (\Exception $e) {
            throw new \Exception("Unauthorized: " . $e->getMessage(), 401);
        }
    }

    private static function resolveTenantConfig($tenantId) {
        $pdo = \Core\Database::getCentralConnection();
        $stmt = $pdo->prepare("SELECT db_strategy, db_config FROM tenants WHERE id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        self::$tenantConfig = $stmt->fetch();
    }

    public static function getTenantId(): ?string {
        return self::$currentTenantId;
    }

    public static function getCompanyCode() {
        return self::$currentCompanyCode;
    }

    public static function setCompanyCode($code) {
        self::$currentCompanyCode = $code;
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
