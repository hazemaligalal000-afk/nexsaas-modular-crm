<?php
/**
 * CRM/Tasks/ActivityController.php
 *
 * REST endpoints for activity logging and timeline retrieval.
 *
 * Routes:
 *   GET  /api/v1/crm/activities                          → index()
 *   POST /api/v1/crm/activities                          → store()
 *   GET  /api/v1/crm/{recordType}/{recordId}/timeline    → timeline()
 *
 * Requirements: 15.2, 15.5
 */

declare(strict_types=1);

namespace CRM\Tasks;

use Core\BaseController;
use Core\Response;

class ActivityController extends BaseController
{
    private ActivityService $service;

    public function __construct(ActivityService $service)
    {
        $this->service = $service;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/activities
    // -------------------------------------------------------------------------

    /**
     * List activities for the current tenant.
     *
     * Query params: type, linked_record_type, linked_record_id, limit, offset
     *
     * Requirement 15.2
     *
     * @param  array $queryParams
     * @return Response
     */
    public function index(array $queryParams = []): Response
    {
        try {
            $activities = $this->service->list($queryParams);
            return $this->respond($activities);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/activities
    // -------------------------------------------------------------------------

    /**
     * Log a new activity.
     *
     * Body: type (call|email|meeting|note|task), linked_record_type,
     *       linked_record_id, subject?, body?, outcome?, duration_minutes?,
     *       activity_date?
     *
     * Requirement 15.2
     *
     * @param  array $body
     * @return Response
     */
    public function store(array $body): Response
    {
        try {
            $body['created_by']   = (int) $this->userId;
            $body['performed_by'] = (int) $this->userId;
            $activity = $this->service->log($body);
            return $this->respond($activity, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/{recordType}/{recordId}/timeline
    // -------------------------------------------------------------------------

    /**
     * Get the activity timeline for a linked CRM record.
     *
     * Requirement 15.5
     *
     * @param  string $recordType  contact|lead|deal|account
     * @param  int    $recordId
     * @return Response
     */
    public function timeline(string $recordType, int $recordId): Response
    {
        try {
            $timeline = $this->service->getTimeline($recordType, $recordId, $this->tenantId);
            return $this->respond($timeline);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
