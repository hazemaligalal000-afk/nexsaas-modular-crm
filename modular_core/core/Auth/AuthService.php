<?php
/**
 * Core/Auth/AuthService.php
 * Enterprise Authentication Service handling login, refresh, and logout.
 */

namespace Core\Auth;

use Core\Database;
use Core\AuditLogger;

class AuthService {

    /**
     * Authenticate a user by email + password.
     * Returns JWT token pair on success.
     */
    public static function login($email, $password, $ipAddress = null) {
        $pdo = Database::getCentralConnection();

        $stmt = $pdo->prepare(
            "SELECT u.id, u.tenant_id, u.name, u.password_hash, u.is_active, t.status as tenant_status
             FROM users u
             JOIN tenants t ON u.tenant_id = t.id
             WHERE u.email = ?
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Prevent timing attacks
        if (!$user) {
            password_verify($password, '$2y$12$invalidhashplaceholder000000000000000000000000000');
            throw new \Exception("Invalid credentials", 401);
        }

        if (!password_verify($password, $user['password_hash'])) {
            AuditLogger::log($user['tenant_id'], $user['id'], 'login_failed', 'user', $user['id']);
            throw new \Exception("Invalid credentials", 401);
        }

        if (!$user['is_active']) {
            throw new \Exception("Account is deactivated. Contact your administrator.", 403);
        }

        if ($user['tenant_status'] === 'suspended') {
            throw new \Exception("Your organization's account is suspended. Please contact billing.", 403);
        }

        // Load user roles
        $roleStmt = $pdo->prepare(
            "SELECT r.role_name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?"
        );
        $roleStmt->execute([$user['id']]);
        $roles = array_column($roleStmt->fetchAll(), 'role_name');

        if (empty($roles)) {
            throw new \Exception("No roles assigned. Contact your administrator.", 403);
        }

        // Update last login
        $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

        // Issue tokens
        $tokens = JwtManager::issueTokenPair($user['id'], $user['tenant_id'], $roles);

        // Audit
        AuditLogger::log($user['tenant_id'], $user['id'], 'login_success', 'user', $user['id']);

        return array_merge($tokens, [
            'user' => [
                'id'        => $user['id'],
                'name'      => $user['name'],
                'email'     => $email,
                'roles'     => $roles,
                'tenant_id' => $user['tenant_id']
            ]
        ]);
    }

    /**
     * Refresh an access token using a valid refresh token.
     */
    public static function refreshToken($refreshToken) {
        return JwtManager::refresh($refreshToken);
    }

    /**
     * Logout — revoke all refresh tokens for the current user.
     */
    public static function logout() {
        $userId = JwtManager::getCurrentUserId();
        if ($userId) {
            JwtManager::revokeAll($userId);
        }
    }

    /**
     * GET /api/auth/me — Returns current user profile + permissions matrix.
     */
    public static function me() {
        $userId   = JwtManager::getCurrentUserId();
        $tenantId = JwtManager::getCurrentTenantId();

        $pdo = Database::getCentralConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, avatar_url FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch();

        if (!$user) throw new \Exception("User not found", 404);

        return [
            'user'        => $user,
            'roles'       => JwtManager::getCurrentRoles(),
            'permissions' => RbacGuard::getPermissionsMatrix(),
            'tenant_id'   => $tenantId
        ];
    }
}
