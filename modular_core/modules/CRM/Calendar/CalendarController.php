<?php
/**
 * CRM/Calendar/CalendarController.php
 *
 * REST endpoints for calendar views and Google/Outlook OAuth 2.0 connections.
 *
 * Routes:
 *   GET    /api/v1/crm/calendar                          → index()
 *   GET    /api/v1/crm/calendar/connections              → connections()
 *   POST   /api/v1/crm/calendar/connect/{provider}       → connect()
 *   GET    /api/v1/crm/calendar/callback/{provider}      → callback()
 *   DELETE /api/v1/crm/calendar/connections/{id}         → disconnect()
 *   POST   /api/v1/crm/calendar/sync                     → sync()
 *
 * Requirements: 16.1, 16.2, 16.3, 16.4
 */

declare(strict_types=1);

namespace CRM\Calendar;

use Core\BaseController;
use Core\Response;

class CalendarController extends BaseController
{
    private CalendarService $service;

    public function __construct(CalendarService $service)
    {
        $this->service = $service;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/calendar
    // -------------------------------------------------------------------------

    /**
     * Return calendar view (day|week|month) for the current user.
     *
     * Query params:
     *   view    day|week|month  (default: week)
     *   date    YYYY-MM-DD      (default: today)
     *   user_id int             (optional, defaults to current user)
     *
     * Requirement 16.1
     *
     * @param  array $queryParams
     * @return Response
     */
    public function index(array $queryParams = []): Response
    {
        $userId = isset($queryParams['user_id'])
            ? (int) $queryParams['user_id']
            : (int) $this->userId;

        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        $view = in_array($queryParams['view'] ?? '', ['day', 'week', 'month'], true)
            ? $queryParams['view']
            : 'week';

        $date = !empty($queryParams['date'])
            ? $queryParams['date']
            : date('Y-m-d');

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->respond(null, 'date must be in YYYY-MM-DD format.', 422);
        }

        try {
            $result = $this->service->getCalendarView($this->tenantId, $userId, $view, $date);
            return $this->respond($result);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/calendar/connections
    // -------------------------------------------------------------------------

    /**
     * List connected external calendars for the current user.
     *
     * Requirement 16.2
     *
     * @return Response
     */
    public function connections(): Response
    {
        $userId = (int) $this->userId;
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        try {
            $connections = $this->service->getConnections($userId);
            return $this->respond(['connections' => $connections]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/calendar/connect/{provider}
    // -------------------------------------------------------------------------

    /**
     * Initiate OAuth flow for Google or Outlook calendar.
     *
     * Returns: { auth_url: "https://..." }
     *
     * Requirement 16.2
     *
     * @param  string $provider  google|outlook
     * @return Response
     */
    public function connect(string $provider): Response
    {
        $userId = (int) $this->userId;
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        try {
            $authUrl = $this->service->getAuthUrl($userId, $provider);
            return $this->respond(['auth_url' => $authUrl]);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/calendar/callback/{provider}
    // -------------------------------------------------------------------------

    /**
     * Handle OAuth callback from Google or Outlook.
     *
     * Exchanges code for tokens, stores connection, redirects to frontend.
     *
     * Requirement 16.2
     *
     * @param  string $provider
     * @param  array  $queryParams  code, state (user_id)
     * @return Response
     */
    public function callback(string $provider, array $queryParams = []): Response
    {
        $code   = trim((string) ($queryParams['code']  ?? ''));
        $userId = (int) ($queryParams['state'] ?? 0);

        if ($code === '') {
            return $this->respond(null, 'Missing authorization code.', 422);
        }

        if ($userId === 0) {
            return $this->respond(null, 'Invalid state parameter.', 422);
        }

        try {
            $connection = $this->service->connectCalendar($userId, $provider, $code);
            $frontUrl   = $_ENV['FRONTEND_URL'] ?? getenv('FRONTEND_URL') ?: '/';
            $redirect   = rtrim($frontUrl, '/') . '/settings/calendar?connected=1&provider=' . urlencode($provider);

            header('Location: ' . $redirect, true, 302);
            return $this->respond(['connection' => $connection, 'redirect' => $redirect]);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 400;
            return $this->respond(null, $e->getMessage(), (int) $status);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/calendar/connections/{id}
    // -------------------------------------------------------------------------

    /**
     * Disconnect a calendar connection.
     *
     * Requirement 16.2
     *
     * @param  int $id
     * @return Response
     */
    public function disconnect(int $id): Response
    {
        $userId = (int) $this->userId;
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        try {
            $ok = $this->service->disconnectCalendar($userId, $id);
            if (!$ok) {
                return $this->respond(null, 'Connection not found or already disconnected.', 404);
            }
            return $this->respond(['disconnected' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/calendar/sync
    // -------------------------------------------------------------------------

    /**
     * Manually trigger a calendar sync for the current user.
     *
     * Requirements 16.3, 16.4
     *
     * @return Response
     */
    public function sync(): Response
    {
        $userId = (int) $this->userId;
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        try {
            $result = $this->service->triggerSync($userId);
            return $this->respond($result);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
