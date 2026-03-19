<?php
/**
 * CRM/Analytics/DashboardService.php
 *
 * Dashboard builder: create/manage dashboards with configurable grid layouts
 * and real-time WebSocket widget refresh via Redis pub/sub.
 *
 * Requirements: 17.6, 17.7
 */

declare(strict_types=1);

namespace CRM\Analytics;

use Core\BaseService;

class DashboardService extends BaseService
{
    private string $tenantId;
    private string $companyCode;
    private string $userId;

    private AnalyticsService    $analyticsService;
    private ReportBuilderService $reportBuilderService;

    /** @var \Redis|\Predis\Client|null */
    private $redis;

    private const VALID_WIDGET_TYPES = [
        'report',
        'pipeline_summary',
        'deal_velocity',
        'lead_conversion',
        'activity_summary',
        'revenue_forecast',
        'custom',
    ];

    public function __construct(
        $db,
        string $tenantId,
        string $companyCode,
        string $userId = '',
        ?AnalyticsService $analyticsService = null,
        ?ReportBuilderService $reportBuilderService = null,
        $redis = null
    ) {
        parent::__construct($db);
        $this->tenantId             = $tenantId;
        $this->companyCode          = $companyCode;
        $this->userId               = $userId;
        $this->analyticsService     = $analyticsService
            ?? new AnalyticsService($db, $tenantId, $companyCode);
        $this->reportBuilderService = $reportBuilderService
            ?? new ReportBuilderService($db, $tenantId, $companyCode, $userId);
        $this->redis                = $redis;
    }

    // -------------------------------------------------------------------------
    // Dashboard CRUD — Requirement 17.6
    // -------------------------------------------------------------------------

