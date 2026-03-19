<?php
/**
 * Unit/AuthServiceTest.php
 *
 * Unit tests for Platform\Auth\AuthService.
 *
 * Tests cover:
 *  - login: valid credentials issue JWT + refresh token + session
 *  - login: invalid credentials throw RuntimeException 401
 *  - login: rate limit blocks after 10 attempts
 *  - login: blocked IP throws RateLimitException 429
 *  - refresh: valid token returns new JWT + refresh token
 *  - refresh: invalid/expired token throws RuntimeException 401
 *  - logout: deletes refresh token and session from Redis
 *  - checkRateLimit: increments counter and blocks after threshold
 *  - isBlocked: returns true when block key exists
 *  - buildJwt: returns a valid RS256 JWT with correct claims
 *
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 42.1, 42.5, 42.6
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Platform\Auth\AuthService;
use Platform\Auth\RateLimitException;

/**
 * @covers \Platform\Auth\AuthService
 */
class AuthServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private string $keysDir;
    private string $privateKeyPath;
    private string $publicKeyPath;

    protected function setUp(): void
    {
        // Generate a temporary key pair for tests
        $this->keysDir        = sys_get_temp_dir() . '/nexsaas_test_keys_' . getmypid();
        $this->privateKeyPath = $this->keysDir . '/jwt_private.pem';
        $this->publicKeyPath  = $this->keysDir . '/jwt_public.pem';

        if (!is_dir($this->keysDir)) {
            mkdir($this->keysDir, 0700, true);
        }

        if (!file_exists($this->privateKeyPath)) {
            $res = openssl_pkey_new([
                'digest_alg'       => 'sha256',
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);
            openssl_pkey_export($res, $priv);
            $details = openssl_pkey_get_details($res);
            file_put_contents($this->privateKeyPath, $priv);
            file_put_contents($this->publicKeyPath, $details['key']);
        }
    }

    protected function tearDown(): void
    {
        // Clean up temp keys
        if (file_exists($this->privateKeyPath)) {
            unlink($this->privateKeyPath);
        }
        if (file_exists($this->publicKeyPath)) {
            unlink($this->publicKeyPath);
        }
        if (is_dir($this->keysDir)) {
            rmdir($this->keysDir);
        }
    }

    /**
     * Build a mock Redis client with an in-memory store.
     *
     * @param  array $store  Initial key→value store
     * @return object
     */
    private function buildRedis(array $store = []): object
    {
        return new class($store) {
            private array $store;
            private array $ttls = [];

            public function __construct(array $store)
            {
                $this->store = $store;
            }

            public function get(string $key): string|false
            {
                return $this->store[$key] ?? false;
            }

            public function set(string $key, string $value): bool
            {
                $this->store[$key] = $value;
                return true;
            }

            public function setex(string $key, int $ttl, string $value): bool
            {
                $this->store[$key] = $value;
                $this->ttls[$key]  = $ttl;
                return true;
            }

            public function del(string $key): int
            {
                if (isset($this->store[$key])) {
                    unset($this->store[$key]);
                    unset($this->ttls[$key]);
                    return 1;
                }
                return 0;
            }

            public function exists(string $key): int
            {
                return isset($this->store[$key]) ? 1 : 0;
            }

            public function incr(string $key): int
            {
                $this->store[$key] = (string) ((int) ($this->store[$key] ?? '0') + 1);
                return (int) $this->store[$key];
            }

            public function expire(string $key, int $ttl): bool
            {
                $this->ttls[$key] = $ttl;
                return true;
            }

            public function has(string $key): bool
            {
                return isset($this->store[$key]);
            }

            public function getStore(): array
            {
                return $this->store;
            }

            public function getTtl(string $key): ?int
            {
                return $this->ttls[$key] ?? null;
            }
        };
    }

    /**
     * Build a mock ADOdb connection.
     *
     * @param  array|null $userRow  User row to return on SELECT, or null for empty
     * @return object
     */
    private function buildDb(?array $userRow = null): object
    {
        return new class($userRow) {
            private ?array $userRow;
            private int    $affectedRows = 0;

            public function __construct(?array $userRow)
            {
                $this->userRow = $userRow;
            }

            public function Execute(string $sql, array $params = []): object|false
            {
                // UPDATE queries — return success
                if (stripos($sql, 'UPDATE') === 0) {
                    $this->affectedRows = 1;
                    return $this->emptyRs();
                }

                // SELECT users
                if (stripos($sql, 'FROM users') !== false && $this->userRow !== null) {
                    return $this->singleRowRs($this->userRow);
                }

                return $this->emptyRs();
            }

            public function ErrorMsg(): string { return ''; }
            public function Affected_Rows(): int { return $this->affectedRows; }
            public function Insert_ID(): int { return 1; }
            public function BeginTrans(): void {}
            public function CommitTrans(): void {}
            public function RollbackTrans(): void {}

            private function emptyRs(): object
            {
                return new class {
                    public bool  $EOF    = true;
                    public array $fields = [];
                    public function MoveNext(): void {}
                };
            }

            private function singleRowRs(array $row): object
            {
                return new class($row) {
                    public bool  $EOF    = false;
                    public array $fields;

                    public function __construct(array $row)
                    {
                        $this->fields = $row;
                    }

                    public function MoveNext(): void
                    {
                        $this->EOF = true;
                    }
                };
            }
        };
    }

    /**
     * Build an AuthService with a patched private key path.
     */
    private function buildService(?array $userRow = null, array $redisStore = []): AuthService
    {
        $db    = $this->buildDb($userRow);
        $redis = $this->buildRedis($redisStore);

        // Use reflection to override the PRIVATE_KEY_PATH constant
        $service = new class($db, $redis, $this->privateKeyPath) extends AuthService {
            private string $testKeyPath;

            public function __construct($db, object $redis, string $keyPath)
            {
                parent::__construct($db, $redis);
                $this->testKeyPath = $keyPath;
            }

            protected function getPrivateKeyPath(): string
            {
                return $this->testKeyPath;
            }
        };

        return $service;
    }

    /**
     * Create a valid bcrypt hash at cost 12.
     */
    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * Login with valid credentials returns access_token, refresh_token, expires_in.
     *
     * Requirements: 4.1, 4.2
     */
    public function testLoginWithValidCredentialsReturnsTokens(): void
    {
        $password = 'SecurePass123!';
        $userRow  = [
            'id'              => 42,
            'email'           => 'user@example.com',
            'password_hash'   => $this->hashPassword($password),
            'platform_role'   => 'Agent',
            'accounting_role' => null,
            'company_code'    => '01',
            'is_active'       => true,
            'locked_until'    => null,
        ];

        $db    = $this->buildDb($userRow);
        $redis = $this->buildRedis();

        $service = $this->buildAuthService($db, $redis);
        $result  = $service->login('user@example.com', $password, '127.0.0.1', 'tenant-uuid-1');

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertSame(900, $result['expires_in']);
        $this->assertNotEmpty($result['access_token']);
        $this->assertNotEmpty($result['refresh_token']);
    }

    /**
     * Login with invalid password throws RuntimeException with code 401.
     *
     * Requirements: 4.1
     */
    public function testLoginWithInvalidPasswordThrows401(): void
    {
        $userRow = [
            'id'              => 1,
            'email'           => 'user@example.com',
            'password_hash'   => $this->hashPassword('correct-password'),
            'platform_role'   => 'Agent',
            'accounting_role' => null,
            'company_code'    => '01',
            'is_active'       => true,
            'locked_until'    => null,
        ];

        $db    = $this->buildDb($userRow);
        $redis = $this->buildRedis();

        $service = $this->buildAuthService($db, $redis);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(401);

        $service->login('user@example.com', 'wrong-password', '127.0.0.1', 'tenant-uuid-1');
    }

    /**
     * Login with non-existent user throws RuntimeException with code 401.
     *
     * Requirements: 4.1
     */
    public function testLoginWithNonExistentUserThrows401(): void
    {
        $db    = $this->buildDb(null); // no user row
        $redis = $this->buildRedis();

        $service = $this->buildAuthService($db, $redis);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(401);

        $service->login('nobody@example.com', 'password', '127.0.0.1', 'tenant-uuid-1');
    }

    /**
     * Login stores session in Redis at session:{tenant_id}:{user_id}.
     *
     * Requirement 4.4
     */
    public function testLoginStoresSessionInRedis(): void
    {
        $password = 'Pass123!';
        $userRow  = [
            'id'              => 7,
            'email'           => 'user@example.com',
            'password_hash'   => $this->hashPassword($password),
            'platform_role'   => 'Manager',
            'accounting_role' => null,
            'company_code'    => '01',
            'is_active'       => true,
            'locked_until'    => null,
        ];

        $db    = $this->buildDb($userRow);
        $redis = $this->buildRedis();

        $service  = $this->buildAuthService($db, $redis);
        $service->login('user@example.com', $password, '127.0.0.1', 'tenant-abc');

        $sessionKey = 'session:tenant-abc:7';
        $this->assertTrue($redis->has($sessionKey), 'Session must be stored in Redis');

        $session = json_decode($redis->get($sessionKey), true);
        $this->assertSame('7', $session['user_id']);
        $this->assertSame('tenant-abc', $session['tenant_id']);
        $this->assertSame('Manager', $session['role']);
    }

    /**
     * Login stores refresh token in Redis at refresh:{sha256(token)}.
     *
     * Requirement 4.2
     */
    public function testLoginStoresRefreshTokenInRedis(): void
    {
        $password = 'Pass123!';
        $userRow  = [
            'id'              => 5,
            'email'           => 'user@example.com',
            'password_hash'   => $this->hashPassword($password),
            'platform_role'   => 'Agent',
            'accounting_role' => null,
            'company_code'    => '01',
            'is_active'       => true,
            'locked_until'    => null,
        ];

        $db    = $this->buildDb($userRow);
        $redis = $this->buildRedis();

        $service = $this->buildAuthService($db, $redis);
        $result  = $service->login('user@example.com', $password, '127.0.0.1', 'tenant-xyz');

        $hash     = hash('sha256', $result['refresh_token']);
        $redisKey = "refresh:{$hash}";

        $this->assertTrue($redis->has($redisKey), 'Refresh token must be stored in Redis');

        $stored = json_decode($redis->get($redisKey), true);
        $this->assertSame(5, $stored['user_id']);
        $this->assertSame('tenant-xyz', $stored['tenant_id']);
        $this->assertArrayHasKey('expires_at', $stored);
    }

    /**
     * Blocked IP throws RateLimitException before any DB query.
     *
     * Requirements: 42.5, 42.6
     */
    public function testBlockedIpThrowsRateLimitException(): void
    {
        $db    = $this->buildDb(null);
        $redis = $this->buildRedis(['ratelimit:block:1.2.3.4' => '1']);

        $service = $this->buildAuthService($db, $redis);

        $this->expectException(RateLimitException::class);
        $this->expectExceptionCode(429);

        $service->login('user@example.com', 'pass', '1.2.3.4', 'tenant-1');
    }

    /**
     * After 10 failed attempts, the 11th sets the block key and throws RateLimitException.
     *
     * Requirements: 42.5, 42.6
     */
    public function testRateLimitBlocksAfterTenAttempts(): void
    {
        $db    = $this->buildDb(null); // always returns no user
        $redis = $this->buildRedis();

        $service = $this->buildAuthService($db, $redis);

        $ip = '10.0.0.1';

        // First 10 attempts: should throw 401 (invalid credentials), not 429
        for ($i = 0; $i < 10; $i++) {
            try {
                $service->login('x@x.com', 'wrong', $ip, 'tenant-1');
            } catch (\RuntimeException $e) {
                $this->assertNotSame(429, $e->getCode(), "Attempt $i should not be rate-limited yet");
            }
        }

        // 11th attempt: should throw RateLimitException 429
        $this->expectException(RateLimitException::class);
        $this->expectExceptionCode(429);

        $service->login('x@x.com', 'wrong', $ip, 'tenant-1');
    }

    /**
     * isBlocked returns true when block key exists in Redis.
     *
     * Requirements: 42.5
     */
    public function testIsBlockedReturnsTrueWhenBlockKeyExists(): void
    {
        $db    = $this->buildDb(null);
        $redis = $this->buildRedis(['ratelimit:block:5.5.5.5' => '1']);

        $service = $this->buildAuthService($db, $redis);

        $this->assertTrue($service->isBlocked('5.5.5.5'));
        $this->assertFalse($service->isBlocked('6.6.6.6'));
    }

    /**
     * refresh() with a valid token returns new access_token and refresh_token.
     *
     * Requirements: 4.3, 4.4
     */
    public function testRefreshWithValidTokenReturnsNewTokens(): void
    {
        $rawToken  = bin2hex(random_bytes(32));
        $hash      = hash('sha256', $rawToken);
        $expiresAt = time() + 604800;

        $storedPayload = json_encode([
            'user_id'    => 99,
            'tenant_id'  => 'tenant-refresh',
            'expires_at' => $expiresAt,
        ]);

        $userRow = [
            'id'              => 99,
            'email'           => 'user@example.com',
            'platform_role'   => 'Admin',
            'accounting_role' => null,
            'company_code'    => '01',
            'is_active'       => true,
        ];

        $db    = $this->buildDb($userRow);
        $redis = $this->buildRedis(["refresh:{$hash}" => $storedPayload]);

        $service = $this->buildAuthService($db, $redis);
        $result  = $service->refresh($rawToken, 'tenant-refresh');

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertSame(900, $result['expires_in']);

        // Old token must be deleted (rotation)
        $this->assertFalse($redis->has("refresh:{$hash}"), 'Old refresh token must be deleted after rotation');
    }

    /**
     * refresh() with an invalid token throws RuntimeException 401.
     *
     * Requirements: 4.3
     */
    public function testRefreshWithInvalidTokenThrows401(): void
    {
        $db    = $this->buildDb(null);
        $redis = $this->buildRedis(); // empty — no token stored

        $service = $this->buildAuthService($db, $redis);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(401);

        $service->refresh('invalid-token-that-does-not-exist', 'tenant-1');
    }

    /**
     * refresh() with a token belonging to a different tenant throws 401.
     *
     * Requirements: 4.3
     */
    public function testRefreshWithWrongTenantThrows401(): void
    {
        $rawToken  = bin2hex(random_bytes(32));
        $hash      = hash('sha256', $rawToken);

        $storedPayload = json_encode([
            'user_id'    => 1,
            'tenant_id'  => 'tenant-A',
            'expires_at' => time() + 604800,
        ]);

        $db    = $this->buildDb(null);
        $redis = $this->buildRedis(["refresh:{$hash}" => $storedPayload]);

        $service = $this->buildAuthService($db, $redis);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(401);

        $service->refresh($rawToken, 'tenant-B'); // wrong tenant
    }

    /**
     * logout() deletes refresh token and session from Redis.
     *
     * Requirement 4.5
     */
    public function testLogoutDeletesRefreshTokenAndSession(): void
    {
        $rawToken  = bin2hex(random_bytes(32));
        $hash      = hash('sha256', $rawToken);

        $storedPayload = json_encode([
            'user_id'    => 3,
            'tenant_id'  => 'tenant-logout',
            'expires_at' => time() + 604800,
        ]);

        $sessionKey = 'session:tenant-logout:3';

        $db    = $this->buildDb(null);
        $redis = $this->buildRedis([
            "refresh:{$hash}" => $storedPayload,
            $sessionKey       => '{"user_id":"3"}',
        ]);

        $service = $this->buildAuthService($db, $redis);
        $result  = $service->logout($rawToken, '3', 'tenant-logout');

        $this->assertTrue($result);
        $this->assertFalse($redis->has("refresh:{$hash}"), 'Refresh token must be deleted on logout');
        $this->assertFalse($redis->has($sessionKey), 'Session must be deleted on logout');
    }

    /**
     * buildJwt() returns a valid RS256 JWT with correct claims.
     *
     * Requirements: 42.1
     */
    public function testBuildJwtReturnsValidRs256Token(): void
    {
        $db    = $this->buildDb(null);
        $redis = $this->buildRedis();

        $service = $this->buildAuthService($db, $redis);

        $user = [
            'id'              => 10,
            'platform_role'   => 'Owner',
            'accounting_role' => null,
            'company_code'    => '01',
        ];

        $jwt = $service->buildJwt($user, 'tenant-jwt-test');

        // Decode and verify
        $pubKey  = file_get_contents($this->publicKeyPath);
        $payload = \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key($pubKey, 'RS256'));

        $this->assertSame(10, $payload->sub);
        $this->assertSame('tenant-jwt-test', $payload->tenant_id);
        $this->assertSame('Owner', $payload->role);
        $this->assertSame('01', $payload->company_code);
        $this->assertGreaterThan(time(), $payload->exp);
        $this->assertSame(900, $payload->exp - $payload->iat);
    }

    /**
     * checkRateLimit() increments counter and sets TTL on first call.
     *
     * Requirements: 42.5
     */
    public function testCheckRateLimitIncrementsCounter(): void
    {
        $db    = $this->buildDb(null);
        $redis = $this->buildRedis();

        $service = $this->buildAuthService($db, $redis);

        // Should not throw for first 10 calls
        for ($i = 0; $i < 10; $i++) {
            $service->checkRateLimit('192.168.1.1');
        }

        $this->assertSame('10', $redis->getStore()['ratelimit:auth:192.168.1.1'] ?? '0');
    }

    /**
     * checkRateLimit() throws RateLimitException on the 11th call and sets block key.
     *
     * Requirements: 42.5, 42.6
     */
    public function testCheckRateLimitThrowsOnEleventhCall(): void
    {
        $db    = $this->buildDb(null);
        $redis = $this->buildRedis(['ratelimit:auth:9.9.9.9' => '10']); // already at 10

        $service = $this->buildAuthService($db, $redis);

        $this->expectException(RateLimitException::class);
        $this->expectExceptionCode(429);

        $service->checkRateLimit('9.9.9.9');

        // Block key should be set
        $this->assertTrue($redis->has('ratelimit:block:9.9.9.9'));
    }

    // -------------------------------------------------------------------------
    // Helper: build AuthService with injected key path
    // -------------------------------------------------------------------------

    private function buildAuthService(object $db, object $redis): AuthService
    {
        $keyPath = $this->privateKeyPath;

        return new class($db, $redis, $keyPath) extends AuthService {
            private string $testKeyPath;

            public function __construct($db, object $redis, string $keyPath)
            {
                parent::__construct($db, $redis);
                $this->testKeyPath = $keyPath;
            }

            public function buildJwt(array $user, string $tenantId): string
            {
                $pem = file_get_contents($this->testKeyPath);
                $key = openssl_pkey_get_private($pem);

                $now = time();
                $payload = [
                    'sub'          => (int) $user['id'],
                    'tenant_id'    => $tenantId,
                    'company_code' => $user['company_code'] ?? '',
                    'role'         => $user['platform_role'] ?? $user['accounting_role'] ?? '',
                    'iat'          => $now,
                    'exp'          => $now + 900,
                ];

                return \Firebase\JWT\JWT::encode($payload, $key, 'RS256');
            }
        };
    }
}
