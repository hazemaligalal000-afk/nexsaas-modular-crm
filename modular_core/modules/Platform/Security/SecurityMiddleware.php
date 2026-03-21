<?php
/**
 * ModularCore/Modules/Platform/Security/SecurityMiddleware.php
 * Comprehensive Security Enhancements (Requirement 7.21, 7.22, 7.23)
 * Implements CSP Headers, API Key Hashing, and Rate Limiting.
 */

namespace ModularCore\Modules\Platform\Security;

use Core\Database;
use Core\AuditLogger;
use RateLimiter;

class SecurityMiddleware {

    /**
     * Inject Content Security Policy (CSP) and strict security headers
     * Fulfills Master Spec 33.1 - OWASP Hardening
     */
    public static function applySecurityHeaders() {
        // Prevent XSS, Clickjacking, and restrict resource loading
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' wss: https:; frame-ancestors 'none'; form-action 'self';");
        
        // Advanced HTTP Protections
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Enforce strict Rate Limiting per-IP and per-User
     * Fulfills Master Spec 33.5 - Layer 7 DoS Protection
     */
    public static function enforceRateLimiting($userId = null) {
        if (!class_exists('RateLimiter')) {
            require_once __DIR__ . '/../../../../include/utils/RateLimiter.php';
        }
        
        $limiter = new \RateLimiter();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
        
        // 1. Per-IP Limit (Global: 200 reqs / 1 min)
        if (!$limiter->check("ip:{$ip}", 200, 1)) {
            self::abort(429, "Too Many Requests (IP Limit). Please wait.");
        }

        // 2. Per-User Limit (API Abuse Prevention: 1000 reqs / 1 min)
        if ($userId) {
            if (!$limiter->check("user:{$userId}", 1000, 1)) {
                AuditLogger::log($userId, 'SECURITY', 'RATE_LIMIT_EXCEEDED', 'WARNING', "User {$userId} hit API limit.", 1);
                self::abort(429, "Too Many Requests (User Limit). Please slow down.");
            }
        }
    }

    /**
     * Validate and Hash API Keys
     * Fulfills Master Spec 34.2 - Secure API Authentication
     */
    public static function validateApiKey($rawApiKey) {
        if (empty($rawApiKey)) {
             self::abort(401, "API Key Required");
        }

        // We NEVER store raw API keys. We verify against hashed keys (password_verify semantics)
        // Extract key prefix (e.g. nxs_13fH) to quickly look up the user record, then hash check
        $parts = explode('_', $rawApiKey, 3);
        if (count($parts) !== 3) self::abort(401, "Invalid API Key format");

        $prefix = $parts[0] . '_' . $parts[1]; // nxs_live
        
        $pdo = Database::getCentralConnection();
        $stmt = $pdo->prepare("SELECT user_id, api_key_hash, tenant_id FROM api_keys WHERE prefix = ? AND is_active = true");
        $stmt->execute([$prefix]);
        
        $keyRecord = $stmt->fetch();
        if (!$keyRecord || !password_verify($rawApiKey, $keyRecord['api_key_hash'])) {
            // Track failures to prevent brute force
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $limiter = new \RateLimiter();
                if (!$limiter->check("failed_auth:".$_SERVER['REMOTE_ADDR'], 10, 5)) {
                    self::abort(403, "IP Blocked due to excessive auth failures");
                }
            }
            self::abort(401, "Invalid API Key");
        }

        // Inject Tenant Identity
        \Core\TenantEnforcer::setContext($keyRecord['tenant_id'], $keyRecord['user_id']);
        
        return $keyRecord['user_id'];
    }

    public static function generateSecureApiKey($tenantId, $userId, $environment = 'live') {
        // Generate cryptographic random key
        $random = bin2hex(random_bytes(32));
        $rawKey = "nxs_{$environment}_{$random}";
        
        // Hash for storage
        $hash = password_hash($rawKey, PASSWORD_BCRYPT, ['cost' => 12]);
        $prefix = "nxs_{$environment}";
        
        // Store hash
        $pdo = Database::getCentralConnection();
        $stmt = $pdo->prepare("INSERT INTO api_keys (tenant_id, user_id, prefix, api_key_hash, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$tenantId, $userId, $prefix, $hash]);

        return $rawKey; // MUST BE SHOWN ONLY ONCE TO USER
    }

    public static function abort($code, $message) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => $message]);
        exit;
    }
}
