<?php
/**
 * CRM/Calendar/SchedulingLinkController.php
 *
 * REST endpoints for scheduling links and public booking.
 *
 * Protected routes (JWT required):
 *   GET    /api/v1/crm/calendar/scheduling-links         → index()
 *   POST   /api/v1/crm/calendar/scheduling-links         → create()
 *   PUT    /api/v1/crm/calendar/scheduling-links/{id}    → update()
 *   DELETE /api/v1/crm/calendar/scheduling-links/{id}    → delete()
 *
 * Public routes (no auth):
 *   GET    /api/v1/crm/book/{slug}                       → showSlots()
 *   POST   /api/v1/crm/book/{slug}                       → book()
 *
 * Requirements: 16.5
 */

declare(strict_types=1);

namespace CRM\Calendar;

use Core\BaseController;
use Core\Response;

class SchedulingLinkController extends BaseController
{
    private SchedulingLinkService $service;

    public function __construct(SchedulingLinkService $service)
    {
        $this->service = $service;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/calendar/scheduling-links
    // -------------------------------------------------------------------------

    /**
     * List scheduling links for the current user.
     *
     * @return Response
     */
    public function index(): Response
    {
        $userId = (int) $this->userId;
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        try {
            $links = $this->service->list($userId);
            return $this->respond(['scheduling_links' => $links]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/calendar/scheduling-links
    // -------------------------------------------------------------------------

    /**
     * Create a new scheduling link.
     *
     * Body: { title, duration_minutes?, buffer_minutes?, availability_rules? }
     *
     * @param  array $body
     * @return Response
     */
    public function create(array $body): Response
    {
        $userId = (int) $this->userId;
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        if (empty($body['title'])) {
            return $this->respond(null, 'title is required.', 422);
        }

        try {
            $link = $this->service->create($userId, $body);
            return $this->respond($link, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/calendar/scheduling-links/{id}
    // -------------------------------------------------------------------------

    /**
     * Update a scheduling link.
     *
     * @param  int   $id
     * @param  array $body
     * @return Response
     */
    public function update(int $id, array $body): Response
    {
        $userId = (int) $this->userId;
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        try {
            $link = $this->service->update($id, $userId, $body);
            return $this->respond($link);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/calendar/scheduling-links/{id}
    // -------------------------------------------------------------------------

    /**
     * Delete a scheduling link.
     *
     * @param  int $id
     * @return Response
     */
    public function delete(int $id): Response
    {
        $userId = (int) $this->userId;
        if ($userId === 0) {
            return $this->respond(null, 'Unauthorized.', 401);
        }

        try {
            $ok = $this->service->delete($id, $userId);
            if (!$ok) {
                return $this->respond(null, 'Scheduling link not found.', 404);
            }
            return $this->respond(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/book/{slug}  — PUBLIC, no auth
    // -------------------------------------------------------------------------

    /**
     * Return available booking slots for the next 14 days.
     *
     * Requirement 16.5
     *
     * @param  string $slug
     * @param  array  $queryParams  start_date?, end_date?
     * @return Response
     */
    public function showSlots(string $slug, array $queryParams = []): Response
    {
        $startDate = $queryParams['start_date'] ?? date('Y-m-d');
        $endDate   = $queryParams['end_date']   ?? date('Y-m-d', strtotime('+14 days'));

        try {
            $result = $this->service->getAvailableSlots($slug, $startDate, $endDate);
            return $this->respond($result);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/book/{slug}  — PUBLIC, no auth
    // -------------------------------------------------------------------------

    /**
     * Create a booking via a scheduling link.
     *
     * Body: { booker_name, booker_email, start_at, notes? }
     *
     * Requirement 16.5
     *
     * @param  string $slug
     * @param  array  $body
     * @return Response
     */
    public function book(string $slug, array $body): Response
    {
        if (empty($body['booker_name'])) {
            return $this->respond(null, 'booker_name is required.', 422);
        }

        if (empty($body['booker_email']) || !filter_var($body['booker_email'], FILTER_VALIDATE_EMAIL)) {
            return $this->respond(null, 'A valid booker_email is required.', 422);
        }

        if (empty($body['start_at'])) {
            return $this->respond(null, 'start_at is required.', 422);
        }

        try {
            $booking = $this->service->createBooking($slug, $body);
            return $this->respond($booking, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
