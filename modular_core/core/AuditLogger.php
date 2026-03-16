<?php
/**
 * Core/AuditLogger.php
 * Records all sensitive operations for compliance and security.
 */

namespace Core;

class AuditLogger {

    /**
     * Log an auditable action.
     */
    public static function log($tenantId, $userId, $action, $entityType = null, $entityId = null, $oldValues = null, $newValues = null) {
        try {
            $pdo = Database::getCentralConnection();
            $stmt = $pdo->prepare(
                "INSERT INTO audit_log (tenant_id, user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $tenantId,
                $userId,
                $action,
                $entityType,
                $entityId,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 500)
            ]);
        } catch (\Exception $e) {
            // Never let audit logging break the main flow
            error_log("AuditLogger Error: " . $e->getMessage());
        }
    }
}
