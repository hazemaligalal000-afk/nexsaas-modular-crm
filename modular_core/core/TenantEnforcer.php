<?php
/**
 * Core/TenantEnforcer.php
 * Handles Multi-Tenant architecture (Task 4)
 * Ensures every database query or module request is scoped to a specific organization.
 */

namespace Core;

class TenantEnforcer {
    private static $currentOrganizationId = null;

    /**
     * Initializes the tenant context from the API Request headers.
     * Throws an exception if the API Key/Tenant is invalid, shutting down the entire API early.
     */
    public static function initializeFromHeader($headers) {
        $apiKey = $headers['X-API-Key'] ?? null;
        if (!$apiKey) {
            throw new \Exception("Unauthorized: Missing X-API-Key", 401);
        }

        // Connect to master database and resolve the Key -> Tenant ID
        $pdo = \Core\Database::getConnection();
        $stmt = $pdo->prepare("SELECT organization_id FROM saas_api_keys WHERE api_key = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$apiKey]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new \Exception("Forbidden: Invalid API Key", 403);
        }

        self::$currentOrganizationId = $row['organization_id'];
    }

    public static function getTenantId() {
        if (!self::$currentOrganizationId) {
            throw new \Exception("Internal Server Error: Tenant context not initialized.", 500);
        }
        return self::$currentOrganizationId;
    }

    /**
     * Automatically injects 'organization_id = X' into any SQL WHERE clause.
     */
    public static function scopeQuery($sql) {
        $orgId = self::getTenantId();
        // Intelligent SQL parsing happens here. For demonstration, we simply append it.
        // In a real ORM, this is applied to every Doctrine/Eloquent Builder natively.
        if (stripos($sql, 'WHERE') !== false) {
            return str_ireplace('WHERE', "WHERE organization_id = {$orgId} AND", $sql);
        } else {
            return $sql . " WHERE organization_id = {$orgId}";
        }
    }
}
