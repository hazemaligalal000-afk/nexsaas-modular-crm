<?php
/**
 * Platform/Auth/JwtKeyRotationService.php
 *
 * JWT RS256 key rotation service.
 *
 * - Rotates signing keys on a 90-day schedule
 * - Keeps previous key pair for overlap window so active sessions remain valid
 * - Redis key jwt:key_rotation:last_rotated stores Unix timestamp of last rotation
 * - During verification: tries current public key first, falls back to previous key
 *
 * Requirements: 42.7
 */

declare(strict_types=1);

namespace Platform\Auth;

use Core\BaseService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;

class JwtKeyRotationService extends BaseService
{
    private const ROTATION_INTERVAL_DAYS = 90;
    private const ROTATION_INTERVAL_SECS = self::ROTATION_INTERVAL_DAYS * 86400;

    private const REDIS_LAST_ROTATED_KEY = 'jwt:key_rotation:last_rotated';

    private const KEYS_DIR         = __DIR__ . '/../../../keys/';
    private const PRIVATE_KEY_PATH = self::KEYS_DIR . 'jwt_private.pem';
    private const PUBLIC_KEY_PATH  = self::KEYS_DIR . 'jwt_public.pem';
    private const PREV_PRIVATE_KEY = self::KEYS_DIR . 'jwt_private_prev.pem';
    private const PREV_PUBLIC_KEY  = self::KEYS_DIR . 'jwt_public_prev.pem';

    /** @var object Redis client */
    private object $redis;

