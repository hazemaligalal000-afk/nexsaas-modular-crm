<?php
/**
 * Auth/MFAService.php
 * 
 * CORE → ADVANCED: Multi-Factor Authentication (MFA)
 */

declare(strict_types=1);

namespace Modules\Auth;

use Core\BaseService;

class MFAService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Generate 2FA code and dispatch via preferred channel
     * Channels: 'email', 'waba', 'sms'
     */
    public function triggerMFA(int $userId, string $channel = 'email'): array
    {
        // 1. Generate 6-digit TOTP
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // 2. Persistent log for verification
        $this->db->Execute(
            "INSERT INTO mfa_codes (user_id, code, expires_at, channel) 
             VALUES (?, ?, ?, ?)
             ON CONFLICT (user_id) DO UPDATE SET 
                code = EXCLUDED.code, 
                expires_at = EXCLUDED.expires_at, 
                channel = EXCLUDED.channel",
            [$userId, $code, $expiry, $channel]
        );

        // 3. FIRE EVENT: MFA Dispatch (Omnichannel/Email Listens)
        $this->fireEvent('auth.mfa_dispatch', [
            'user_id' => $userId,
            'code' => $code,
            'channel' => $channel
        ]);

        return ['status' => 'queued', 'expires_at' => $expiry];
    }

    /**
     * Verify the 2FA code
     */
    public function verifyCode(int $userId, string $code): bool
    {
        $sql = "SELECT code FROM mfa_codes WHERE user_id = ? AND code = ? AND expires_at > NOW()";
        $valid = $this->db->GetOne($sql, [$userId, $code]);

        if ($valid) {
            // Mark code as used
            $this->db->Execute("DELETE FROM mfa_codes WHERE user_id = ?", [$userId]);
            return true;
        }

        return false;
    }
}
