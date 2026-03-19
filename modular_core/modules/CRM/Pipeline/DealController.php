<?php
/**
 * CRM/Pipeline/DealController.php
 *
 * REST endpoints for deal management.
 *
 * Routes (registered in module.json):
 *   GET    /api/v1/crm/deals              → index()
 *   POST   /api/v1/crm/deals              → create()
 *   GET    /api/v1/crm/deals/{id}         → show()
 *   PUT    /api/v1/crm/deals/{id}         → update()
 *   DELETE /api/v1/crm/deals/{id}         → delete()
 *   PUT    /api/v1/crm/deals/{id}/stage   → moveStage()
 *
 * Requirements: 10.4, 10.5
 */

declare(strict_types=1);

namespace CRM\Pipeline;

use Core\BaseController;
use Core\Response;
use CRM\Deals\DealService;

class DealController extends BaseController
{
    private DealService $service;

    public function __construct(DealService $service)
    {
        $this->service = $service;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/deals  — list view (Req 10.5)
    // -------------------------------------------------------------------------

    /**
     * List deals with optional filters.
     *
     * Query params:
     *   limit        int  (1–200, default 50)
     *   offset       int  (default 0)
     *   pipeline_id  int  filter by pipeline
     *   stage_id     int  filter by stage
     *   owner_id     int  filter by owner
     *   is_overdue   bool filter overdue deals
     *   is_stale     bool filter stale deals
     *   sort         string column name (default created_at)
     *   dir          string ASC|DESC (default DESC)
     *
     * @param  array $queryParams
     * @return Response
     */
    public function index(array $queryParams = []): Response
    {
        try {
            $limit  = max(1, min(200, (int) ($queryParams['limit']  ?? 50)));
            $offset = max(0, (int) ($queryParams['offset'] ?? 0));

            $filters = [];
            foreach (['pipeline_id', 'stage_id', 'owner_id'] as $key) {
                if (!empty($queryParams[$key])) {
                    $filters[$key] = (int) $queryParams[$key];
                }
            }
            foreach (['is_overdue', 'is_stale'] as $key) {
                if (isset($queryParams[$key])) {
                    $filters[$key] = filter_var($queryParams[$key], FILTER_VALIDATE_BOOLEAN);
                }
            }

            $allowedSort = ['created_at', 'updated_at', 'close_date', 'value', 'title'];
            $sort = in_array($queryParams['sort'] ?? '', $allowedSort, true)
                ? $queryParams['sort']
                : 'created_at';
            $dir = strtoupper($queryParams['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

            $deals = $this->service->listFiltered($limit, $offset, $filters, $sort, $dir);
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
     * Required body fields: title, pipeline_id, stage_id
     *
     * @param  array $body
     * @return Response
     */
    public function create(array $body): Response
    {
        if (empty($body['title'])) {
            return $this->respond(null, 'title is required.', 422);
        }
        if (empty($body['pipeline_id'])) {
            return $this->respond(null, 'pipeline_id is required.', 422);
        }
        if (empty($body['stage_id'])) {
            return $this->respond(null, 'stage_id is required.', 422);
        }

        try {
            $userId = (int) ($this->userId ?? 0);
            $id     = $this->service->create($body, $userId);
            $deal   = $this->service->findById($id);
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
            return $this->respond($this->service->findById($id));
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
    public function delete(int $id): Response
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
    // PUT /api/v1/crm/deals/{id}/stage  — Req 10.3, 10.4 (drag-and-drop)
    // -------------------------------------------------------------------------

    /**
     * Move a deal to a new stage (Kanban drag-and-drop).
     *
     * Body: { "stage_id": int }
     *
     * @param  int   $id
     * @param  array $body
     * @return Response
     */
    public function moveStage(int $id, array $body): Response
    {
        $stageId = isset($body['stage_id']) ? (int) $body['stage_id'] : 0;
        if ($stageId <= 0) {
            return $this->respond(null, 'stage_id is required and must be a positive integer.', 422);
        }

        try {
            $userId = (int) ($this->userId ?? 0);
            $this->service->moveStage($id, $stageId, $userId);
            return $this->respond($this->service->findById($id));
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