    /**
     * @param \ADOConnection $db    ADOdb connection
     * @param object         $redis Redis client
     */
    public function __construct($db, object $redis)
    {
        parent::__construct($db);
        $this->redis = $redis;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Rotate JWT signing keys if 90 days have elapsed since last rotation.
     *
     * Steps:
     *  1. Check shouldRotate(); return early if not needed.
     *  2. Delete any existing _prev key files.
     *  3. Move current keys to _prev.
     *  4. Generate a new RS256 key pair.
     *  5. Update Redis jwt:key_rotation:last_rotated.
     *  6. Write audit log entry.
     *
     * Requirements: 42.7
     *
     * @return void
     * @throws \RuntimeException if key generation or file operations fail
     */
    public function rotate(): void
    {
        if (!$this->shouldRotate()) {
            return;
        }

        $this->ensureKeysDirectory();

        // Step 2: Remove stale _prev keys (safe to ignore if absent)
        foreach ([self::PREV_PRIVATE_KEY, self::PREV_PUBLIC_KEY] as $prevPath) {
            if (file_exists($prevPath)) {
                if (!unlink($prevPath)) {
                    throw new \RuntimeException("Failed to delete previous key: {$prevPath}");
                }
            }
        }

        // Step 3: Move current keys → _prev (only if current keys exist)
        if (file_exists(self::PRIVATE_KEY_PATH)) {
            if (!rename(self::PRIVATE_KEY_PATH, self::PREV_PRIVATE_KEY)) {
                throw new \RuntimeException('Failed to archive current private key to _prev.');
            }
        }
        if (file_exists(self::PUBLIC_KEY_PATH)) {
            if (!rename(self::PUBLIC_KEY_PATH, self::PREV_PUBLIC_KEY)) {
                // Attempt rollback of private key move
                if (file_exists(self::PREV_PRIVATE_KEY)) {
                    rename(self::PREV_PRIVATE_KEY, self::PRIVATE_KEY_PATH);
                }
                throw new \RuntimeException('Failed to archive current public key to _prev.');
            }
        }

        // Step 4: Generate new key pair
        try {
            $this->generateKeyPair();
        } catch (\RuntimeException $e) {
            // Rollback: restore _prev keys to current position
            if (file_exists(self::PREV_PRIVATE_KEY)) {
                rename(self::PREV_PRIVATE_KEY, self::PRIVATE_KEY_PATH);
            }
            if (file_exists(self::PREV_PUBLIC_KEY)) {
                rename(self::PREV_PUBLIC_KEY, self::PUBLIC_KEY_PATH);
            }
            throw new \RuntimeException('Key generation failed; rolled back to previous keys. ' . $e->getMessage(), 0, $e);
        }

        // Step 5: Update Redis timestamp
        $now = time();
        $this->redis->set(self::REDIS_LAST_ROTATED_KEY, (string) $now);

        // Step 6: Audit log
        $this->writeAuditLog($now);
    }

    /**
     * Verify a JWT using the current public key, falling back to the previous key.
     *
     * Requirements: 42.7
     *
     * @param  string $jwt  Signed JWT string
     * @return object       Decoded payload
     * @throws \RuntimeException if both keys fail to verify the token
     */
    public function verifyToken(string $jwt): object
    {
        // Try current public key first
        if (file_exists(self::PUBLIC_KEY_PATH)) {
            try {
                $pem = file_get_contents(self::PUBLIC_KEY_PATH);
                return JWT::decode($jwt, new Key($pem, 'RS256'));
            } catch (SignatureInvalidException $e) {
                // Signature mismatch — try previous key below
            } catch (\UnexpectedValueException $e) {
                // Malformed token or other decode error — try previous key
            }
        }

        // Fall back to previous public key (overlap window)
        if (file_exists(self::PREV_PUBLIC_KEY)) {
            try {
                $pem = file_get_contents(self::PREV_PUBLIC_KEY);
                return JWT::decode($jwt, new Key($pem, 'RS256'));
            } catch (ExpiredException $e) {
                throw new \RuntimeException('Token has expired.', 401, $e);
            } catch (BeforeValidException $e) {
                throw new \RuntimeException('Token is not yet valid.', 401, $e);
            } catch (\UnexpectedValueException $e) {
                throw new \RuntimeException('Token verification failed: ' . $e->getMessage(), 401, $e);
            }
        }

        throw new \RuntimeException('Token verification failed: no valid public key available.', 401);
    }

    /**
     * Returns true if more than 90 days have elapsed since the last key rotation
     * (or if no rotation has ever been recorded).
     *
     * Requirements: 42.7
     *
     * @return bool
     */
    public function shouldRotate(): bool
    {
        $lastRotated = $this->redis->get(self::REDIS_LAST_ROTATED_KEY);

        if ($lastRotated === false || $lastRotated === null || $lastRotated === '') {
            // Never rotated — rotation is needed
            return true;
        }

        return (time() - (int) $lastRotated) >= self::ROTATION_INTERVAL_SECS;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a new RS256 2048-bit key pair and write to the keys directory.
     *
     * @return void
     * @throws \RuntimeException on OpenSSL failure
     */
    private function generateKeyPair(): void
    {
        $config = [
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        if ($res === false) {
            throw new \RuntimeException('Failed to generate RSA key pair: ' . openssl_error_string());
        }

        openssl_pkey_export($res, $privateKey);
        $details   = openssl_pkey_get_details($res);
        $publicKey = $details['key'];

        file_put_contents(self::PRIVATE_KEY_PATH, $privateKey);
        chmod(self::PRIVATE_KEY_PATH, 0600);

        file_put_contents(self::PUBLIC_KEY_PATH, $publicKey);
        chmod(self::PUBLIC_KEY_PATH, 0644);
    }

    /**
     * Ensure the keys directory exists with correct permissions.
     *
     * @return void
     */
    private function ensureKeysDirectory(): void
    {
        $dir = self::KEYS_DIR;
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                throw new \RuntimeException("Failed to create keys directory: {$dir}");
            }
        }
    }

    /**
     * Write a rotation event to the audit_log table.
     *
     * @param  int $rotatedAt Unix timestamp of the rotation
     * @return void
     */
    private function writeAuditLog(int $rotatedAt): void
    {
        $rotatedAtStr = date('Y-m-d H:i:s', $rotatedAt);

        // Best-effort: do not throw if audit log write fails
        try {
            $this->db->Execute(
                "INSERT INTO audit_log (event_type, event_data, created_at)
                 VALUES (?, ?, ?)",
                [
                    'jwt.key_rotation',
                    json_encode(['rotated_at' => $rotatedAtStr, 'interval_days' => self::ROTATION_INTERVAL_DAYS]),
                    $rotatedAtStr,
                ]
            );
        } catch (\Throwable $e) {
            // Log to stderr but do not abort rotation
            error_log('[JwtKeyRotationService] Audit log write failed: ' . $e->getMessage());
        }
    }
}
