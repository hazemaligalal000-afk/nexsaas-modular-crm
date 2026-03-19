<?php
namespace Core\Security;

use Core\BaseService;

/**
 * GDPR Service: Right-to-Erasure & Data Portability Management.
 * Requirement 43.5: Permanently remove all tenant records and revoke sessions within 24 hours.
 */
class GDPRService extends BaseService {
    
    public function purgeTenant(string $tenantId) {
        // Enforce cascading deletion of all tenant-specific records (Master Spec)
        $tables = ['leads', 'deals', 'vouchers', 'audit_logs', 'ai_usage_audit', 'tenants'];
        
        $this->db->StartTrans();
        foreach($tables as $table) {
            $sql = "DELETE FROM {$table} WHERE tenant_id = ?";
            $this->db->Execute($sql, [$tenantId]);
        }
        
        // Revoke all active session keys from Redis
        $redis = \Core\Performance\CacheManager::getInstance();
        $redis->delete("sessions:{$tenantId}:*");
        
        $this->db->CompleteTrans();
        
        \Core\AuditLogger::log($tenantId, 'SYSTEM', 'GDPR_PURGE', 'SUCCESS', "All tenant records permanently erased.", 0, []);
        return true;
    }

    public function anonymizePersonalData(string $userId) {
        // Anonymize user details following right-to-be-forgotten
        $sql = "UPDATE users SET email = 'deleted@nexsaas.com', name = 'Anonymized User', phone = NULL WHERE id = ?";
        return $this->db->Execute($sql, [$userId]);
    }
}
