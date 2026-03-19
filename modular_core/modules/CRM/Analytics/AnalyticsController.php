<?php
/**
 * CRM/Analytics/AnalyticsController.php
 *
 * REST endpoints for pre-built CRM reports.
 *
 * Routes:
 *   GET /api/v1/crm/reports/pipeline-summary       → pipelineSummary()
 *   GET /api/v1/crm/reports/deal-velocity          → dealVelocity()
 *   GET /api/v1/crm/reports/lead-conversion        → leadConversionRate()
 *   GET /api/v1/crm/reports/activity-summary       → activitySummary()
 *   GET /api/v1/crm/reports/revenue-forecast       → revenueForecast()
 *
 * Requirements: 17.1, 17.3
 */

declare(strict_types=1);

namespace CRM\Analytics;

use Core\BaseController;
use Core\Response;

class AnalyticsController extends BaseController
{
    private AnalyticsService $service;

    public function __construct(AnalyticsService $service)
    {
        $this->service = $service;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/reports/pipeline-summary
    // -------------------------------------------------------------------------

    /**
     * Pipeline summary report.
     *
     * Query params:
     *   pipeline_id (optional int)
     *
     * @param  array $queryParams
     * @return Response
     */
    public function pipelineSummary(array $queryParams = []): Response
    {
        try {
            $pipelineId = isset($queryParams['pipeline_id'])
                ? (int) $queryParams['pipeline_id']
                : null;

            $data = $this->service->pipelineSummary($pipelineId);
            return $this->respond($data);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/reports/deal-velocity
    // -------------------------------------------------------------------------

    /**
     * Deal velocity report.
     *
     * Query params:
     *   date_from   (optional, ISO date)
     *   date_to     (optional, ISO date)
     *   pipeline_id (optional int)
     *
     * @param  array $queryParams
     * @return Response
     */
    public function dealVelocity(array $queryParams = []): Response
    {
        try {
            $data = $this->service->dealVelocity(
                $queryParams['date_from']   ?? null,
                $queryParams['date_to']     ?? null,
                isset($queryParams['pipeline_id']) ? (int) $queryParams['pipeline_id'] : null
            );
            return $this->respond($data);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/reports/lead-conversion
    // -------------------------------------------------------------------------

    /**
     * Lead conversion rate report.
     *
     * Query params:
     *   date_from (optional, ISO date)
     *   date_to   (optional, ISO date)
     *
     * @param  array $queryParams
     * @return Response
     */
    public function leadConversionRate(array $queryParams = []): Response
    {
        try {
            $data = $this->service->leadConversionRate(
                $queryParams['date_from'] ?? null,
                $queryParams['date_to']   ?? null
            );
            return $this->respond($data);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/reports/activity-summary
    // -------------------------------------------------------------------------

    /**
     * Activity summary report.
     *
     * Query params:
     *   date_from (optional, ISO date)
     *   date_to   (optional, ISO date)
     *   owner_id  (optional int)
     *
     * @param  array $queryParams
     * @return Response
     */
    public function activitySummary(array $queryParams = []): Response
    {
        try {
            $data = $this->service->activitySummary(
                $queryParams['date_from'] ?? null,
                $queryParams['date_to']   ?? null,
                isset($queryParams['owner_id']) ? (int) $queryParams['owner_id'] : null
            );
            return $this->respond($data);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/reports/revenue-forecast
    // -------------------------------------------------------------------------

    /**
     * Revenue forecast report.
     *
     * Query params:
     *   months      (optional int, default 6, max 24)
     *   pipeline_id (optional int)
     *
     * @param  array $queryParams
     * @return Response
     */
    public function revenueForecast(array $queryParams = []): Response
    {
        try {
            $months = isset($queryParams['months'])
                ? max(1, min(24, (int) $queryParams['months']))
                : 6;

            $pipelineId = isset($queryParams['pipeline_id'])
                ? (int) $queryParams['pipeline_id']
                : null;

            $data = $this->service->revenueForecast($months, $pipelineId);
            return $this->respond($data);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
