<?php
/**
 * Core/Database.php
 * Unified Database Handler for the Modular Core.
 */

namespace Core;

use PDO;
use PDOException;

class Database {
    private static $centralInstance = null;
    private static $tenantInstance = null;

    /**
     * Get the connection to the Central (Control Plane) Database.
     */
    public static function getCentralConnection() {
        if (self::$centralInstance === null) {
            try {
                // Production: Load from ENV (Requirement 0.1: No config leaks)
                $host = getenv('DB_HOST') ?: 'postgres';
                $port = getenv('DB_PORT') ?: '5432';
                $db   = getenv('DB_NAME') ?: 'nexsaas'; 
                $user = getenv('DB_USER') ?: 'nexsaas';
                $pass = getenv('DB_PASSWORD') ?: 'secret';
                
                $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=disable";
                self::$centralInstance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT         => true, // Requirement 41.5: Connection Pooling
                ]);
            } catch (PDOException $e) {
                throw new \Exception("Central DB Connection Error: " . $e->getMessage());
            }
        }
        return self::$centralInstance;
    }

    /**
     * Get the connection for the current Tenant context.
     * Switches dynamically between Shared Pool and Dedicated Client DB.
     */
    public static function getConnection() {
        if (self::$tenantInstance !== null) return self::$tenantInstance;

        // In the absence of a request context (like in a provisioner), default to Central.
        // Otherwise use the TenantEnforcer to resolve.
        if (class_exists('\\Core\\TenantEnforcer')) {
             $config = \Core\TenantEnforcer::getTenantConfig();
        
             if (($config['db_strategy'] ?? 'shared') === 'dedicated') {
                 // Dedicated Mode (Reserved for Phase 4: Enterprise Dedicated Clusters)
                 $dbConf = json_decode($config['db_config'], true);
                 try {
                     $dsn = "pgsql:host={$dbConf['host']};port={$dbConf['port']};dbname={$dbConf['dbname']}";
                     self::$tenantInstance = new PDO($dsn, $dbConf['user'], $dbConf['pass'], [
                         PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                     ]);
                 } catch (PDOException $e) {
                     throw new \Exception("Dedicated Tenant DB Connection Error");
                 }
             } else {
                 self::$tenantInstance = self::getCentralConnection();
             }
        } else {
             self::$tenantInstance = self::getCentralConnection();
        }

        // Enable PostgreSQL Row-Level Security (RLS) dynamically
        if (class_exists('\\Core\\TenantEnforcer')) {
            $currentTenantId = \Core\TenantEnforcer::getTenantId();
            if ($currentTenantId) {
                // Set the session variable used by RLS Policies
                self::$tenantInstance->exec("SET app.current_tenant_id = '{$currentTenantId}'");
            }
        }

        return self::$tenantInstance;
    }

    /**
     * Helper to run a tenant-scoped query automatically.
     */
    public static function query($sql, $params = []) {
        $pdo = self::getConnection();
        $sql = TenantEnforcer::scopeQuery($sql);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
