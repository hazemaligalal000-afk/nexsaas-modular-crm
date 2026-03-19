<?php
/**
 * CRM/Analytics/ReportBuilderService.php
 *
 * Custom report builder: save/load report definitions, execute dynamic SQL,
 * and export results as CSV or PDF.
 *
 * Requirements: 17.2, 17.5
 */

declare(strict_types=1);

namespace CRM\Analytics;

use Core\BaseService;

class ReportBuilderService extends BaseService
{
    private string $tenantId;
    private string $companyCode;
    private string $userId;

    // -------------------------------------------------------------------------
    // Data source registry
    // -------------------------------------------------------------------------

    /**
     * Allowed dimensions and metrics per data source.
     * Keys are the SQL column expressions; values are display labels.
     */
    private const DATA_SOURCES = [
        'contacts' => [
            'table'      => 'contacts',
            'dimensions' => [
                'full_name'  => 'Full Name',
                'company'    => 'Company',
                'job_title'  => 'Job Title',
                'tags'       => 'Tags',
                'owner_id'   => 'Owner',
                'created_at' => 'Created At',
            ],
            'metrics' => [
                'count' => ['label' => 'Count', 'expr' => 'COUNT(*)'],
            ],
        ],
        'leads' => [
            'table'      => 'leads',
            'dimensions' => [
                'source'     => 'Source',
                'status'     => 'Status',
                'owner_id'   => 'Owner',
                'created_at' => 'Created At',
            ],
            'metrics' => [
                'count'     => ['label' => 'Count',     'expr' => 'COUNT(*)'],
                'avg_score' => ['label' => 'Avg Score', 'expr' => 'ROUND(AVG(score)::NUMERIC, 2)'],
            ],
        ],
        'deals' => [
            'table'      => 'deals',
            'dimensions' => [
                'pipeline_id'    => 'Pipeline',
                'stage_id'       => 'Stage',
                'owner_id'       => 'Owner',
                'close_date'     => 'Close Date',
                'currency_code'  => 'Currency',
            ],
            'metrics' => [
                'count'           => ['label' => 'Count',            'expr' => 'COUNT(*)'],
                'sum_value'       => ['label' => 'Total Value',      'expr' => 'COALESCE(SUM(value), 0)'],
                'avg_value'       => ['label' => 'Avg Value',        'expr' => 'ROUND(AVG(value)::NUMERIC, 2)'],
                'avg_win_probability' => ['label' => 'Avg Win %', 'expr' => 'ROUND(AVG(win_probability)::NUMERIC, 2)'],
            ],
        ],
        'activities' => [
            'table'      => 'activities',
            'dimensions' => [
                'type'               => 'Type',
                'assigned_user_id'   => 'Owner',
                'linked_record_type' => 'Linked Record Type',
                'created_at'         => 'Created At',
            ],
            'metrics' => [
                'count' => ['label' => 'Count', 'expr' => 'COUNT(*)'],
            ],
        ],
        'conversations' => [
            'table'      => 'conversations',
            'dimensions' => [
                'channel'        => 'Channel',
                'status'         => 'Status',
                'assigned_agent' => 'Assigned Agent',
                'created_at'     => 'Created At',
            ],
            'metrics' => [
                'count'           => ['label' => 'Count',           'expr' => 'COUNT(*)'],
                'avg_handle_time' => ['label' => 'Avg Handle Time', 'expr' => 'ROUND(AVG(handle_time_seconds)::NUMERIC, 2)'],
            ],
        ],
    ];

    // -------------------------------------------------------------------------
    // Filter operator whitelist
    // -------------------------------------------------------------------------

    private const FILTER_OPERATORS = [
        'equals', 'not_equals', 'contains', 'greater_than',
        'less_than', 'between', 'in',
    ];

