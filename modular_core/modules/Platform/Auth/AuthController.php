<?php
/**
 * Platform/Auth/AuthController.php
 *
 * Handles authentication endpoints:
 *   POST /api/v1/auth/login
 *   POST /api/v1/auth/refresh
 *   POST /api/v1/auth/logout
 *
 * All responses use BaseController::respond().
 * All endpoints check isBlocked($ip) before processing and return HTTP 429 if blocked.
 *
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 42.1, 42.5, 42.6
 */

declare(strict_types=1);

namespace Platform\Auth;

use Core\BaseController;
use Core\Response;

class AuthController extends BaseController
{
    private AuthService $authService;

    /**
     * @param AuthService $authService
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/auth/login
    // -------------------------------------------------------------------------

    /**
     * Authenticate user credentials and return JWT + refresh token.
     *
     * Expected JSON body:
     *   { "email": string, "password": string, "tenant_id": string }
     *
     * Requirements: 4.1, 4.2, 42.1, 42.5, 42.6
     *
     * @param  array $body    Parsed request body
     * @param  array $headers HTTP headers (used to extract client IP)
     * @return Response
     */
    public function login(array $body, array $headers = []): Response
    {
        $email    = trim((string) ($body['email']    ?? ''));
        $password = (string) ($body['password']  ?? '');
        $tenantId = trim((string) ($body['tenant_id'] ?? ''));

        if ($email === '' || $password === '' || $tenantId === '') {
            return $this->respond(null, 'email, password, and tenant_id are required.', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respond(null, 'Invalid email format.', 422);
        }

        $ip = $this->resolveClientIp($headers);

        // Check block before any processing — Requirement 42.5, 42.6
        if ($this->authService->isBlocked($ip)) {
            return $this->respond(null, 'Too many attempts. Try again in 15 minutes.', 429);
        }

        try {
            $tokens = $this->authService->login($email, $password, $ip, $tenantId);
            return $this->respond($tokens, null, 200);
        } catch (RateLimitException $e) {
            return $this->respond(null, 'Too many attempts. Try again in 15 minutes.', 429);
        } catch (\RuntimeException $e) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 401;
            return $this->respond(null, $e->getMessage(), $status);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/auth/refresh
    // -------------------------------------------------------------------------

    /**
     * Rotate refresh token and issue new access + refresh token pair.
     *
     * Expected JSON body:
     *   { "refresh_token": string, "tenant_id": string }
     *
     * Requirements: 4.3, 4.4
     *
     * @param  array $body
     * @param  array $headers
     * @return Response
     */
    public function refresh(array $body, array $headers = []): Response
    {
        $refreshToken = trim((string) ($body['refresh_token'] ?? ''));
        $tenantId     = trim((string) ($body['tenant_id']     ?? ''));

        if ($refreshToken === '' || $tenantId === '') {
            return $this->respond(null, 'refresh_token and tenant_id are required.', 422);
        }

        $ip = $this->resolveClientIp($headers);

        // Check block before any processing
        if ($this->authService->isBlocked($ip)) {
            return $this->respond(null, 'Too many attempts. Try again in 15 minutes.', 429);
        }

        try {
            $tokens = $this->authService->refresh($refreshToken, $tenantId);
            return $this->respond($tokens, null, 200);
        } catch (\RuntimeException $e) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 401;
            return $this->respond(null, $e->getMessage(), $status);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/auth/logout
    // -------------------------------------------------------------------------

    /**
     * Invalidate the provided refresh token and remove session from Redis.
     *
     * Expected JSON body:
     *   { "refresh_token": string }
     *
     * Requires valid JWT (userId and tenantId set by front-controller after JWT validation).
     *
     * Requirements: 4.5
     *
     * @param  array $body
     * @param  array $headers
     * @return Response
     */
    public function logout(array $body, array $headers = []): Response
    {
        $refreshToken = trim((string) ($body['refresh_token'] ?? ''));

        if ($refreshToken === '') {
            return $this->respond(null, 'refresh_token is required.', 422);
        }

        $ip = $this->resolveClientIp($headers);

        // Check block before any processing
        if ($this->authService->isBlocked($ip)) {
            return $this->respond(null, 'Too many attempts. Try again in 15 minutes.', 429);
        }

        // userId and tenantId are set by the front-controller after JWT validation
        $this->authService->logout($refreshToken, $this->userId, $this->tenantId);

        return $this->respond(['message' => 'Logged out successfully.'], null, 200);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the real client IP from headers (supports proxies).
     *
     * @param  array $headers
     * @return string
     */
    private function resolveClientIp(array $headers): string
    {
        // Check common proxy headers first
        foreach (['X-Forwarded-For', 'X-Real-IP', 'CF-Connecting-IP'] as $header) {
            $value = $headers[$header] ?? $headers[strtolower($header)] ?? null;
            if ($value !== null && $value !== '') {
                // X-Forwarded-For may contain a comma-separated list; take the first
                return trim(explode(',', $value)[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
