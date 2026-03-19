<?php
/**
 * CRM/Analytics/ReportBuilderController.php
 *
 * REST endpoints for the custom report builder.
 *
 * Routes:
 *   GET    /api/v1/crm/reports/data-sources              → dataSources()
 *   GET    /api/v1/crm/reports                           → index()
 *   POST   /api/v1/crm/reports                           → create()
 *   GET    /api/v1/crm/reports/{id}                      → show()
 *   PUT    /api/v1/crm/reports/{id}                      → update()
 *   DELETE /api/v1/crm/reports/{id}                      → delete()
 *   POST   /api/v1/crm/reports/{id}/execute              → execute()
 *   GET    /api/v1/crm/reports/{id}/export/csv           → exportCsv()
 *   GET    /api/v1/crm/reports/{id}/export/pdf           → exportPdf()
 *   GET    /api/v1/crm/reports/{id}/schedules            → listSchedules()
 *   POST   /api/v1/crm/reports/{id}/schedules            → createSchedule()
 *   PUT    /api/v1/crm/reports/{id}/schedules/{sid}      → updateSchedule()
 *   DELETE /api/v1/crm/reports/{id}/schedules/{sid}      → deleteSchedule()
 *
 * Permissions:
 *   crm.reports.view   — read operations
 *   crm.reports.manage — write operations
 *
 * Requirements: 17.2, 17.4, 17.5
 */

declare(strict_types=1);

namespace CRM\Analytics;

use Core\BaseController;
use Core\Response;

class ReportBuilderController extends BaseController
{
    private ReportBuilderService  $reportService;
    private ReportScheduleService $scheduleService;