    public function __construct($db, string $tenantId, string $companyCode, string $userId = '')
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
        $this->userId      = $userId;
    }

    // -------------------------------------------------------------------------
    // Data source discovery — Requirement 17.2
    // -------------------------------------------------------------------------

    /**
     * Return available data sources with their dimensions and metrics.
     *
     * @return array
     */
    public function getDataSources(): array
    {
        $result = [];
        foreach (self::DATA_SOURCES as $key => $config) {
            $result[] = [
                'key'        => $key,
                'label'      => ucfirst($key),
                'dimensions' => array_map(
                    fn($col, $label) => ['key' => $col, 'label' => $label],
                    array_keys($config['dimensions']),
                    array_values($config['dimensions'])
                ),
                'metrics' => array_map(
                    fn($col, $meta) => ['key' => $col, 'label' => $meta['label']],
                    array_keys($config['metrics']),
                    array_values($config['metrics'])
                ),
            ];
        }
        return $result;
    }

    // -------------------------------------------------------------------------
    // CRUD — Requirement 17.2
    // -------------------------------------------------------------------------

    /**
     * Save (create or update) a custom report definition.
     *
     * @param  array $data  Must include: name, data_source, dimensions, metrics.
     *                      Optional: id (for update), description, filters, sort_config.
     * @return array        Saved report row.
     */
    public function saveReport(array $data): array
    {
        $this->validateDataSource($data['data_source'] ?? '');

        $payload = [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'data_source' => $data['data_source'],
            'dimensions'  => json_encode($data['dimensions'] ?? []),
            'metrics'     => json_encode($data['metrics'] ?? []),
            'filters'     => json_encode($data['filters'] ?? []),
            'sort_config' => json_encode($data['sort_config'] ?? (object)[]),
            'owner_id'    => (int) ($data['owner_id'] ?? $this->userId),
            'created_by'  => (int) ($data['created_by'] ?? $this->userId),
        ];

        if (!empty($data['id'])) {
            $id = (int) $data['id'];
            $this->updateReport($id, $payload);
            return $this->getReport($id);
        }

        $id = $this->insertReport($payload);
        return $this->getReport($id);
    }

    /**
     * Get a single report by ID (tenant-scoped).
     *
     * @param  int $reportId
     * @return array
     * @throws \RuntimeException if not found
     */
    public function getReport(int $reportId): array
    {
        $sql = "SELECT * FROM custom_reports WHERE id = ?";
        $rs  = $this->db->Execute($sql, [$reportId]);

        if ($rs === false) {
            throw new \RuntimeException('getReport query failed: ' . $this->db->ErrorMsg());
        }

        while (!$rs->EOF) {
            $row = $rs->fields;
            if ($row['tenant_id'] === $this->tenantId && $row['company_code'] === $this->companyCode && $row['deleted_at'] === null) {
                return $this->decodeJsonFields($row);
            }
            $rs->MoveNext();
        }

        throw new \RuntimeException("Report {$reportId} not found.");
    }

    /**
     * List all active reports for the current tenant.
     *
     * @return array
     */
    public function listReports(): array
    {
        $sql = <<<SQL
            SELECT id, name, description, data_source, dimensions, metrics,
                   filters, sort_config, owner_id, created_by, created_at, updated_at
            FROM custom_reports
            WHERE tenant_id    = ?
              AND company_code = ?
              AND deleted_at   IS NULL
            ORDER BY created_at DESC
        SQL;

        $rows = [];
        $rs   = $this->db->Execute($sql, [$this->tenantId, $this->companyCode]);

        if ($rs === false) {
            throw new \RuntimeException('listReports query failed: ' . $this->db->ErrorMsg());
        }

        while (!$rs->EOF) {
            $rows[] = $this->decodeJsonFields($rs->fields);
            $rs->MoveNext();
        }

        return $rows;
    }

    /**
     * Soft-delete a report.
     *
     * @param  int $reportId
     * @return bool
     */
    public function deleteReport(int $reportId): bool
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $sql = "UPDATE custom_reports SET deleted_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL";
        $rs  = $this->db->Execute($sql, [$now, $reportId, $this->tenantId, $this->companyCode]);

        if ($rs === false) {
            throw new \RuntimeException('deleteReport failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Report execution — Requirement 17.2
    // -------------------------------------------------------------------------

    /**
     * Execute a saved report and return result rows.
     *
     * Builds dynamic SQL from the report's dimensions (GROUP BY),
     * metrics (SELECT aggregates), and filters (WHERE clauses).
     * Always scoped to tenant_id + company_code + deleted_at IS NULL.
     * LIMIT 10000 for safety.
     *
     * @param  int   $reportId
     * @param  array $params   Optional runtime overrides (e.g. date range)
     * @return array           ['columns' => [...], 'rows' => [...], 'total' => int]
     */
    public function executeReport(int $reportId, array $params = []): array
    {
        $report = $this->getReport($reportId);
        return $this->executeReportDefinition($report, $params);
    }

    /**
     * Execute a report definition directly (used internally for export).
     *
     * @param  array $report
     * @param  array $params
     * @return array
     */
    public function executeReportDefinition(array $report, array $params = []): array
    {
        $source     = $report['data_source'];
        $dimensions = $report['dimensions'];
        $metrics    = $report['metrics'];
        $filters    = $report['filters'];
        $sortConfig = $report['sort_config'];

        $this->validateDataSource($source);
        $config = self::DATA_SOURCES[$source];
        $table  = $config['table'];

        // --- SELECT columns ---
        $selectParts = [];
        $groupByParts = [];

        foreach ($dimensions as $dim) {
            $col = $this->whitelistColumn($dim, array_keys($config['dimensions']));
            $selectParts[]  = $col;
            $groupByParts[] = $col;
        }

        foreach ($metrics as $metric) {
            $key = $this->whitelistColumn($metric, array_keys($config['metrics']));
            $expr = $config['metrics'][$key]['expr'];
            $selectParts[] = "{$expr} AS {$key}";
        }

        if (empty($selectParts)) {
            $selectParts[] = 'COUNT(*) AS count';
        }

        // --- WHERE clause ---
        $whereParts = [
            'tenant_id = ?',
            'company_code = ?',
            'deleted_at IS NULL',
        ];
        $bindParams = [$this->tenantId, $this->companyCode];

        foreach ($filters as $filter) {
            [$clause, $values] = $this->buildFilterClause($filter, $config['dimensions']);
            if ($clause !== null) {
                $whereParts[] = $clause;
                $bindParams   = array_merge($bindParams, $values);
            }
        }

        // --- ORDER BY ---
        $orderBy = '';
        if (!empty($sortConfig['column'])) {
            $sortCol = $this->whitelistColumn(
                $sortConfig['column'],
                array_merge(array_keys($config['dimensions']), array_keys($config['metrics']))
            );
            $dir     = strtoupper($sortConfig['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $orderBy = "ORDER BY {$sortCol} {$dir}";
        }

        // --- GROUP BY ---
        $groupBy = !empty($groupByParts) ? 'GROUP BY ' . implode(', ', $groupByParts) : '';

        $sql = sprintf(
            "SELECT %s FROM %s WHERE %s %s %s LIMIT 10000",
            implode(', ', $selectParts),
            $table,
            implode(' AND ', $whereParts),
            $groupBy,
            $orderBy
        );

        $rs = $this->db->Execute($sql, $bindParams);

        if ($rs === false) {
            throw new \RuntimeException('executeReport query failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }

        // Derive column list from first row or select parts
        $columns = !empty($rows) ? array_keys($rows[0]) : $dimensions;

        return [
            'columns' => $columns,
            'rows'    => $rows,
            'total'   => count($rows),
        ];
    }

    // -------------------------------------------------------------------------
    // Export — Requirement 17.5
    // -------------------------------------------------------------------------

    /**
     * Export report results as a CSV string.
     *
     * @param  int   $reportId
     * @param  array $params
     * @return string  CSV content
     */
    public function exportCsv(int $reportId, array $params = []): string
    {
        $result = $this->executeReport($reportId, $params);
        return $this->buildCsv($result);
    }

    /**
     * Build CSV from a result set (also used by the scheduler).
     *
     * @param  array $result  ['columns' => [...], 'rows' => [...]]
     * @return string
     */
    public function buildCsv(array $result): string
    {
        ob_start();
        $handle = fopen('php://output', 'w');

        // Header row
        fputcsv($handle, $result['columns']);

        // Data rows
        foreach ($result['rows'] as $row) {
            fputcsv($handle, array_values($row));
        }

        fclose($handle);
        return ob_get_clean();
    }

    /**
     * Export report results as a PDF file using mPDF.
     * Returns the absolute path to the generated temp file.
     *
     * @param  int   $reportId
     * @param  array $params
     * @return string  File path
     */
    public function exportPdf(int $reportId, array $params = []): string
    {
        $report = $this->getReport($reportId);
        $result = $this->executeReportDefinition($report, $params);

        $html = $this->buildPdfHtml($report['name'], $result);

        $mpdf = new \Mpdf\Mpdf([
            'mode'        => 'utf-8',
            'format'      => 'A4-L',
            'orientation' => 'L',
            'margin_top'  => 15,
            'margin_left' => 10,
            'margin_right'=> 10,
        ]);

        $mpdf->WriteHTML($html);

        $tmpPath = sys_get_temp_dir() . '/report_' . $reportId . '_' . time() . '.pdf';
        $mpdf->Output($tmpPath, \Mpdf\Output\Destination::FILE);

        return $tmpPath;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function insertReport(array $payload): int
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $payload['tenant_id']    = $this->tenantId;
        $payload['company_code'] = $this->companyCode;
        $payload['created_at']   = $now;
        $payload['updated_at']   = $now;

        $cols   = implode(', ', array_keys($payload));
        $places = implode(', ', array_fill(0, count($payload), '?'));
        $sql    = "INSERT INTO custom_reports ({$cols}) VALUES ({$places})";

        $rs = $this->db->Execute($sql, array_values($payload));
        if ($rs === false) {
            throw new \RuntimeException('insertReport failed: ' . $this->db->ErrorMsg());
        }

        return (int) $this->db->Insert_ID();
    }

    private function updateReport(int $id, array $payload): void
    {
        unset($payload['created_by'], $payload['owner_id']);
        $payload['updated_at'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $setClauses = [];
        $values     = [];
        foreach ($payload as $col => $val) {
            $setClauses[] = "{$col} = ?";
            $values[]     = $val;
        }
        $values[] = $id;
        $values[] = $this->tenantId;
        $values[] = $this->companyCode;

        $sql = "UPDATE custom_reports SET " . implode(', ', $setClauses)
             . " WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL";

        $rs = $this->db->Execute($sql, $values);
        if ($rs === false) {
            throw new \RuntimeException('updateReport failed: ' . $this->db->ErrorMsg());
        }
    }

    /**
     * Build a WHERE clause fragment for a single filter.
     *
     * @param  array $filter   ['column' => ..., 'operator' => ..., 'value' => ...]
     * @param  array $allowedCols  Whitelist of column names
     * @return array{0: string|null, 1: array}
     */
    private function buildFilterClause(array $filter, array $allowedCols): array
    {
        $col      = $filter['column']   ?? '';
        $operator = $filter['operator'] ?? '';
        $value    = $filter['value']    ?? null;

        if (empty($col) || empty($operator)) {
            return [null, []];
        }

        // Whitelist column
        try {
            $col = $this->whitelistColumn($col, array_keys($allowedCols));
        } catch (\InvalidArgumentException) {
            return [null, []];
        }

        // Whitelist operator
        if (!in_array($operator, self::FILTER_OPERATORS, true)) {
            return [null, []];
        }

        switch ($operator) {
            case 'equals':
                return ["{$col} = ?", [$value]];
            case 'not_equals':
                return ["{$col} != ?", [$value]];
            case 'contains':
                return ["{$col} ILIKE ?", ['%' . $value . '%']];
            case 'greater_than':
                return ["{$col} > ?", [$value]];
            case 'less_than':
                return ["{$col} < ?", [$value]];
            case 'between':
                $vals = (array) $value;
                if (count($vals) < 2) {
                    return [null, []];
                }
                return ["{$col} BETWEEN ? AND ?", [$vals[0], $vals[1]]];
            case 'in':
                $vals = (array) $value;
                if (empty($vals)) {
                    return [null, []];
                }
                $places = implode(', ', array_fill(0, count($vals), '?'));
                return ["{$col} IN ({$places})", $vals];
        }

        return [null, []];
    }

    /**
     * Validate that a column name is in the whitelist (SQL injection guard).
     *
     * @param  string $col
     * @param  array  $whitelist
     * @return string  The validated column name
     * @throws \InvalidArgumentException
     */
    private function whitelistColumn(string $col, array $whitelist): string
    {
        if (!in_array($col, $whitelist, true)) {
            throw new \InvalidArgumentException("Column '{$col}' is not allowed.");
        }
        return $col;
    }

    /**
     * Validate that a data source key is registered.
     *
     * @param  string $source
     * @throws \InvalidArgumentException
     */
    private function validateDataSource(string $source): void
    {
        if (!array_key_exists($source, self::DATA_SOURCES)) {
            throw new \InvalidArgumentException("Unknown data source: '{$source}'.");
        }
    }

    /**
     * Decode JSONB fields from DB rows.
     *
     * @param  array $row
     * @return array
     */
    private function decodeJsonFields(array $row): array
    {
        foreach (['dimensions', 'metrics', 'filters', 'sort_config'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = json_decode($row[$field], true) ?? [];
            }
        }
        return $row;
    }

    /**
     * Build an HTML table for PDF rendering.
     *
     * @param  string $title
     * @param  array  $result
     * @return string
     */
    private function buildPdfHtml(string $title, array $result): string
    {
        $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html  = '<html><body>';
        $html .= '<h2 style="font-family:sans-serif">' . $esc($title) . '</h2>';
        $html .= '<table border="1" cellpadding="4" cellspacing="0" style="font-family:sans-serif;font-size:11px;border-collapse:collapse;width:100%">';

        // Header
        $html .= '<thead><tr style="background:#4a5568;color:#fff">';
        foreach ($result['columns'] as $col) {
            $html .= '<th>' . $esc((string) $col) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        // Rows
        foreach ($result['rows'] as $i => $row) {
            $bg    = ($i % 2 === 0) ? '#f7fafc' : '#fff';
            $html .= "<tr style=\"background:{$bg}\">";
            foreach (array_values($row) as $cell) {
                $html .= '<td>' . $esc((string) ($cell ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';
        return $html;
    }
}
