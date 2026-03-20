<?php

namespace ModularCore\Bootstrap;

use OTPHP\TOTP;
use Exception;

/**
 * Requirement 11: Implement Two-Factor Authentication (Phase 0.3)
 */
class TOTPService
{
    /**
     * Requirement 11.2: Generate QR code for setup
     */
    public function generateSecret(string $userEmail): array
    {
        $totp = TOTP::create();
        $totp->setLabel($userEmail);
        $totp->setIssuer('NexSaaS Platform');

        return [
            'secret' => $totp->getSecret(),
            'qr_url' => $totp->getQrCodeUri(
                'https://api.qrserver.com/v1/create-qr-code/?data=',
                '{PROVISIONING_URI}'
            )
        ];
    }

    /**
     * Requirement 11.4: Require valid TOTP code
     */
    public function verify(string $secret, string $code): bool
    {
        $totp = TOTP::at($secret);
        return $totp->verify($code);
    }

    /**
     * Requirement 11.3: Generate backup codes
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf('%04d-%04d', rand(1000, 9999), rand(1000, 9999));
        }
        return $codes;
    }

    /**
     * Requirement 11.8: Invalidate backup code after use
     */
    public function useBackupCode(string $userId, string $code): bool
    {
        $storedCodes = \DB::table('user_2fa_backups')->where('user_id', $userId)->pluck('code')->toArray();
        if (in_array($code, $storedCodes)) {
             \DB::table('user_2fa_backups')->where('user_id', $userId)->where('code', $code)->delete();
             return true;
        }
        return false;
    }
}
