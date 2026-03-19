<?php
/**
 * CRM/Email/MailboxController.php
 *
 * REST endpoints for Gmail / Microsoft 365 mailbox connections and email tracking.
 *
 * Protected routes (JWT required):
 *   GET    /api/v1/crm/email/mailboxes                    → index()
 *   POST   /api/v1/crm/email/mailboxes/connect            → connect()
 *   GET    /api/v1/crm/email/mailboxes/callback/{provider}→ callback()
 *   DELETE /api/v1/crm/email/mailboxes/{id}               → disconnect()
 *   POST   /api/v1/crm/email/mailboxes/{id}/sync          → triggerSync()
 *
 * Public routes (no auth):
 *   GET    /api/v1/email/track/{token}/open.gif           → trackOpen()
 *   GET    /api/v1/email/track/{token}/click              → trackClick()
 *
 * Requirements: 13.1, 13.2, 13.3, 13.4
 */

declare(strict_types=1);

namespace CRM\Email;

use Core\BaseController;
use Core\Response;

class MailboxController extends BaseController
{
    private MailboxConnectionService $connectionService;
    private EmailSyncService         $syncService;
    private EmailTrackingService     $trackingService;

    public function __construct(
        MailboxConnectionService $connectionService,
        EmailSyncService         $syncService,
        EmailTrackingService     $trackingService
    ) {
        $this->connectionService = $connectionService;
        $this->syncService       = $syncService;
        $this->trackingService   = $trackingService;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/email/mailboxes
    // -------------------------------------------------------------------------

    /**
     * List connected mailboxes for the current user.
     *
     * @return Response
     */
    public function index(): Response
    {
        $userId = (int) ($this->userId ?? 0);
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        try {
            $mailboxes = $this->connectionService->getConnectedMailboxes($userId, $this->tenantId);
            return $this->respond(['mailboxes' => $mailboxes]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/email/mailboxes/connect
    // -------------------------------------------------------------------------

    /**
     * Initiate OAuth flow for a provider.
     *
     * Body: { "provider": "gmail" | "microsoft365" }
     *
     * Returns: { "auth_url": "https://..." }
     *
     * @param  array $body
     * @return Response
     */
    public function connect(array $body): Response
    {
        $userId = (int) ($this->userId ?? 0);
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        $provider = trim((string) ($body['provider'] ?? ''));
        if (!in_array($provider, ['gmail', 'microsoft365'], true)) {
            return $this->respond(null, "provider must be 'gmail' or 'microsoft365'.", 422);
        }

        try {
            $authUrl = $this->connectionService->initiateOAuth(
                $userId,
                $provider,
                $this->tenantId,
                $this->companyCode
            );
            return $this->respond(['auth_url' => $authUrl]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/email/mailboxes/callback/{provider}
    // -------------------------------------------------------------------------

    /**
     * Handle OAuth callback from Gmail or Microsoft 365.
     *
     * Stores tokens and redirects to the frontend dashboard.
     *
     * @param  string $provider     Route param
     * @param  array  $queryParams  code, state
     * @return Response
     */
    public function callback(string $provider, array $queryParams = []): Response
    {
        $code  = trim((string) ($queryParams['code']  ?? ''));
        $state = trim((string) ($queryParams['state'] ?? ''));

        if ($code === '') {
            return $this->respond(null, 'Missing authorization code.', 422);
        }

        try {
            $mailbox  = $this->connectionService->handleCallback($provider, $code, $state, $this->tenantId);
            $frontUrl = $_ENV['FRONTEND_URL'] ?? getenv('FRONTEND_URL') ?: '/';
            $redirect = rtrim($frontUrl, '/') . '/settings/email?connected=1&provider=' . urlencode($provider);

            // Issue HTTP redirect — front-controller will send this response
            header('Location: ' . $redirect, true, 302);
            return $this->respond(['mailbox' => $mailbox, 'redirect' => $redirect]);
        } catch (\RuntimeException $e) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 400;
            return $this->respond(null, $e->getMessage(), (int) $status);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/email/mailboxes/{id}
    // -------------------------------------------------------------------------

    /**
     * Disconnect (soft-delete) a mailbox.
     *
     * @param  int $id  Mailbox ID
     * @return Response
     */
    public function disconnect(int $id): Response
    {
        $userId = (int) ($this->userId ?? 0);
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        try {
            $ok = $this->connectionService->disconnect($id, $userId, $this->tenantId);
            if (!$ok) {
                return $this->respond(null, 'Mailbox not found or already disconnected.', 404);
            }
            return $this->respond(['disconnected' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/email/mailboxes/{id}/sync
    // -------------------------------------------------------------------------

    /**
     * Trigger a manual sync for a mailbox.
     *
     * @param  int $id  Mailbox ID
     * @return Response
     */
    public function triggerSync(int $id): Response
    {
        $userId = (int) ($this->userId ?? 0);
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        try {
            $result = $this->syncService->syncMailbox($id);
            return $this->respond($result, null, 200);
        } catch (\RuntimeException $e) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 500;
            return $this->respond(null, $e->getMessage(), (int) $status);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/email/track/{token}/open.gif  (no auth)
    // -------------------------------------------------------------------------

    /**
     * Serve a 1x1 transparent GIF and record the open event.
     *
     * This endpoint must NOT require authentication (tracking pixels are
     * loaded by email clients, not authenticated users).
     *
     * @param  string $token  Tracking token UUID
     * @return Response       (headers set directly; body is binary GIF)
     */
    public function trackOpen(string $token): Response
    {
        $ip        = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        try {
            $this->trackingService->recordOpen($token, $ip, $userAgent);
        } catch (\Throwable) {
            // Never fail a tracking pixel request
        }

        // 1x1 transparent GIF (43 bytes)
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        header('Content-Type: image/gif');
        header('Content-Length: ' . strlen($gif));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $gif;
        exit;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/email/track/{token}/click  (no auth)
    // -------------------------------------------------------------------------

    /**
     * Record a link click event and redirect to the original URL.
     *
     * Query param: url (the original destination URL, URL-encoded)
     *
     * @param  string $token       Tracking token UUID
     * @param  array  $queryParams
     * @return Response
     */
    public function trackClick(string $token, array $queryParams = []): Response
    {
        $url       = trim((string) ($queryParams['url'] ?? ''));
        $ip        = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ($url === '') {
            return $this->respond(null, 'Missing url parameter.', 422);
        }

        try {
            $destination = $this->trackingService->recordClick($token, $url, $ip, $userAgent);
        } catch (\Throwable) {
            $destination = $url; // Fall through to redirect even on error
        }

        header('Location: ' . $destination, true, 302);
        exit;
    }
}
