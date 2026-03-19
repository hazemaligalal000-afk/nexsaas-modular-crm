<?php
/**
 * CRM/Pipeline/PipelineController.php
 *
 * REST endpoints for pipeline management and Kanban/forecast views.
 *
 * Routes (registered in module.json):
 *   GET  /api/v1/crm/pipelines                  → index()
 *   POST /api/v1/crm/pipelines                  → create()
 *   GET  /api/v1/crm/pipelines/{id}/forecast    → forecast()
 *   GET  /api/v1/crm/pipelines/{id}/kanban      → kanban()
 *
 * Requirements: 10.4, 10.5, 10.7
 */

declare(strict_types=1);

namespace CRM\Pipeline;

use Core\BaseController;
use Core\Response;
use CRM\Deals\DealService;
use CRM\Deals\PipelineService;

class PipelineController extends BaseController
{
    private PipelineService $pipelineService;
    private DealService     $dealService;

    public function __construct(PipelineService $pipelineService, DealService $dealService)
    {
        $this->pipelineService = $pipelineService;
        $this->dealService     = $dealService;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/pipelines
    // -------------------------------------------------------------------------

    /**
     * List all pipelines for the current tenant.
     *
     * @return Response
     */
    public function index(): Response
    {
        try {
            $pipelines = $this->pipelineService->listPipelines();
            return $this->respond($pipelines);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/pipelines
    // -------------------------------------------------------------------------

    /**
     * Create a new pipeline.
     *
     * Required body: { "name": string }
     *
     * @param  array $body
     * @return Response
     */
    public function create(array $body): Response
    {
        if (empty($body['name'])) {
            return $this->respond(null, 'name is required.', 422);
        }

        try {
            $userId = (int) ($this->userId ?? 0);
            $id     = $this->pipelineService->createPipeline($body['name'], $userId);
            $pipeline = $this->pipelineService->findPipelineById($id);
            return $this->respond($pipeline, null, 201);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/pipelines/{id}/forecast  — Req 10.7
    // -------------------------------------------------------------------------

    /**
     * Compute the weighted pipeline forecast.
     *
     * Returns: { weighted_value: float, deal_count: int }
     * where weighted_value = SUM(deal.value × deal.win_probability)
     *
     * @param  int $id  Pipeline ID
     * @return Response
     */
    public function forecast(int $id): Response
    {
        try {
            $pipeline = $this->pipelineService->findPipelineById($id);
            if ($pipeline === null) {
                return $this->respond(null, 'Pipeline not found.', 404);
            }

            $result = $this->dealService->computeForecast($id);
            return $this->respond([
                'pipeline_id'    => $id,
                'pipeline_name'  => $pipeline['name'],
                'weighted_value' => $result['weighted_value'],
                'deal_count'     => $result['deal_count'],
            ]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/pipelines/{id}/kanban  — Req 10.4
    // -------------------------------------------------------------------------

    /**
     * Return deals grouped by stage for a Kanban board view.
     *
     * Response shape:
     * [
     *   {
     *     "id": int,
     *     "name": string,
     *     "position": int,
     *     "is_closed_won": bool,
     *     "is_closed_lost": bool,
     *     "deals": [ { id, title, value, win_probability, close_date, is_stale, is_overdue, ... } ]
     *   },
     *   ...
     * ]
     *
     * @param  int $id  Pipeline ID
     * @return Response
     */
    public function kanban(int $id): Response
    {
        try {
            $pipeline = $this->pipelineService->findPipelineById($id);
            if ($pipeline === null) {
                return $this->respond(null, 'Pipeline not found.', 404);
            }

            $board = $this->dealService->getKanban($id);
            return $this->respond($board);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
