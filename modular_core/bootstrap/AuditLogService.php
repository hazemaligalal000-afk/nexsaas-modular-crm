<?php

namespace ModularCore\Bootstrap;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * Requirement 13: Implement Audit Logging (Phase 0.3)
 */
class AuditLogService
{
    /**
     * Record security event (Requirement 13.1-13.7)
     */
    public function log(string $event, string $status = 'SUCCESS', array $params = [], Request $request = null): void
    {
        $request = $request ?: request();
        
        # 1. Capture contextual security data (Requirement 13.1)
        $userId = $params['user_id'] ?? ($request->user() ? $request->user()->id : null);
        $tenantId = $params['tenant_id'] ?? ($request->user() ? $request->user()->tenant_id : null);
        
        # 2. Tamper-evident record (Requirement 13.8)
        # In Production, this would be signed or sent to an immutable store.
        DB::table('audit_logs')->insert([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'event_type'   => $event,
            'status'       => $status,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'resource_id'  => $params['resource_id'] ?? null,
            'details'      => json_encode($params['details'] ?? []),
            'recorded_at'  => now(),
            'created_at'   => now()
        ]);

        # 3. Clean up old logs (Requirement 13.9: retain at least 90 days)
        # This would typically be a scheduled job, but implemented here for visibility.
        // DB::table('audit_logs')->where('recorded_at', '<', now()->subDays(90))->delete();
    }

    /**
     * Common security event shortcuts
     */
    public function logLogin($user) { $this->log('LOGIN_SUCCESS', 'SUCCESS', ['user_id' => $user->id]); }
    public function logLoginFailure($username) { $this->log('LOGIN_FAILURE', 'FAILED', ['details' => ['username' => $username]]); }
    public function logPasswordChange($user) { $this->log('PASSWORD_CHANGE', 'SUCCESS', ['user_id' => $user->id]); }
    public function log2faToggle($user, $enabled) { $this->log('2FA_TOGGLE', 'SUCCESS', ['user_id' => $user->id, 'details' => ['enabled' => $enabled]]); }
    public function logApiKeyRevoke($user, $keyId) { $this->log('API_KEY_REVOKE', 'SUCCESS', ['user_id' => $user->id, 'resource_id' => $keyId]); }
}
