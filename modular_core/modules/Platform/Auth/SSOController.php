<?php
/**
 * Platform/Auth/SSOController.php
 *
 * Handles SSO authentication endpoints:
 *   GET  /api/v1/auth/sso/{provider}           → initiate SSO flow (redirect to IdP)
 *   POST /api/v1/auth/sso/{provider}/callback  → process IdP response, return tokens
 *
 * All responses use BaseController::respond().
 *
 * Requirements: 4.7, 34.1, 34.2, 34.3, 34.4, 34.5
 */

declare(strict_types=1);

namespace Platform\Auth;

use Core\BaseController;
use Core\Response;

class SSOController extends BaseController
{
    private SSOService $ssoService;

    /**
     * @param SSOService $ssoService
     */
    public function __construct(SSOService $ssoService)
    {
        $this->ssoService = $ssoService;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/auth/sso/{provider}
    // -------------------------------------------------------------------------

    /**
     * Initiate an SSO flow for the given provider.
     *
     * For SAML: returns a redirect URL to the IdP's SSO endpoint.
     * For OAuth2: returns a redirect URL to the authorization server.
     *
     * The caller (front-controller) should issue an HTTP 302 redirect to the
     * returned URL. The response envelope carries the URL in data.redirect_url
     * so API clients can also handle it programmatically.
     *
     * Expected query params:
     *   tenant_id  string  (required) — the tenant initiating the SSO flow
     *
     * Requirements: 34.1, 34.2
     *
     * @param  string $provider  Route param: 'saml' | 'google' | 'microsoft' | 'github'
     * @param  array  $query     Parsed query string
     * @return Response
     */
    public function initiate(string $provider, array $query = []): Response
    {
        $tenantId = trim((string) ($query['tenant_id'] ?? $this->tenantId));

        if ($tenantId === '') {
            return $this->respond(null, 'tenant_id is required.', 422);
        }

        try {
            $redirectUrl = $this->ssoService->initiate($provider, $tenantId);
            return $this->respond(['redirect_url' => $redirectUrl], null, 200);
        } catch (\RuntimeException $e) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 400;
            return $this->respond(null, $e->getMessage(), $status);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/auth/sso/{provider}/callback
    // -------------------------------------------------------------------------

    /**
     * Process the IdP callback and issue JWT + refresh token.
     *
     * For SAML: expects SAMLResponse in the POST body.
     * For OAuth2: expects code and state in the POST body (or query string).
     *
     * Expected body / params:
     *   tenant_id    string  (required)
     *   SAMLResponse string  (SAML only)
     *   code         string  (OAuth2 only)
     *   state        string  (OAuth2 only)
     *
     * Requirements: 34.2, 34.3, 34.4
     *
     * @param  string $provider  Route param: 'saml' | 'google' | 'microsoft' | 'github'
     * @param  array  $params    Merged POST body + query params
     * @return Response
     */
    public function callback(string $provider, array $params = []): Response
    {
        $tenantId = trim((string) ($params['tenant_id'] ?? $this->tenantId));

        if ($tenantId === '') {
            return $this->respond(null, 'tenant_id is required.', 422);
        }

        try {
            $tokens = $this->ssoService->handleCallback($provider, $params, $tenantId);
            return $this->respond($tokens, null, 200);
        } catch (\RuntimeException $e) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 401;
            return $this->respond(null, $e->getMessage(), $status);
        }
    }
}
