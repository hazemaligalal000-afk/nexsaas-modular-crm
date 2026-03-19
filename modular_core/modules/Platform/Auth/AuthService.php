<?php
/**
 * Platform/Auth/AuthService.php
 *
 * JWT RS256 authentication service.
 *
 * - Access tokens: RS256, 15-minute expiry
 * - Refresh tokens: random_bytes(32) → bin2hex, stored in Redis
 *   key: refresh:{sha256_hash} → JSON {user_id, tenant_id, expires_at}, TTL 604800 (7 days)
 * - Session metadata: session:{tenant_id}:{user_id} → JSON {user_id, tenant_id, role, login_at}, TTL 604800
 * - Rate limiting: key ratelimit:auth:{ip}, counter, TTL 60s
 *   Block key: ratelimit:block:{ip}, TTL 900s (15 min) after >10 attempts
 * - Password hashing: bcrypt cost 12
 *
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 42.1, 42.5, 42.6
 */

declare(strict_types=1);

namespace Platform\Auth;

use Core\BaseService;
use Firebase\JWT\JWT;

class RateLimitException extends \RuntimeException {}

class AuthService extends BaseService
{
    private const ACCESS_TOKEN_TTL  = 900;       // 15 minutes in seconds
    private const REFRESH_TOKEN_TTL = 604800;    // 7 days in seconds
    private const RATE_LIMIT_MAX    = 10;        // max attempts per minute
    private const RATE_LIMIT_TTL    = 60;        // rate limit window in seconds
    private const BLOCK_TTL         = 900;       // block duration in seconds (15 min)

    private const PRIVATE_KEY_PATH  = __DIR__ . '/../../../keys/jwt_private.pem';

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
     * Authenticate a user and issue JWT + refresh token.
     *
     * Steps:
     *  1. Check Redis block key — throw RateLimitException if blocked.
     *  2. Increment rate-limit counter; set block key if > 10.
     *  3. Fetch user by email + tenant_id (adodb).
     *  4. Verify bcrypt password hash (cost 12).
     *  5. Issue RS256 access token (15 min) and refresh token (7 days).
     *  6. Store session metadata in Redis session:{tenant_id}:{user_id}.
     *
     * Requirements: 4.1, 4.2, 4.3, 42.1, 42.5, 42.6
     *
     * @param  string $email
     * @param  string $password
     * @param  string $ip
     * @param  string $tenantId
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     * @throws RateLimitException on rate limit exceeded
     * @throws \RuntimeException  on auth failure
     */
    public function login(string $email, string $password, string $ip, string $tenantId): array
    {
        // 1. Check block key
        $this->checkRateLimit($ip);

        // 2. Fetch user by email + tenant_id
        $sql = "SELECT id, email, password_hash, platform_role, accounting_role,
                       company_code, is_active, locked_until
                FROM users
                WHERE email = ? AND tenant_id = ? AND deleted_at IS NULL";
        $rs = $this->db->Execute($sql, [$email, $tenantId]);

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException('Invalid credentials.', 401);
        }

        $user = $rs->fields;

        // Check account active
        if (!(bool) $user['is_active']) {
            throw new \RuntimeException('Account is disabled.', 401);
        }

        // Check locked_until
        if (!empty($user['locked_until'])) {
            $lockedUntil = new \DateTimeImmutable($user['locked_until']);
            if ($lockedUntil > new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
                throw new \RuntimeException('Account is temporarily locked.', 401);
            }
        }

        // 3. Verify password (bcrypt cost 12)
        if (!password_verify($password, $user['password_hash'])) {
            throw new \RuntimeException('Invalid credentials.', 401);
        }

        // Update last_login_at and reset failed_login_count
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $this->db->Execute(
            "UPDATE users SET last_login_at = ?, failed_login_count = 0, updated_at = ? WHERE id = ?",
            [$now, $now, $user['id']]
        );

        // 4. Issue tokens
        $accessToken  = $this->buildJwt($user, $tenantId);
        $refreshToken = $this->issueRefreshToken((int) $user['id'], $tenantId);

