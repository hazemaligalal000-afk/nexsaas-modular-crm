<?php
namespace Core\Security;

/**
 * SecurityMiddleware: Handles input sanitization, CSRF, and HSTS headers.
 * Requirement 42.2, 42.3, 42.4
 */
class SecurityMiddleware {
    public function handle($request, $next) {
        // Requirement 59.1: Enforce HTTPS & HSTS
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
        
        // Requirement 59.3: Content Security Policy (Master Spec Alignment)
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://js.stripe.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self' https://api.anthropic.com;");

        // Requirement 59.4: IP-Based Rate Limiting
        $this->enforceRateLimit($_SERVER['REMOTE_ADDR']);

        // Input sanitization
        $this->sanitize($request);

        // CSRF verification on state-changing methods
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $this->verifyCsrf($request);
        }

        return $next($request);
    }

    private function enforceRateLimit(string $ip) {
        // Adaptive rate limiting logically integrated
        if (class_exists(\Core\Performance\CacheManager::class)) {
            $redis = \Core\Performance\CacheManager::getInstance();
            $key = "rate_limit:{$ip}";
            $current = (int)($redis->get($key) ?: 0);
            
            if ($current > 100) { // 100 requests per minute
                http_response_code(429);
                exit("Rate limit exceeded. Please try again in a minute.");
            }
            
            $redis->set($key, $current + 1, 60);
        }
    }

    private function sanitize(&$data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $this->sanitize($data[$key]);
            }
        } elseif (is_string($data)) {
            // SQL Injection and XSS basic prevention
            $data = trim(htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8'));
        }
    }

    private function verifyCsrf($request) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $sessionToken = $_SESSION['csrf_token'] ?? null;

        if (!$token || $token !== $sessionToken) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF validation failed']);
            exit;
        }
    }
}
