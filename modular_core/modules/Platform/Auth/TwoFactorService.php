<?php
/**
 * Platform/Auth/TwoFactorService.php
 *
 * TOTP-based Two-Factor Authentication service.
 *
 * - TOTP secret generated via OTPHP\TOTP::create() (RFC 6238)
 * - Secret encrypted at rest with AES-256-CBC using APP_KEY env var
 * - 8 single-use backup codes (10 hex chars each), bcrypt-hashed for storage
 * - TOTP window: 1 step (±30 s tolerance)
 *
 * Requirements: 4.6, 33.1, 33.2, 33.3, 33.4, 33.5
 */

declare(strict_types=1);

namespace Platform\Auth;

use Core\BaseService;
use OTPHP\TOTP;

class TwoFactorService extends BaseService
{
    private const BACKUP_CODE_COUNT  = 8;
    private const BACKUP_CODE_BYTES  = 5;   // 5 bytes → 10 hex chars
    private const TOTP_WINDOW        = 1;   // ±1 step (30 s each side)
    private const CIPHER              = 'AES-256-CBC';

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Enroll a user in TOTP 2FA.
     *
     * Steps:
     *  1. Generate a new TOTP secret via OTPHP\TOTP::create().
     *  2. Encrypt the secret with APP_KEY and store in users.totp_secret.
     *  3. Set users.totp_enabled = false (not yet verified).
     *  4. Generate 8 backup codes, bcrypt-hash them, store in user_backup_codes.
     *  5. Return the raw secret, provisioning URI (for QR), and raw backup codes.
     *
     * Requirements: 33.1, 33.4
     *
     * @param  int    $userId
     * @param  string $tenantId
     * @return array{secret: string, qr_uri: string, backup_codes: string[]}
     * @throws \RuntimeException if user not found
     */
    public function enroll(int $userId, string $tenantId): array
    {
        $user = $this->fetchUser($userId, $tenantId);

        // 1. Generate TOTP
        $totp = TOTP::create();
        $totp->setLabel($user['email']);
        $totp->setIssuer('NexSaaS');

        $rawSecret = $totp->getSecret();

        // 2. Encrypt and persist secret; mark not yet enabled
        $encryptedSecret = $this->encryptSecret($rawSecret);
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->db->Execute(
            "UPDATE users
             SET totp_secret = ?, totp_enabled = FALSE, totp_verified_at = NULL, updated_at = ?
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$encryptedSecret, $now, $userId, $tenantId]
        );

        // 3. Delete any existing backup codes for this user/tenant
        $this->db->Execute(
            "DELETE FROM user_backup_codes WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        // 4. Generate and store backup codes
        $rawCodes = $this->generateBackupCodes(
            $userId,
            $tenantId,
            $user['company_code']
        );

        return [
            'secret'       => $rawSecret,
            'qr_uri'       => $totp->getProvisioningUri(),
            'backup_codes' => $rawCodes,
        ];
    }

    /**
     * Verify a TOTP code during the enrollment confirmation step.
     *
     * On success: sets users.totp_enabled = true and records totp_verified_at.
     *
     * Requirements: 33.1, 33.3
     *
     * @param  int    $userId
     * @param  string $tenantId
     * @param  string $code     6-digit TOTP code
     * @return bool
     */
    public function verify(int $userId, string $tenantId, string $code): bool
    {
        $user = $this->fetchUser($userId, $tenantId);

        if (empty($user['totp_secret'])) {
            return false;
        }

        $rawSecret = $this->decryptSecret($user['totp_secret']);
        $totp      = TOTP::createFromSecret($rawSecret);

        if (!$totp->verify($code, null, self::TOTP_WINDOW)) {
            return false;
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $this->db->Execute(
            "UPDATE users
             SET totp_enabled = TRUE, totp_verified_at = ?, updated_at = ?
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$now, $now, $userId, $tenantId]
        );

        return true;
    }

    /**
     * Verify a TOTP code (or backup code) at login time.
     *
     * Tries TOTP first; if that fails, checks backup codes.
     * A matched backup code is soft-deleted (deleted_at = NOW()).
     *
     * Requirements: 4.6, 33.5
     *
     * @param  int    $userId
     * @param  string $tenantId
     * @param  string $code     6-digit TOTP code or 10-char backup code
     * @return bool
     */
    public function verifyLogin(int $userId, string $tenantId, string $code): bool
    {
        $user = $this->fetchUser($userId, $tenantId);

        if (empty($user['totp_secret']) || !(bool) $user['totp_enabled']) {
            return false;
        }

        // Try TOTP first
        $rawSecret = $this->decryptSecret($user['totp_secret']);
        $totp      = TOTP::createFromSecret($rawSecret);

        if ($totp->verify($code, null, self::TOTP_WINDOW)) {
            return true;
        }

        // Fall back to backup codes
        return $this->verifyBackupCode($userId, $tenantId, $code);
    }

