<?php
/**
 * CRM/Analytics/DashboardController.php
 *
 * REST endpoints for the dashboard builder.
 *
 * Routes:
 *   GET    /api/v1/crm/dashboards                              → index()
 *   POST   /api/v1/crm/dashboards                             → create()
 *   GET    /api/v1/crm/dashboards/{id}                        → show()
 *   PUT    /api/v1/crm/dashboards/{id}/layout                 → updateLayout()
 *   POST   /api/v1/crm/dashboards/{id}/widgets                → addWidget()
 *   DELETE /api/v1/crm/dashboards/{id}/widgets/{widgetId}     → removeWidget()
 *   GET    /api/v1/crm/dashboards/{id}/widgets/{widgetId}/data → getWidgetData()
 *
 * Permissions:
 *   crm.dashboard.view   — read operations
 *   crm.dashboard.manage — write operations
 *
 * Requirements: 17.6, 17.7
 */

declare(strict_types=1);

namespace CRM\Analytics;

use Core\BaseController;
use Core\Response;

class DashboardController extends BaseController
{
    private DashboardService $service;

    public function __construct(DashboardService $service)
    {
        $this->service = $service;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/dashboards
    // Permission: crm.dashboard.view
    // -------------------------------------------------------------------------

    /**
     * List all dashboards for the current tenant.
     *
     * @return Response
     */
    public function index(): Response
    {
        try {
            $data = $this->service->listDashboards();
            return $this->respond($data);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/dashboards
    // Permission: crm.dashboard.manage
    // -------------------------------------------------------------------------

    /**
     * Create a new dashboard.
     *
     * Body: { name, layout_config?, is_default? }
     *
     * @param  array $body
     * @return Response
     */
    public function create(array $body = []): Response
    {
        try {
            $body['created_by'] = $this->userId;
            $body['owner_id']   = $this->userId;
            $dashboard = $this->service->createDashboard($body);
            return $this->respond($dashboard, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/dashboards/{id}
    // Permission: crm.dashboard.view
    // -------------------------------------------------------------------------

    /**
     * Get a dashboard with all its widgets.
     *
     * @param  int $id
     * @return Response
     */
    public function show(int $id): Response
    {
        try {
            $dashboard = $this->service->getDashboard($id);
            return $this->respond($dashboard);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/dashboards/{id}/layout
    // Permission: crm.dashboard.manage
    // -------------------------------------------------------------------------

    /**
     * Update grid layout for all widgets in a dashboard.
     *
     * Body: { widgets: [{ widget_id, grid_x, grid_y, grid_w, grid_h }] }
     *
     * @param  int   $id
     * @param  array $body
     * @return Response
     */
    public function updateLayout(int $id, array $body = []): Response
    {
        try {
            $widgets = $body['widgets'] ?? [];
            if (!is_array($widgets)) {
                return $this->respond(null, 'widgets must be an array.', 422);
            }
            $dashboard = $this->service->updateLayout($id, $widgets);
            return $this->respond($dashboard);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/dashboards/{id}/widgets
    // Permission: crm.dashboard.manage
    // -------------------------------------------------------------------------

    /**
     * Add a widget to a dashboard.
     *
     * Body: { widget_type, title, report_id?, config?, grid_x?, grid_y?, grid_w?, grid_h?, refresh_interval_seconds? }
     *
     * @param  int   $id
     * @param  array $body
     * @return Response
     */
    public function addWidget(int $id, array $body = []): Response
    {
        try {
            $body['created_by'] = $this->userId;
            $widget = $this->service->addWidget($id, $body);
            return $this->respond($widget, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/dashboards/{id}/widgets/{widgetId}
    // Permission: crm.dashboard.manage
    // -------------------------------------------------------------------------

    /**
     * Remove (soft-delete) a widget from a dashboard.
     *
     * @param  int $id        Dashboard ID (used for context; widget is tenant-scoped)
     * @param  int $widgetId
     * @return Response
     */
    public function removeWidget(int $id, int $widgetId): Response
    {
        try {
            $deleted = $this->service->removeWidget($widgetId);
            if (!$deleted) {
                return $this->respond(null, 'Widget not found.', 404);
            }
            return $this->respond(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/dashboards/{id}/widgets/{widgetId}/data
    // Permission: crm.dashboard.view
    // -------------------------------------------------------------------------

    /**
     * Fetch the underlying data for a specific widget.
     *
     * @param  int $id        Dashboard ID
     * @param  int $widgetId
     * @return Response
     */
    public function getWidgetData(int $id, int $widgetId): Response
    {
        try {
            $data = $this->service->getWidgetData($widgetId);
            return $this->respond($data);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
