<?php
/**
 * CRM/Deals/DealController.php
 *
 * REST endpoints for deal and pipeline management.
 *
 * Routes:
 *   GET    /api/v1/crm/deals                        → list()
 *   POST   /api/v1/crm/deals                        → create()
 *   GET    /api/v1/crm/deals/{id}                   → show()
 *   PUT    /api/v1/crm/deals/{id}                   → update()
 *   DELETE /api/v1/crm/deals/{id}                   → destroy()
 *   PUT    /api/v1/crm/deals/{id}/stage             → moveStage()
 *   GET    /api/v1/crm/pipelines/{id}/forecast      → forecast()
 *   GET    /api/v1/crm/pipelines/{id}/kanban        → kanban()
 *
 * Requirements: 10.4, 10.5, 10.7
 */

declare(strict_types=1);

namespace CRM\Deals;

use Core\BaseController;
use Core\Response;

class DealController extends BaseController
{
    private DealService $service;

    public function __construct(DealService $service)
    {
        $this->service = $service;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/deals
    // -------------------------------------------------------------------------

    /**
     * List deals for the current tenant.
     *
     * @param  array $queryParams
     * @return Response
     */
    public function list(array $queryParams = []): Response
    {
        try {
            $limit  = max(1, min(200, (int) ($queryParams['limit']  ?? 50)));
            $offset = max(0, (int) ($queryParams['offset'] ?? 0));
            $deals  = $this->service->list($limit, $offset);
            return $this->respond($deals);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/deals
    // -------------------------------------------------------------------------

    /**
     * Create a new deal.
     *
     * @param  array $body
     * @param  int   $createdBy
     * @return Response
     */
    public function create(array $body, int $createdBy): Response
    {
        try {
            $id   = $this->service->create($body, $createdBy);
            $deal = $this->service->findById($id);
            return $this->respond($deal, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/deals/{id}
    // -------------------------------------------------------------------------

    /**
     * Show a single deal.
     *
     * @param  int $id
     * @return Response
     */
    public function show(int $id): Response
    {
        try {
            $deal = $this->service->findById($id);

            if ($deal === null) {
                return $this->respond(null, 'Deal not found.', 404);
            }

            return $this->respond($deal);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/deals/{id}
    // -------------------------------------------------------------------------

    /**
     * Update a deal.
     *
     * @param  int   $id
     * @param  array $body
     * @return Response
     */
    public function update(int $id, array $body): Response
    {
        try {
            $updated = $this->service->update($id, $body);

            if (!$updated) {
                return $this->respond(null, 'Deal not found or no changes made.', 404);
            }

            $deal = $this->service->findById($id);
            return $this->respond($deal);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/deals/{id}
    // -------------------------------------------------------------------------

    /**
     * Soft-delete a deal.
     *
     * @param  int $id
     * @return Response
     */
    public function destroy(int $id): Response
    {
        try {
            $deleted = $this->service->delete($id);

            if (!$deleted) {
                return $this->respond(null, 'Deal not found.', 404);
            }

            return $this->respond(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/deals/{id}/stage
    // -------------------------------------------------------------------------

    /**
     * Move a deal to a new stage.
     *
     * Body: { "stage_id": int }
     *
     * @param  int   $dealId
     * @param  array $body
     * @param  int   $userId
     * @return Response
     */
    public function moveStage(int $dealId, array $body, int $userId): Response
    {
        $newStageId = isset($body['stage_id']) ? (int) $body['stage_id'] : 0;

        if ($newStageId <= 0) {
            return $this->respond(null, 'stage_id is required and must be a positive integer.', 422);
        }

        try {
            $this->service->moveStage($dealId, $newStageId, $userId);
            $deal = $this->service->findById($dealId);
            return $this->respond($deal);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/pipelines/{id}/forecast
    // -------------------------------------------------------------------------

    /**
     * Compute the weighted forecast for a pipeline.
     *
     * @param  int $pipelineId
     * @return Response
     */
    public function forecast(int $pipelineId): Response
    {
        try {
            $result = $this->service->computeForecast($pipelineId);
            return $this->respond($result);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/pipelines/{id}/kanban
    // -------------------------------------------------------------------------

    /**
     * Return deals grouped by stage for a Kanban board.
     *
     * @param  int $pipelineId
     * @return Response
     */
    public function kanban(int $pipelineId): Response
    {
        try {
            $board = $this->service->getKanban($pipelineId);
            return $this->respond($board);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