    /**
     * Check whether TOTP 2FA is fully enabled for a user.
     *
     * Requirements: 33.3
     *
     * @param  int    $userId
     * @param  string $tenantId
     * @return bool
     */
    public function isEnabled(int $userId, string $tenantId): bool
    {
        $rs = $this->db->Execute(
            "SELECT totp_enabled FROM users
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$userId, $tenantId]
        );

        if ($rs === false || $rs->EOF) {
            return false;
        }

        return (bool) $rs->fields['totp_enabled'];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch a user row (id, email, company_code, totp_secret, totp_enabled).
     *
     * @throws \RuntimeException if not found
     */
    private function fetchUser(int $userId, string $tenantId): array
    {
        $rs = $this->db->Execute(
            "SELECT id, email, company_code, totp_secret, totp_enabled
             FROM users
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$userId, $tenantId]
        );

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException('User not found.', 404);
        }

        return $rs->fields;
    }

    /**
     * Generate backup codes, bcrypt-hash them, and insert into user_backup_codes.
     *
     * @return string[]  Raw (unhashed) backup codes
     */
    private function generateBackupCodes(int $userId, string $tenantId, string $companyCode): array
    {
        $rawCodes = [];
        $now      = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $raw        = bin2hex(random_bytes(self::BACKUP_CODE_BYTES)); // 10 hex chars
            $rawCodes[] = $raw;
            $hash       = password_hash($raw, PASSWORD_BCRYPT);

            $this->db->Execute(
                "INSERT INTO user_backup_codes
                    (company_code, tenant_id, user_id, code_hash, created_at)
                 VALUES (?, ?, ?, ?, ?)",
                [$companyCode, $tenantId, $userId, $hash, $now]
            );
        }

        return $rawCodes;
    }

    /**
     * Check a submitted code against active backup codes.
     * Soft-deletes the matching code on success.
     */
    private function verifyBackupCode(int $userId, string $tenantId, string $code): bool
    {
        $rs = $this->db->Execute(
            "SELECT id, code_hash FROM user_backup_codes
             WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$userId, $tenantId]
        );

        if ($rs === false || $rs->EOF) {
            return false;
        }

        while (!$rs->EOF) {
            if (password_verify($code, $rs->fields['code_hash'])) {
                $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
                $this->db->Execute(
                    "UPDATE user_backup_codes SET deleted_at = ? WHERE id = ?",
                    [$now, (int) $rs->fields['id']]
                );
                return true;
            }
            $rs->MoveNext();
        }

        return false;
    }

    /**
     * Encrypt a TOTP secret using AES-256-CBC with the APP_KEY env var.
     *
     * Output format: base64(iv + ciphertext)
     */
    private function encryptSecret(string $plaintext): string
    {
        $key = $this->deriveKey();
        $iv  = random_bytes(openssl_cipher_iv_length(self::CIPHER));

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new \RuntimeException('Failed to encrypt TOTP secret.');
        }

        return base64_encode($iv . $ciphertext);
    }

    /**
     * Decrypt a TOTP secret previously encrypted by encryptSecret().
     */
    private function decryptSecret(string $encoded): string
    {
        $key    = $this->deriveKey();
        $ivLen  = openssl_cipher_iv_length(self::CIPHER);
        $raw    = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) <= $ivLen) {
            throw new \RuntimeException('Invalid encrypted TOTP secret.');
        }

        $iv         = substr($raw, 0, $ivLen);
        $ciphertext = substr($raw, $ivLen);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new \RuntimeException('Failed to decrypt TOTP secret.');
        }

        return $plaintext;
    }

    /**
     * Derive a 32-byte key from APP_KEY using SHA-256.
     */
    private function deriveKey(): string
    {
        $appKey = getenv('APP_KEY');

        if ($appKey === false || $appKey === '') {
            throw new \RuntimeException('APP_KEY environment variable is not set.');
        }

        return hash('sha256', $appKey, true); // raw binary, 32 bytes
    }
}
