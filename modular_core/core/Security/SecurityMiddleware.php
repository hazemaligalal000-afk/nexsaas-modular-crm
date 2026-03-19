<?php
namespace Core\Security;

/**
 * SecurityMiddleware: Handles input sanitization, CSRF, and HSTS headers.
 * Requirement 42.2, 42.3, 42.4
 */
class SecurityMiddleware {
    public function handle($request, $next) {
        // Enforce HTTPS via HSTS
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

        // Input sanitization
        $this->sanitize($request);

        // CSRF verification on state-changing methods
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $this->verifyCsrf($request);
        }

        return $next($request);
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
