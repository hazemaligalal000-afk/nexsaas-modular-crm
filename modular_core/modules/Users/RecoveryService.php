<?php
/**
 * Users/RecoveryService.php
 * 
 * CORE → ADVANCED: Secure 2FA Account Recovery Engine
 */

declare(strict_types=1);

namespace Modules\Users;

use Core\BaseService;

class RecoveryService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Generate 8-digit secure recovery codes (static)
     * Used by: Individual users to regain access if 2FA device is lost
     */
    public function generateBackupCodes(int $userId): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = bin2hex(random_bytes(4)); // 8 chars
        }

        // 1. Persistent storage (Hashed for security)
        $this->db->Execute(
            "UPDATE user_preferences SET backup_codes = ? WHERE user_id = ?",
            [json_encode(array_map('password_hash', $codes, array_fill(0, 10, PASSWORD_DEFAULT))), $userId]
        );

        return $codes;
    }

    /**
     * Verify a backup code
     */
    public function verifyBackupCode(int $userId, string $code): bool
    {
        $sql = "SELECT backup_codes FROM user_preferences WHERE user_id = ?";
        $stored = json_decode($this->db->GetOne($sql, [$userId]) ?? '[]', true);

        foreach ($stored as $idx => $hash) {
            if (password_verify($code, $hash)) {
                // Consume the code
                unset($stored[$idx]);
                $this->db->Execute(
                    "UPDATE user_preferences SET backup_codes = ? WHERE user_id = ?",
                    [json_encode(array_values($stored)), $userId]
                );
                return true;
            }
        }

        return false;
    }
}
