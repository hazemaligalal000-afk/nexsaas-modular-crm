<?php
/**
 * CRM/Analytics/AnalyticsService.php
 *
 * Pre-built CRM reports:
 *   - Pipeline Summary
 *   - Deal Velocity
 *   - Lead Conversion Rate
 *   - Activity Summary
 *   - Revenue Forecast
 *
 * All queries are tenant + company scoped and optimised to return within 10s
 * for datasets up to 500K rows (Requirements 17.1, 17.3).
 *
 * Requirements: 17.1, 17.3
 */

declare(strict_types=1);

namespace CRM\Analytics;

use Core\BaseService;

class AnalyticsService extends BaseService
{
    private string $tenantId;
    private string $companyCode;

    public function __construct($db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    // -------------------------------------------------------------------------
    // 1. Pipeline Summary — Requirement 17.1
    // -------------------------------------------------------------------------

    /**
     * Return per-stage deal counts, total value, and weighted forecast for
     * every active pipeline belonging to the tenant.
     *
     * Optional filter: $pipelineId limits results to a single pipeline.
     *
     * @param  int|null $pipelineId
     * @return array
     */
    public function pipelineSummary(?int $pipelineId = null): array
    {
        $pipelineFilter = '';
        $params = [$this->tenantId, $this->companyCode];

        if ($pipelineId !== null) {
            $pipelineFilter = 'AND p.id = ?';
            $params[] = $pipelineId;
        }

        $sql = <<<SQL
            SELECT
                p.id                                                        AS pipeline_id,
                p.name                                                      AS pipeline_name,
                ps.id                                                       AS stage_id,
                ps.name                                                     AS stage_name,
                ps.position                                                 AS stage_position,
                COUNT(d.id)                                                 AS deal_count,
                COALESCE(SUM(d.value), 0)                                   AS total_value,
                COALESCE(SUM(d.value * COALESCE(d.win_probability, 0)), 0)  AS weighted_value,
                COUNT(d.id) FILTER (WHERE d.is_overdue)                     AS overdue_count,
                COUNT(d.id) FILTER (WHERE d.is_stale)                       AS stale_count
            FROM pipelines p
            JOIN pipeline_stages ps
                ON ps.pipeline_id  = p.id
               AND ps.tenant_id    = p.tenant_id
               AND ps.company_code = p.company_code
               AND ps.deleted_at   IS NULL
            LEFT JOIN deals d
                ON d.stage_id     = ps.id
               AND d.pipeline_id  = p.id
               AND d.tenant_id    = p.tenant_id
               AND d.company_code = p.company_code
               AND d.deleted_at   IS NULL
            WHERE p.tenant_id    = ?
              AND p.company_code = ?
              AND p.deleted_at   IS NULL
              {$pipelineFilter}
            GROUP BY p.id, p.name, ps.id, ps.name, ps.position
            ORDER BY p.id, ps.position
        SQL;

        return $this->fetchAll($sql, $params);
    }

    // -------------------------------------------------------------------------
    // 2. Deal Velocity — Requirement 17.1
    // -------------------------------------------------------------------------

    /**
     * Average time (in days) deals spend in each stage before moving forward,
     * and overall average cycle time from creation to close-won.
     *
     * @param  string|null $dateFrom  ISO date, e.g. '2025-01-01'
     * @param  string|null $dateTo    ISO date, e.g. '2025-12-31'
     * @param  int|null    $pipelineId
     * @return array
     */
    public function dealVelocity(
        ?string $dateFrom   = null,
        ?string $dateTo     = null,
        ?int    $pipelineId = null
    ): array {
        $params = [$this->tenantId, $this->companyCode];
        $filters = '';

        if ($pipelineId !== null) {
            $filters .= ' AND dsh.pipeline_id = ?';
            $params[] = $pipelineId;
        }
        if ($dateFrom !== null) {
            $filters .= ' AND dsh.changed_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $filters .= ' AND dsh.changed_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        // Average days per stage transition
        $stageSql = <<<SQL
            SELECT
                ps.id                                                   AS stage_id,
                ps.name                                                 AS stage_name,
                p.id                                                    AS pipeline_id,
                p.name                                                  AS pipeline_name,
                COUNT(dsh.id)                                           AS transition_count,
                ROUND(AVG(
                    EXTRACT(EPOCH FROM (dsh.changed_at - LAG(dsh.changed_at)
                        OVER (PARTITION BY dsh.deal_id ORDER BY dsh.changed_at)))
                    / 86400
                )::NUMERIC, 2)                                          AS avg_days_in_stage
            FROM deal_stage_history dsh
            JOIN pipeline_stages ps
                ON ps.id           = dsh.from_stage_id
               AND ps.tenant_id    = dsh.tenant_id
               AND ps.company_code = dsh.company_code
               AND ps.deleted_at   IS NULL
            JOIN pipelines p
                ON p.id            = ps.pipeline_id
               AND p.tenant_id     = ps.tenant_id
               AND p.company_code  = ps.company_code
               AND p.deleted_at    IS NULL
            WHERE dsh.tenant_id    = ?
              AND dsh.company_code = ?
              {$filters}
            GROUP BY ps.id, ps.name, p.id, p.name
            ORDER BY p.id, ps.position
        SQL;

        $stageVelocity = $this->fetchAll($stageSql, $params);

        // Overall cycle time: creation → closed-won
        $cycleSql = <<<SQL
            SELECT
                p.id                                                    AS pipeline_id,
                p.name                                                  AS pipeline_name,
                COUNT(d.id)                                             AS closed_won_count,
                ROUND(AVG(
                    EXTRACT(EPOCH FROM (d.updated_at - d.created_at)) / 86400
                )::NUMERIC, 2)                                          AS avg_cycle_days
            FROM deals d
            JOIN pipeline_stages ps
                ON ps.id           = d.stage_id
               AND ps.is_closed_won = true
            JOIN pipelines p
                ON p.id            = d.pipeline_id
               AND p.tenant_id     = d.tenant_id
               AND p.company_code  = d.company_code
               AND p.deleted_at    IS NULL
            WHERE d.tenant_id    = ?
              AND d.company_code = ?
              AND d.deleted_at   IS NULL
            GROUP BY p.id, p.name
            ORDER BY p.id
        SQL;

        $cycleParams = [$this->tenantId, $this->companyCode];
        if ($pipelineId !== null) {
            $cycleSql    .= ' AND d.pipeline_id = ?';
            $cycleParams[] = $pipelineId;
        }

        $cycleTime = $this->fetchAll($cycleSql, $cycleParams);

        return [
            'stage_velocity' => $stageVelocity,
            'cycle_time'     => $cycleTime,
        ];
    }

    // -------------------------------------------------------------------------
    // 3. Lead Conversion Rate — Requirement 17.1
    // -------------------------------------------------------------------------

    /**
     * Lead conversion funnel: total leads, converted leads, conversion rate,
     * and breakdown by source.
     *
     * @param  string|null $dateFrom
     * @param  string|null $dateTo
     * @return array
     */
    public function leadConversionRate(
        ?string $dateFrom = null,
        ?string $dateTo   = null
    ): array {
        $params  = [$this->tenantId, $this->companyCode];
        $filters = '';

        if ($dateFrom !== null) {
            $filters .= ' AND created_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $filters .= ' AND created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $sql = <<<SQL
            SELECT
                source,
                COUNT(id)                                               AS total_leads,
                COUNT(id) FILTER (WHERE converted_at IS NOT NULL)       AS converted_leads,
                ROUND(
                    COUNT(id) FILTER (WHERE converted_at IS NOT NULL)::NUMERIC
                    / NULLIF(COUNT(id), 0) * 100,
                    2
                )                                                       AS conversion_rate_pct,
                ROUND(AVG(
                    CASE WHEN converted_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (converted_at - created_at)) / 86400
                    END
                )::NUMERIC, 2)                                          AS avg_days_to_convert
            FROM leads
            WHERE tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
              {$filters}
            GROUP BY source
            ORDER BY total_leads DESC
        SQL;

        $bySource = $this->fetchAll($sql, $params);

        // Overall totals
        $totalSql = <<<SQL
            SELECT
                COUNT(id)                                               AS total_leads,
                COUNT(id) FILTER (WHERE converted_at IS NOT NULL)       AS converted_leads,
                ROUND(
                    COUNT(id) FILTER (WHERE converted_at IS NOT NULL)::NUMERIC
                    / NULLIF(COUNT(id), 0) * 100,
                    2
                )                                                       AS conversion_rate_pct,
                ROUND(AVG(
                    CASE WHEN converted_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (converted_at - created_at)) / 86400
                    END
                )::NUMERIC, 2)                                          AS avg_days_to_convert
            FROM leads
            WHERE tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
              {$filters}
        SQL;

        $totalRs = $this->db->Execute($totalSql, $params);
        $totals  = ($totalRs && !$totalRs->EOF) ? $totalRs->fields : [];

        return [
            'totals'    => $totals,
            'by_source' => $bySource,
        ];
    }

    // -------------------------------------------------------------------------
    // 4. Activity Summary — Requirement 17.1
    // -------------------------------------------------------------------------

    /**
     * Activity counts grouped by type and by user for the given date range.
     *
     * @param  string|null $dateFrom
     * @param  string|null $dateTo
     * @param  int|null    $ownerId   Filter to a specific user
     * @return array
     */
    public function activitySummary(
        ?string $dateFrom = null,
        ?string $dateTo   = null,
        ?int    $ownerId  = null
    ): array {
        $params  = [$this->tenantId, $this->companyCode];
        $filters = '';

        if ($dateFrom !== null) {
            $filters .= ' AND a.created_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $filters .= ' AND a.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($ownerId !== null) {
            $filters .= ' AND a.assigned_user_id = ?';
            $params[] = $ownerId;
        }

        // By type
        $byTypeSql = <<<SQL
            SELECT
                a.type                                                  AS activity_type,
                COUNT(a.id)                                             AS total,
                COUNT(a.id) FILTER (WHERE a.status = 'completed')       AS completed,
                COUNT(a.id) FILTER (WHERE a.status != 'completed'
                    AND a.due_date < NOW())                              AS overdue
            FROM activities a
            WHERE a.tenant_id    = ?
              AND a.company_code = ?
              AND a.deleted_at   IS NULL
              {$filters}
            GROUP BY a.type
            ORDER BY total DESC
        SQL;

        // By user
        $byUserSql = <<<SQL
            SELECT
                u.id                                                    AS user_id,
                u.full_name,
                COUNT(a.id)                                             AS total,
                COUNT(a.id) FILTER (WHERE a.status = 'completed')       AS completed,
                COUNT(a.id) FILTER (WHERE a.status != 'completed'
                    AND a.due_date < NOW())                              AS overdue
            FROM activities a
            JOIN users u
                ON u.id = a.assigned_user_id
            WHERE a.tenant_id    = ?
              AND a.company_code = ?
              AND a.deleted_at   IS NULL
              {$filters}
            GROUP BY u.id, u.full_name
            ORDER BY total DESC
        SQL;

        return [
            'by_type' => $this->fetchAll($byTypeSql, $params),
            'by_user' => $this->fetchAll($byUserSql, $params),
        ];
    }

    // -------------------------------------------------------------------------
    // 5. Revenue Forecast — Requirement 17.1
    // -------------------------------------------------------------------------

    /**
     * Monthly revenue forecast for the next N months based on:
     *   - Deals with close_date in the period × win_probability (weighted)
     *   - Closed-won deals in the period (actual)
     *
     * @param  int         $months     Number of months to forecast (default 6)
     * @param  int|null    $pipelineId
     * @return array
     */
    public function revenueForecast(int $months = 6, ?int $pipelineId = null): array
    {
        $params = [$this->tenantId, $this->companyCode, $months];
        $pipelineFilter = '';

        if ($pipelineId !== null) {
            $pipelineFilter = 'AND d.pipeline_id = ?';
            $params[] = $pipelineId;
        }

        // Weighted forecast: open deals grouped by close month
        $forecastSql = <<<SQL
            SELECT
                TO_CHAR(d.close_date, 'YYYY-MM')                        AS month,
                COUNT(d.id)                                             AS deal_count,
                COALESCE(SUM(d.value), 0)                               AS total_value,
                COALESCE(SUM(d.value * COALESCE(d.win_probability, 0)), 0) AS weighted_value
            FROM deals d
            JOIN pipeline_stages ps
                ON ps.id            = d.stage_id
               AND ps.is_closed_won = false
               AND ps.is_closed_lost = false
            WHERE d.tenant_id    = ?
              AND d.company_code = ?
              AND d.deleted_at   IS NULL
              AND d.close_date   BETWEEN CURRENT_DATE AND (CURRENT_DATE + (? || ' months')::INTERVAL)
              {$pipelineFilter}
            GROUP BY TO_CHAR(d.close_date, 'YYYY-MM')
            ORDER BY month
        SQL;

        // Actual closed-won revenue by month (historical + current month)
        $actualSql = <<<SQL
            SELECT
                TO_CHAR(d.updated_at, 'YYYY-MM')                        AS month,
                COUNT(d.id)                                             AS deal_count,
                COALESCE(SUM(d.value), 0)                               AS closed_value
            FROM deals d
            JOIN pipeline_stages ps
                ON ps.id            = d.stage_id
               AND ps.is_closed_won = true
            WHERE d.tenant_id    = ?
              AND d.company_code = ?
              AND d.deleted_at   IS NULL
              AND d.updated_at   >= DATE_TRUNC('month', CURRENT_DATE - INTERVAL '3 months')
              {$pipelineFilter}
            GROUP BY TO_CHAR(d.updated_at, 'YYYY-MM')
            ORDER BY month
        SQL;

        $actualParams = [$this->tenantId, $this->companyCode];
        if ($pipelineId !== null) {
            $actualParams[] = $pipelineId;
        }

        return [
            'forecast' => $this->fetchAll($forecastSql, $params),
            'actual'   => $this->fetchAll($actualSql, $actualParams),
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helper
    // -------------------------------------------------------------------------

    /**
     * Execute a query and return all rows as an array.
     *
     * @param  string $sql
     * @param  array  $params
     * @return array
     *
     * @throws \RuntimeException on DB error
     */
    private function fetchAll(string $sql, array $params): array
    {
        $rs = $this->db->Execute($sql, $params);

        if ($rs === false) {
            throw new \RuntimeException('AnalyticsService query failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        return $rows;
    }
}
