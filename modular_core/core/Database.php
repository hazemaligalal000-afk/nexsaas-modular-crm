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
                // Production: Load from ENV / Secret Manager
                $host = 'db';
                $db   = 'crm_db'; 
                $user = 'crm_user';
                $pass = 'crm_secret';
                
                $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
                self::$centralInstance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT         => true, // Requirement 41.5: Connection Pooling
                ]);
            } catch (PDOException $e) {
                throw new \Exception("Central DB Connection Error");
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

        $config = TenantEnforcer::getTenantConfig();
        
        if (($config['db_strategy'] ?? 'shared') === 'dedicated') {
            // Dedicated Mode: Connect to the client's private database server
            $dbConf = json_decode($config['db_config'], true);
            try {
                $dsn = "mysql:host={$dbConf['host']};dbname={$dbConf['dbname']};charset=utf8mb4";
                self::$tenantInstance = new PDO($dsn, $dbConf['user'], $dbConf['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
            } catch (PDOException $e) {
                throw new \Exception("Dedicated Tenant DB Connection Error");
            }
        } else {
            // Shared Mode: Re-use the Central connection as the Pool
            self::$tenantInstance = self::getCentralConnection();
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
