<?php

namespace ModularCore\Bootstrap;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Core Security Hardening (Phase 0.3)
 */
class CoreSecurityMiddleware
{
    /**
     * Handle an incoming request.
     * (Requirement 9: Force HTTPS)
     * (Requirement 14: Content Security Policy)
     */
    public function handle(Request $request, Closure $next): Response
    {
        # 1. Force HTTPS (Requirement 9.1-9.5)
        if (!$request->secure() && env('APP_ENV') === 'production') {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        # 2. HSTS Header (Requirement 9.2-9.4)
        if (env('APP_ENV') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        # 3. Content Security Policy (Requirement 14.1-14.9)
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' https://js.stripe.com https://cdn.jsdelivr.net; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ";
        $csp .= "img-src 'self' data: https://*.stripe.com; ";
        $csp .= "connect-src 'self' https://api.openai.com https://api.anthropic.com wss://*.soketi.app; ";
        $csp .= "frame-ancestors 'none'; "; // Requirement 14.7 (Anti-Clickjacking)
        $csp .= "base-uri 'self'; ";
        $csp .= "form-action 'self'; ";

        $response->headers->set('Content-Security-Policy', $csp);

        # 4. Standard Security Headers (Requirement 15.10)
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
