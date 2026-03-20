<?php

namespace ModularCore\Modules\Platform\Security;

use OTPHP\TOTP;
use Exception;

/**
 * TOTP Service: Two-Factor Authentication (Requirement Phase 0 / 35)
 * Orchestrates secure TOTP secret generation and verification.
 */
class TOTPService
{
    /**
     * Requirement 35: Generate TOTP Secret for User
     */
    public function generateSecret()
    {
        return str_replace('=', '', base64_encode(random_bytes(10)));
    }

    /**
     * Requirement 35: Generate Authenticator QR Code URL
     */
    public function getQRCodeUrl($userEmail, $secret, $issuer = 'NexSaaS CRM')
    {
        $totp = TOTP::create($secret);
        $totp->setLabel($userEmail);
        $totp->setIssuer($issuer);
        
        return $totp->getProvisioningUri();
    }

    /**
     * Requirement 35: Verify 6-digit TOTP Code
     */
    public function verify($secret, $code)
    {
        $totp = TOTP::create($secret);
        return $totp->verify($code);
    }

    /**
     * Security Lock: Revoke 2FA for User (Admin Action)
     */
    public function revoke($userId)
    {
        \DB::table('users')->where('id', $userId)->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false
        ]);
    }
}
