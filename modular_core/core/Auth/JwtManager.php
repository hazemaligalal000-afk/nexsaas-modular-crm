<?php
/**
 * Core/Auth/JwtManager.php
 * Enterprise JWT Token Service with refresh token support.
 */

namespace Core\Auth;

use Core\Database;

class JwtManager {
    private static $secret = null;
    private static $currentPayload = null;

    private static function getSecret() {
        if (!self::$secret) {
            self::$secret = getenv('JWT_SECRET') ?: 'AI_REVOS_JWT_SECRET_KEY_ENTERPRISE_2026';
        }
        return self::$secret;
    }

    /**
     * Encode a payload into a JWT token.
     */
    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $b64Header  = self::base64UrlEncode($header);
        $b64Payload = self::base64UrlEncode(json_encode($payload));
        $signature  = hash_hmac('sha256', "{$b64Header}.{$b64Payload}", self::getSecret(), true);
        $b64Sig     = self::base64UrlEncode($signature);
        return "{$b64Header}.{$b64Payload}.{$b64Sig}";
    }

    /**
     * Decode and validate a JWT token. Throws on invalid/expired.
     */
    public static function decode($jwt) {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) throw new \Exception("Malformed token");

        [$b64Header, $b64Payload, $b64Sig] = $parts;

        // Verify signature
        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "{$b64Header}.{$b64Payload}", self::getSecret(), true)
        );
        if (!hash_equals($expectedSig, $b64Sig)) {
            throw new \Exception("Invalid signature");
        }

        $payload = json_decode(self::base64UrlDecode($b64Payload), true);

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \Exception("Token expired");
        }

        self::$currentPayload = $payload;
        return $payload;
    }

    /**
     * Issue both access + refresh tokens.
     */
    public static function issueTokenPair($userId, $tenantId, $roles) {
        $now = time();

        $accessToken = self::encode([
            'sub'       => $userId,
            'tenant_id' => $tenantId,
            'roles'     => $roles,
            'type'      => 'access',
            'iat'       => $now,
            'exp'       => $now + 900  // 15 minutes
        ]);

        $refreshPayload = [
            'sub'       => $userId,
            'tenant_id' => $tenantId,
            'type'      => 'refresh',
            'iat'       => $now,
            'exp'       => $now + (7 * 86400)  // 7 days
        ];
        $refreshToken = self::encode($refreshPayload);

        // Store refresh token hash in DB for revocation support
        $pdo = Database::getCentralConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))"
        );
        $stmt->execute([$userId, hash('sha256', $refreshToken), $refreshPayload['exp']]);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => 900,
            'token_type'    => 'Bearer'
        ];
    }

    /**
     * Refresh an access token using a valid refresh token.
     */
    public static function refresh($refreshToken) {
        $payload = self::decode($refreshToken);
        
        if (($payload['type'] ?? '') !== 'refresh') {
            throw new \Exception("Invalid token type for refresh");
        }

        // Verify token exists and is not revoked
        $pdo = Database::getCentralConnection();
        $stmt = $pdo->prepare("SELECT id FROM refresh_tokens WHERE token_hash = ? AND revoked = 0 AND expires_at > NOW()");
        $stmt->execute([hash('sha256', $refreshToken)]);
        
        if (!$stmt->fetch()) {
            throw new \Exception("Refresh token revoked or expired");
        }

        // Load user roles for new access token
        $roleStmt = $pdo->prepare(
            "SELECT r.role_name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?"
        );
        $roleStmt->execute([$payload['sub']]);
        $roles = array_column($roleStmt->fetchAll(), 'role_name');

        // Revoke old refresh token and issue new pair (rotation)
        $pdo->prepare("UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = ?")->execute([hash('sha256', $refreshToken)]);

        return self::issueTokenPair($payload['sub'], $payload['tenant_id'], $roles);
    }

    /**
     * Revoke all refresh tokens for a user (logout everywhere).
     */
    public static function revokeAll($userId) {
        $pdo = Database::getCentralConnection();
        $pdo->prepare("UPDATE refresh_tokens SET revoked = 1 WHERE user_id = ?")->execute([$userId]);
    }

    // ── Accessors ──

    public static function getCurrentUserId() {
        return self::$currentPayload['sub'] ?? null;
    }

    public static function getCurrentTenantId() {
        return self::$currentPayload['tenant_id'] ?? null;
    }

    public static function getCurrentRoles() {
        return self::$currentPayload['roles'] ?? [];
    }

    // ── Base64 URL Helpers ──

    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode($data) {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