        // 5. Store session metadata: session:{tenant_id}:{user_id} — Requirement 4.4
        $role = $user['platform_role'] ?? $user['accounting_role'] ?? '';
        $this->storeSession($tenantId, (string) $user['id'], $role, $now);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => self::ACCESS_TOKEN_TTL,
        ];
    }

    /**
     * Rotate refresh token: validate old token, issue new access + refresh pair.
     *
     * Requirements: 4.3, 4.4
     *
     * @param  string $refreshToken  Raw refresh token (hex string)
     * @param  string $tenantId
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     * @throws \RuntimeException on invalid/expired token
     */
    public function refresh(string $refreshToken, string $tenantId): array
    {
        $hash     = hash('sha256', $refreshToken);
        $redisKey = "refresh:{$hash}";

        $stored = $this->redis->get($redisKey);
        if ($stored === false || $stored === null) {
            throw new \RuntimeException('Invalid or expired refresh token.', 401);
        }

        $data = json_decode($stored, true);
        if (!is_array($data) || empty($data['user_id'])) {
            throw new \RuntimeException('Invalid or expired refresh token.', 401);
        }

        // Validate tenant matches
        if (($data['tenant_id'] ?? '') !== $tenantId) {
            throw new \RuntimeException('Invalid or expired refresh token.', 401);
        }

        // Check expiry
        if (!empty($data['expires_at']) && time() > $data['expires_at']) {
            $this->redis->del($redisKey);
            throw new \RuntimeException('Invalid or expired refresh token.', 401);
        }

        // Rotate: delete old key immediately (prevent replay)
        $this->redis->del($redisKey);

        // Fetch user for new token claims
        $sql = "SELECT id, email, platform_role, accounting_role, company_code, is_active
                FROM users
                WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL";
        $rs = $this->db->Execute($sql, [(int) $data['user_id'], $tenantId]);

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException('User not found.', 401);
        }

        $user = $rs->fields;

        if (!(bool) $user['is_active']) {
            throw new \RuntimeException('Account is disabled.', 401);
        }

        $accessToken     = $this->buildJwt($user, $tenantId);
        $newRefreshToken = $this->issueRefreshToken((int) $user['id'], $tenantId);

        // Refresh session TTL
        $now  = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $role = $user['platform_role'] ?? $user['accounting_role'] ?? '';
        $this->storeSession($tenantId, (string) $user['id'], $role, $now);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in'    => self::ACCESS_TOKEN_TTL,
        ];
    }

    /**
     * Invalidate a refresh token and remove session from Redis (logout).
     *
     * Requirements: 4.5
     *
     * @param  string $refreshToken  Raw refresh token (hex string)
     * @param  string $userId
     * @param  string $tenantId
     * @return bool
     */
    public function logout(string $refreshToken, string $userId = '', string $tenantId = ''): bool
    {
        $hash     = hash('sha256', $refreshToken);
        $redisKey = "refresh:{$hash}";

        // If we have stored data, extract userId/tenantId from it if not provided
        if ($userId === '' || $tenantId === '') {
            $stored = $this->redis->get($redisKey);
            if ($stored !== false && $stored !== null) {
                $data     = json_decode($stored, true);
                $userId   = $userId   !== '' ? $userId   : (string) ($data['user_id']   ?? '');
                $tenantId = $tenantId !== '' ? $tenantId : (string) ($data['tenant_id'] ?? '');
            }
        }

        // Delete refresh token
        $this->redis->del($redisKey);

        // Delete session — Requirement 4.5
        if ($userId !== '' && $tenantId !== '') {
            $this->redis->del("session:{$tenantId}:{$userId}");
        }

        return true;
    }

    /**
     * Check rate limit for the given IP.
     *
     * Redis key ratelimit:auth:{ip}, increment counter with TTL 60s.
     * If count > 10, set block key ratelimit:block:{ip} with TTL 900s
     * and throw RateLimitException.
     *
     * Requirements: 42.5, 42.6
     *
     * @param  string $ip
     * @return void
     * @throws RateLimitException if IP is blocked or limit exceeded
     */
    public function checkRateLimit(string $ip): void
    {
        // Check existing block
        if ($this->isBlocked($ip)) {
            throw new RateLimitException('Too many attempts. Try again in 15 minutes.', 429);
        }

        $rateLimitKey = "ratelimit:auth:{$ip}";
        $attempts     = (int) $this->redis->incr($rateLimitKey);

        if ($attempts === 1) {
            // Set TTL only on first increment
            $this->redis->expire($rateLimitKey, self::RATE_LIMIT_TTL);
        }

        if ($attempts > self::RATE_LIMIT_MAX) {
            $this->redis->setex("ratelimit:block:{$ip}", self::BLOCK_TTL, '1');
            throw new RateLimitException('Too many attempts. Try again in 15 minutes.', 429);
        }
    }

    /**
     * Check whether the given IP is currently blocked.
     *
     * Requirements: 42.5, 42.6
     *
     * @param  string $ip
     * @return bool
     */
    public function isBlocked(string $ip): bool
    {
        $val = $this->redis->get("ratelimit:block:{$ip}");
        return $val !== false && $val !== null;
    }

    /**
     * Build and sign an RS256 JWT for the given user.
     *
     * Payload: {sub, tenant_id, role, iat, exp: now+900}
     *
     * Requirements: 42.1
     *
     * @param  array  $user     User row from DB
     * @param  string $tenantId Current tenant UUID
     * @return string           Signed JWT
     */
    public function buildJwt(array $user, string $tenantId): string
    {
        $privateKey = $this->loadPrivateKey();

        $now  = time();
        $role = $user['platform_role'] ?? $user['accounting_role'] ?? '';

        $payload = [
            'sub'          => (int) $user['id'],
            'tenant_id'    => $tenantId,
            'company_code' => $user['company_code'] ?? '',
            'role'         => $role,
            'iat'          => $now,
            'exp'          => $now + self::ACCESS_TOKEN_TTL,
        ];

        return JWT::encode($payload, $privateKey, 'RS256');
    }

    // -------------------------------------------------------------------------
    // Key management
    // -------------------------------------------------------------------------

    /**
     * Generate an RS256 key pair and persist to disk.
     * Keys are stored at modular_core/keys/jwt_private.pem and jwt_public.pem.
     *
     * @return void
     * @throws \RuntimeException if key generation fails
     */
    public function generateKeyPair(): void
    {
        $keysDir = dirname(self::PRIVATE_KEY_PATH);
        if (!is_dir($keysDir)) {
            mkdir($keysDir, 0700, true);
        }

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

        $pubPath = dirname(self::PRIVATE_KEY_PATH) . '/jwt_public.pem';
        file_put_contents(self::PRIVATE_KEY_PATH, $privateKey);
        chmod(self::PRIVATE_KEY_PATH, 0600);

        file_put_contents($pubPath, $publicKey);
        chmod($pubPath, 0644);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Issue a refresh token, store in Redis, and return the raw token.
     *
     * Redis key: refresh:{sha256(token)} → JSON {user_id, tenant_id, expires_at}, TTL 604800
     *
     * Requirements: 4.2, 4.3
     *
     * @param  int    $userId
     * @param  string $tenantId
     * @return string Raw hex refresh token (256-bit = 32 bytes = 64 hex chars)
     */
    private function issueRefreshToken(int $userId, string $tenantId): string
    {
        $rawToken  = bin2hex(random_bytes(32)); // 256-bit opaque token
        $hash      = hash('sha256', $rawToken);
        $redisKey  = "refresh:{$hash}";
        $expiresAt = time() + self::REFRESH_TOKEN_TTL;

        $payload = json_encode([
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'expires_at' => $expiresAt,
        ]);

        $this->redis->setex($redisKey, self::REFRESH_TOKEN_TTL, $payload);

        return $rawToken;
    }

    /**
     * Store session metadata in Redis.
     *
     * Key: session:{tenant_id}:{user_id} → JSON {user_id, tenant_id, role, login_at}, TTL 7 days
     *
     * Requirement 4.4
     *
     * @param  string $tenantId
     * @param  string $userId
     * @param  string $role
     * @param  string $loginAt  UTC datetime string
     * @return void
     */
    private function storeSession(string $tenantId, string $userId, string $role, string $loginAt): void
    {
        $sessionKey = "session:{$tenantId}:{$userId}";
        $payload    = json_encode([
            'user_id'   => $userId,
            'tenant_id' => $tenantId,
            'role'      => $role,
            'login_at'  => $loginAt,
        ]);

        $this->redis->setex($sessionKey, self::REFRESH_TOKEN_TTL, $payload);
    }

    /**
     * Load the RSA private key from disk.
     *
     * @return \OpenSSLAsymmetricKey
     * @throws \RuntimeException if key file is missing or invalid
     */
    private function loadPrivateKey(): \OpenSSLAsymmetricKey
    {
        if (!file_exists(self::PRIVATE_KEY_PATH)) {
            throw new \RuntimeException(
                'JWT private key not found. Run AuthService::generateKeyPair() first.'
            );
        }

        $pem = file_get_contents(self::PRIVATE_KEY_PATH);
        $key = openssl_pkey_get_private($pem);

        if ($key === false) {
            throw new \RuntimeException('Failed to load JWT private key: ' . openssl_error_string());
        }

        return $key;
    }
}
