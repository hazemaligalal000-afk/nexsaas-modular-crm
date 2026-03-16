<?php
/**
 * Core/TenantResolver.php
 * Resolves the tenant context from the request subdomain.
 */

namespace Core;

class TenantResolver {
    private static $tenantData = null;

    /**
     * Resolves tenant from the Host header
     */
    public static function resolve() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $parts = explode('.', $host);
        
        // Example: company-a.mycrm.com -> subdomain is 'company-a'
        $subdomain = $parts[0] ?? null;

        if (!$subdomain || $subdomain === 'www' || $subdomain === 'app') {
            // Handle landing page or master admin
            return null;
        }

        self::$tenantData = self::fetchTenantFromDB($subdomain);

        if (!self::$tenantData) {
            header("HTTP/1.1 404 Not Found");
            echo json_encode(["error" => "Company workspace not found."]);
            exit;
        }

        return self::$tenantData;
    }

    private static function fetchTenantFromDB($subdomain) {
        $pdo = \Core\Database::getConnection();
        // Assuming a 'tenants' table exists in the master DB
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE subdomain = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$subdomain]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function getTenantId() {
        return self::$tenantData['id'] ?? null;
    }

    public static function getDbConfig() {
        return json_decode(self::$tenantData['db_config'] ?? '{}', true);
    }
}
