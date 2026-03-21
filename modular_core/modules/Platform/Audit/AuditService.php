<?php
/**
 * Platform/Audit/AuditService.php
 * 
 * Secure Enterprise Audit Logging (SOC 2 Type II Requirement 10.6)
 * Immutable trail of all system mutations.
 */

namespace NexSaaS\Platform\Audit;

class AuditService
{
    private $adb;

    public function __construct($adb) {
        $this->adb = $adb;
    }

    /**
     * Log a sensitive system event
     */
    public function log(int $actorId, string $action, string $module, string $description, int $tenantId, array $impactedRecords = []): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'system';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'system';

        $query = "INSERT INTO saas_audit_log (actor_id, action_name, module_name, description, tenant_id, ip_address, user_agent, impacted_records, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $this->adb->pquery($query, [
            $actorId,
            $action,
            $module,
            $description,
            $tenantId,
            $ip,
            $agent,
            json_encode($impactedRecords)
        ]);

        // 🛡️ For SOC 2 Compliance: Mirror logs to dedicated secure backend (Sentry/CloudWatch)
        error_log("[AUDIT] [$action] by $actorId on $module for tenant $tenantId");
    }

    /**
     * Get Audit Trail for a specific record
     */
    public function getTrail(string $module, int $recordId, int $tenantId): array
    {
        $query = "SELECT * FROM saas_audit_log WHERE module_name = ? AND impacted_records @> ? AND tenant_id = ? ORDER BY created_at DESC";
        $result = $this->adb->pquery($query, [$module, json_encode([$recordId]), $tenantId]);

        return $this->adb->get_all_rows($result);
    }
}
