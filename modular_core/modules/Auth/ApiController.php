<?php
/**
 * Modules/Auth/ApiController.php
 * Enterprise Authentication Endpoints (Login, Refresh, Me, Logout).
 */

namespace Modules\Auth;

use Core\Auth\AuthService;
use Core\Auth\JwtManager;

class ApiController {

    /**
     * POST /api/auth/login
     * Body: { "email": "...", "password": "..." }
     */
    public function login($data) {
        try {
            $result = AuthService::login(
                $data['email'] ?? '', 
                $data['password'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            // Set refresh token as HttpOnly secure cookie
            setcookie('refresh_token', $result['refresh_token'], [
                'expires'  => time() + (7 * 86400),
                'path'     => '/api/auth/',
                'domain'   => '',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly'  => true,
                'samesite'  => 'Strict'
            ]);

            // Don't expose refresh token in response body
            unset($result['refresh_token']);

            return json_encode(['success' => true, 'data' => $result]);

        } catch (\Exception $e) {
            http_response_code($e->getCode() ?: 401);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/auth/refresh
     * Uses the HttpOnly cookie refresh_token.
     */
    public function refresh() {
        try {
            $refreshToken = $_COOKIE['refresh_token'] ?? null;
            if (!$refreshToken) throw new \Exception("No refresh token provided", 401);

            $tokens = AuthService::refreshToken($refreshToken);

            // Rotate the cookie
            setcookie('refresh_token', $tokens['refresh_token'], [
                'expires'  => time() + (7 * 86400),
                'path'     => '/api/auth/',
                'httponly'  => true,
                'samesite'  => 'Strict'
            ]);

            unset($tokens['refresh_token']);
            return json_encode(['success' => true, 'data' => $tokens]);

        } catch (\Exception $e) {
            http_response_code(401);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/auth/me
     * Returns current user profile + full permission matrix for frontend rendering.
     */
    public function me() {
        try {
            $profile = AuthService::me();
            return json_encode(['success' => true, 'data' => $profile]);
        } catch (\Exception $e) {
            http_response_code(401);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/auth/logout
     * Revokes all refresh tokens for the user.
     */
    public function logout() {
        AuthService::logout();
        setcookie('refresh_token', '', ['expires' => time() - 3600, 'path' => '/api/auth/']);
        return json_encode(['success' => true, 'message' => 'Logged out successfully']);
    }
}