    public function __construct(
        ReportBuilderService  $reportService,
        ReportScheduleService $scheduleService
    ) {
        $this->reportService   = $reportService;
        $this->scheduleService = $scheduleService;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/reports/data-sources
    // Permission: crm.reports.view
    // -------------------------------------------------------------------------

    /**
     * List available data sources with their dimensions and metrics.
     *
     * @return Response
     */
    public function dataSources(): Response
    {
        try {
            $data = $this->reportService->getDataSources();
            return $this->respond($data);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/reports
    // Permission: crm.reports.view
    // -------------------------------------------------------------------------

    /**
     * List all saved reports for the current tenant.
     *
     * @return Response
     */
    public function index(): Response
    {
        try {
            $data = $this->reportService->listReports();
            return $this->respond($data);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/reports
    // Permission: crm.reports.manage
    // -------------------------------------------------------------------------

    /**
     * Create a new custom report.
     *
     * Body: { name, data_source, dimensions[], metrics[], filters[], sort_config, description }
     *
     * @param  array $body  Parsed request body
     * @return Response
     */
    public function create(array $body = []): Response
    {
        try {
            $body['created_by'] = $this->userId;
            $body['owner_id']   = $this->userId;
            $report = $this->reportService->saveReport($body);
            return $this->respond($report, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/reports/{id}
    // Permission: crm.reports.view
    // -------------------------------------------------------------------------

    /**
     * Get a single report definition.
     *
     * @param  int $id
     * @return Response
     */
    public function show(int $id): Response
    {
        try {
            $report = $this->reportService->getReport($id);
            return $this->respond($report);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/reports/{id}
    // Permission: crm.reports.manage
    // -------------------------------------------------------------------------

    /**
     * Update an existing report definition.
     *
     * @param  int   $id
     * @param  array $body
     * @return Response
     */
    public function update(int $id, array $body = []): Response
    {
        try {
            $body['id'] = $id;
            $report = $this->reportService->saveReport($body);
            return $this->respond($report);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/reports/{id}
    // Permission: crm.reports.manage
    // -------------------------------------------------------------------------

    /**
     * Soft-delete a report.
     *
     * @param  int $id
     * @return Response
     */
    public function delete(int $id): Response
    {
        try {
            $deleted = $this->reportService->deleteReport($id);
            if (!$deleted) {
                return $this->respond(null, 'Report not found.', 404);
            }
            return $this->respond(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/reports/{id}/execute
    // Permission: crm.reports.view
    // -------------------------------------------------------------------------

    /**
     * Execute a report and return result rows.
     *
     * @param  int   $id
     * @param  array $body  Optional runtime params
     * @return Response
     */
    public function execute(int $id, array $body = []): Response
    {
        try {
            $result = $this->reportService->executeReport($id, $body);
            return $this->respond($result);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/reports/{id}/export/csv
    // Permission: crm.reports.view
    // -------------------------------------------------------------------------

    /**
     * Export report as CSV download.
     * Note: the front-controller must detect the 'csv_export' key and stream
     * the content with appropriate headers instead of wrapping in API_Response.
     *
     * @param  int   $id
     * @param  array $queryParams
     * @return Response
     */
    public function exportCsv(int $id, array $queryParams = []): Response
    {
        try {
            $csv = $this->reportService->exportCsv($id, $queryParams);
            // Return as data so the front-controller can stream it
            return $this->respond([
                'csv_export'  => true,
                'content'     => $csv,
                'filename'    => "report_{$id}_" . date('Ymd_His') . '.csv',
                'content_type'=> 'text/csv',
            ]);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/reports/{id}/export/pdf
    // Permission: crm.reports.view
    // -------------------------------------------------------------------------

    /**
     * Export report as PDF download.
     *
     * @param  int   $id
     * @param  array $queryParams
     * @return Response
     */
    public function exportPdf(int $id, array $queryParams = []): Response
    {
        try {
            $filePath = $this->reportService->exportPdf($id, $queryParams);
            return $this->respond([
                'pdf_export'  => true,
                'file_path'   => $filePath,
                'filename'    => "report_{$id}_" . date('Ymd_His') . '.pdf',
                'content_type'=> 'application/pdf',
            ]);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/reports/{id}/schedules
    // Permission: crm.reports.view
    // -------------------------------------------------------------------------

    /**
     * List schedules for a report.
     *
     * @param  int $id  Report ID
     * @return Response
     */
    public function listSchedules(int $id): Response
    {
        try {
            $schedules = $this->scheduleService->listSchedules($id);
            return $this->respond($schedules);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/reports/{id}/schedules
    // Permission: crm.reports.manage
    // -------------------------------------------------------------------------

    /**
     * Create a schedule for a report.
     *
     * Body: { frequency, next_run_at, recipients[], format, is_active }
     *
     * @param  int   $id    Report ID
     * @param  array $body
     * @return Response
     */
    public function createSchedule(int $id, array $body = []): Response
    {
        try {
            $body['report_id']   = $id;
            $body['created_by']  = $this->userId;
            $schedule = $this->scheduleService->createSchedule($body);
            return $this->respond($schedule, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/reports/{id}/schedules/{scheduleId}
    // Permission: crm.reports.manage
    // -------------------------------------------------------------------------

    /**
     * Update a schedule.
     *
     * @param  int   $id          Report ID (unused but kept for route consistency)
     * @param  int   $scheduleId
     * @param  array $body
     * @return Response
     */
    public function updateSchedule(int $id, int $scheduleId, array $body = []): Response
    {
        try {
            $schedule = $this->scheduleService->updateSchedule($scheduleId, $body);
            return $this->respond($schedule);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/reports/{id}/schedules/{scheduleId}
    // Permission: crm.reports.manage
    // -------------------------------------------------------------------------

    /**
     * Delete a schedule.
     *
     * @param  int $id          Report ID (unused but kept for route consistency)
     * @param  int $scheduleId
     * @return Response
     */
    public function deleteSchedule(int $id, int $scheduleId): Response
    {
        try {
            $deleted = $this->scheduleService->deleteSchedule($scheduleId);
            if (!$deleted) {
                return $this->respond(null, 'Schedule not found.', 404);
            }
            return $this->respond(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