    /**
     * Create a new dashboard.
     *
     * @param  array $data  Must include: name. Optional: layout_config, is_default.
     * @return array  Created dashboard row.
     */
    public function createDashboard(array $data): array
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Dashboard name is required.');
        }

        $now = $this->now();

        // If this is set as default, unset any existing default for this owner
        if (!empty($data['is_default'])) {
            $this->clearDefaultDashboard();
        }

        $payload = [
            'tenant_id'     => $this->tenantId,
            'company_code'  => $this->companyCode,
            'name'          => $data['name'],
            'owner_id'      => (int) ($data['owner_id'] ?? $this->userId),
            'layout_config' => json_encode($data['layout_config'] ?? (object)[]),
            'is_default'    => isset($data['is_default']) ? (bool) $data['is_default'] : false,
            'created_by'    => (int) ($data['created_by'] ?? $this->userId),
            'created_at'    => $now,
            'updated_at'    => $now,
        ];

        $cols   = implode(', ', array_keys($payload));
        $places = implode(', ', array_fill(0, count($payload), '?'));
        $sql    = "INSERT INTO dashboards ({$cols}) VALUES ({$places})";

        $rs = $this->db->Execute($sql, array_values($payload));
        if ($rs === false) {
            throw new \RuntimeException('createDashboard failed: ' . $this->db->ErrorMsg());
        }

        $id = (int) $this->db->Insert_ID();
        return $this->getDashboard($id);
    }

    /**
     * Fetch a dashboard with all its active widgets.
     *
     * @param  int $dashboardId
     * @return array
     * @throws \RuntimeException if not found
     */
    public function getDashboard(int $dashboardId): array
    {
        $sql = <<<SQL
            SELECT *
            FROM dashboards
            WHERE id           = ?
              AND tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
        SQL;

        $rs = $this->db->Execute($sql, [$dashboardId, $this->tenantId, $this->companyCode]);

        if ($rs === false) {
            throw new \RuntimeException('getDashboard query failed: ' . $this->db->ErrorMsg());
        }

        if ($rs->EOF) {
            throw new \RuntimeException("Dashboard {$dashboardId} not found.");
        }

        $dashboard = $this->decodeJsonFields($rs->fields, ['layout_config']);
        $dashboard['widgets'] = $this->getWidgets($dashboardId);

        return $dashboard;
    }

    /**
     * List all active dashboards for the current tenant/user.
     *
     * @return array
     */
    public function listDashboards(): array
    {
        $sql = <<<SQL
            SELECT id, name, owner_id, layout_config, is_default, created_at, updated_at
            FROM dashboards
            WHERE tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
            ORDER BY is_default DESC, created_at DESC
        SQL;

        $rs = $this->db->Execute($sql, [$this->tenantId, $this->companyCode]);

        if ($rs === false) {
            throw new \RuntimeException('listDashboards query failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $this->decodeJsonFields($rs->fields, ['layout_config']);
            $rs->MoveNext();
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Layout management — Requirement 17.6
    // -------------------------------------------------------------------------

    /**
     * Update grid positions/sizes for all widgets in one transaction.
     *
     * @param  int   $dashboardId
     * @param  array $widgets  Array of { widget_id, grid_x, grid_y, grid_w, grid_h }
     * @return array  Updated dashboard with widgets.
     */
    public function updateLayout(int $dashboardId, array $widgets): array
    {
        // Verify dashboard belongs to this tenant
        $this->assertDashboardOwnership($dashboardId);

        $this->transaction(function () use ($dashboardId, $widgets) {
            $now = $this->now();

            foreach ($widgets as $w) {
                $widgetId = (int) ($w['widget_id'] ?? 0);
                if ($widgetId <= 0) {
                    continue;
                }

                $sql = <<<SQL
                    UPDATE dashboard_widgets
                    SET grid_x       = ?,
                        grid_y       = ?,
                        grid_w       = ?,
                        grid_h       = ?,
                        updated_at   = ?
                    WHERE id           = ?
                      AND dashboard_id = ?
                      AND tenant_id    = ?
                      AND company_code = ?
                      AND deleted_at   IS NULL
                SQL;

                $this->db->Execute($sql, [
                    (int) ($w['grid_x'] ?? 0),
                    (int) ($w['grid_y'] ?? 0),
                    max(1, (int) ($w['grid_w'] ?? 4)),
                    max(1, (int) ($w['grid_h'] ?? 3)),
                    $now,
                    $widgetId,
                    $dashboardId,
                    $this->tenantId,
                    $this->companyCode,
                ]);
            }

            // Touch dashboard updated_at
            $this->db->Execute(
                "UPDATE dashboards SET updated_at = ? WHERE id = ?",
                [$now, $dashboardId]
            );
        });

        return $this->getDashboard($dashboardId);
    }

    // -------------------------------------------------------------------------
    // Widget management — Requirement 17.6
    // -------------------------------------------------------------------------

    /**
     * Add a widget to a dashboard.
     *
     * @param  int   $dashboardId
     * @param  array $widgetConfig  Must include: widget_type, title.
     *                              Optional: report_id, config, grid_x/y/w/h, refresh_interval_seconds.
     * @return array  Created widget row.
     */
    public function addWidget(int $dashboardId, array $widgetConfig): array
    {
        $this->assertDashboardOwnership($dashboardId);

        $widgetType = $widgetConfig['widget_type'] ?? '';
        if (!in_array($widgetType, self::VALID_WIDGET_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid widget_type '{$widgetType}'. Must be one of: " . implode(', ', self::VALID_WIDGET_TYPES)
            );
        }

        if (empty($widgetConfig['title'])) {
            throw new \InvalidArgumentException('Widget title is required.');
        }

        $now = $this->now();

        $payload = [
            'tenant_id'                => $this->tenantId,
            'company_code'             => $this->companyCode,
            'dashboard_id'             => $dashboardId,
            'widget_type'              => $widgetType,
            'report_id'                => isset($widgetConfig['report_id']) ? (int) $widgetConfig['report_id'] : null,
            'title'                    => $widgetConfig['title'],
            'config'                   => json_encode($widgetConfig['config'] ?? (object)[]  ),
            'grid_x'                   => (int) ($widgetConfig['grid_x'] ?? 0),
            'grid_y'                   => (int) ($widgetConfig['grid_y'] ?? 0),
            'grid_w'                   => max(1, (int) ($widgetConfig['grid_w'] ?? 4)),
            'grid_h'                   => max(1, (int) ($widgetConfig['grid_h'] ?? 3)),
            'refresh_interval_seconds' => max(60, (int) ($widgetConfig['refresh_interval_seconds'] ?? 300)),
            'created_by'               => (int) ($widgetConfig['created_by'] ?? $this->userId),
            'created_at'               => $now,
            'updated_at'               => $now,
        ];

        $cols   = implode(', ', array_keys($payload));
        $places = implode(', ', array_fill(0, count($payload), '?'));
        $sql    = "INSERT INTO dashboard_widgets ({$cols}) VALUES ({$places})";

        $rs = $this->db->Execute($sql, array_values($payload));
        if ($rs === false) {
            throw new \RuntimeException('addWidget failed: ' . $this->db->ErrorMsg());
        }

        $widgetId = (int) $this->db->Insert_ID();
        return $this->findWidget($widgetId);
    }

    /**
     * Soft-delete a widget.
     *
     * @param  int $widgetId
     * @return bool
     */
    public function removeWidget(int $widgetId): bool
    {
        $now = $this->now();
        $sql = <<<SQL
            UPDATE dashboard_widgets
            SET deleted_at = ?
            WHERE id           = ?
              AND tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
        SQL;

        $rs = $this->db->Execute($sql, [$now, $widgetId, $this->tenantId, $this->companyCode]);

        if ($rs === false) {
            throw new \RuntimeException('removeWidget failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Widget data — Requirement 17.6, 17.7
    // -------------------------------------------------------------------------

    /**
     * Fetch the underlying report data for a widget.
     * Delegates to AnalyticsService or ReportBuilderService based on widget_type.
     *
     * @param  int $widgetId
     * @return array  { widget_id, widget_type, data, refreshed_at }
     */
    public function getWidgetData(int $widgetId): array
    {
        $widget = $this->findWidget($widgetId);
        $config = $widget['config'] ?? [];

        $data = $this->fetchWidgetData($widget, $config);

        // Update last_refreshed_at
        $now = $this->now();
        $this->db->Execute(
            "UPDATE dashboard_widgets SET last_refreshed_at = ?, updated_at = ? WHERE id = ?",
            [$now, $now, $widgetId]
        );

        return [
            'widget_id'    => $widgetId,
            'widget_type'  => $widget['widget_type'],
            'data'         => $data,
            'refreshed_at' => $now,
        ];
    }

    // -------------------------------------------------------------------------
    // Real-time WebSocket publish — Requirement 17.7
    // -------------------------------------------------------------------------

    /**
     * Publish a widget update to Redis pub/sub so the WebSocket server
     * pushes the update to subscribed clients.
     *
     * Channel: dashboard:{tenant_id}:{dashboard_id}
     *
     * @param  int $dashboardId
     * @param  int $widgetId
     * @return void
     */
    public function publishWidgetUpdate(int $dashboardId, int $widgetId): void
    {
        if ($this->redis === null) {
            return;
        }

        $widget = $this->findWidget($widgetId);
        $config = $widget['config'] ?? [];
        $data   = $this->fetchWidgetData($widget, $config);

        $now     = $this->now();
        $channel = "dashboard:{$this->tenantId}:{$dashboardId}";

        $payload = json_encode([
            'widget_id'    => $widgetId,
            'data'         => $data,
            'refreshed_at' => $now,
        ]);

        $this->redis->publish($channel, $payload);

        // Update last_refreshed_at
        $this->db->Execute(
            "UPDATE dashboard_widgets SET last_refreshed_at = ?, updated_at = ? WHERE id = ?",
            [$now, $now, $widgetId]
        );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch all active widgets for a dashboard.
     *
     * @param  int $dashboardId
     * @return array
     */
    private function getWidgets(int $dashboardId): array
    {
        $sql = <<<SQL
            SELECT *
            FROM dashboard_widgets
            WHERE dashboard_id = ?
              AND tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
            ORDER BY grid_y ASC, grid_x ASC
        SQL;

        $rs = $this->db->Execute($sql, [$dashboardId, $this->tenantId, $this->companyCode]);

        if ($rs === false) {
            throw new \RuntimeException('getWidgets query failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $this->decodeJsonFields($rs->fields, ['config']);
            $rs->MoveNext();
        }

        return $rows;
    }

    /**
     * Find a single widget by ID (tenant-scoped).
     *
     * @param  int $widgetId
     * @return array
     * @throws \RuntimeException if not found
     */
    private function findWidget(int $widgetId): array
    {
        $sql = <<<SQL
            SELECT *
            FROM dashboard_widgets
            WHERE id           = ?
              AND tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
        SQL;

        $rs = $this->db->Execute($sql, [$widgetId, $this->tenantId, $this->companyCode]);

        if ($rs === false) {
            throw new \RuntimeException('findWidget query failed: ' . $this->db->ErrorMsg());
        }

        if ($rs->EOF) {
            throw new \RuntimeException("Widget {$widgetId} not found.");
        }

        return $this->decodeJsonFields($rs->fields, ['config']);
    }

    /**
     * Dispatch to the correct analytics method based on widget_type.
     *
     * @param  array $widget
     * @param  array $config
     * @return array
     */
    private function fetchWidgetData(array $widget, array $config): array
    {
        $type = $widget['widget_type'];

        switch ($type) {
            case 'pipeline_summary':
                return $this->analyticsService->pipelineSummary(
                    isset($config['pipeline_id']) ? (int) $config['pipeline_id'] : null
                );

            case 'deal_velocity':
                return $this->analyticsService->dealVelocity(
                    $config['date_from']   ?? null,
                    $config['date_to']     ?? null,
                    isset($config['pipeline_id']) ? (int) $config['pipeline_id'] : null
                );

            case 'lead_conversion':
                return $this->analyticsService->leadConversionRate(
                    $config['date_from'] ?? null,
                    $config['date_to']   ?? null
                );

            case 'activity_summary':
                return $this->analyticsService->activitySummary(
                    $config['date_from'] ?? null,
                    $config['date_to']   ?? null,
                    isset($config['owner_id']) ? (int) $config['owner_id'] : null
                );

            case 'revenue_forecast':
                return $this->analyticsService->revenueForecast(
                    isset($config['months']) ? (int) $config['months'] : 6,
                    isset($config['pipeline_id']) ? (int) $config['pipeline_id'] : null
                );

            case 'report':
            case 'custom':
                $reportId = (int) ($widget['report_id'] ?? 0);
                if ($reportId <= 0) {
                    return ['columns' => [], 'rows' => [], 'total' => 0];
                }
                return $this->reportBuilderService->executeReport($reportId, $config);

            default:
                return [];
        }
    }

    /**
     * Assert that the dashboard belongs to the current tenant.
     *
     * @param  int $dashboardId
     * @throws \RuntimeException if not found or not owned by tenant
     */
    private function assertDashboardOwnership(int $dashboardId): void
    {
        $sql = <<<SQL
            SELECT id FROM dashboards
            WHERE id           = ?
              AND tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
        SQL;

        $rs = $this->db->Execute($sql, [$dashboardId, $this->tenantId, $this->companyCode]);

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Dashboard {$dashboardId} not found.");
        }
    }

    /**
     * Clear the is_default flag for the current owner's dashboards.
     */
    private function clearDefaultDashboard(): void
    {
        $sql = <<<SQL
            UPDATE dashboards
            SET is_default = FALSE, updated_at = ?
            WHERE tenant_id    = ?
              AND company_code = ?
              AND owner_id     = ?
              AND is_default   = TRUE
              AND deleted_at   IS NULL
        SQL;

        $this->db->Execute($sql, [$this->now(), $this->tenantId, $this->companyCode, $this->userId]);
    }

    /**
     * Decode JSONB fields from a DB row.
     *
     * @param  array    $row
     * @param  string[] $fields
     * @return array
     */
    private function decodeJsonFields(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = json_decode($row[$field], true) ?? [];
            }
        }
        return $row;
    }

    /**
     * Return current UTC timestamp string.
     */
    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
