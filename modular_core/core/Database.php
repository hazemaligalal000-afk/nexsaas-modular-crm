<?php
/**
 * Core/Database.php
 * Unified Database Handler for the Modular Core.
 */

namespace Core;

use PDO;
use PDOException;

class Database {
    private static $instance = null;

    /**
     * Get the singleton PDO connection
     */
    public static function getConnection() {
        if (self::$instance === null) {
            try {
                // In a real environment, we would load these from config.db.php or env
                // For this demo, we'll use the values found in the root config.inc.php
                // Note: Path depends on where this is called from, better to use absolute or defined constants.
                $host = 'db';
                $db   = 'crm_db';
                $user = 'crm_user';
                $pass = 'crm_secret';
                $port = '3306';
                $charset = 'utf8mb4';

                $dsn = "mysql:host=$host;dbname=$db;port=$port;charset=$charset";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];

                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new \Exception("Database Connection Error: " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    /**
     * Helper to run a scoped query automatically
     */
    public static function query($sql, $params = []) {
        $pdo = self::getConnection();
        
        // Proactively apply tenant scoping if TenantEnforcer is initialized
        if (class_exists('Core\TenantEnforcer')) {
            $sql = \Core\TenantEnforcer::scopeQuery($sql);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
