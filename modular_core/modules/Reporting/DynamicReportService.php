<?php
/**
 * Reporting/DynamicReportService.php
 * 
 * CORE → ADVANCED: Custom BI Report Builder
 */

declare(strict_types=1);

namespace Modules\Reporting;

use Core\BaseService;

class DynamicReportService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Fetch a custom report for a tenant based on dynamic SQL template
     * Used by: Dashboard BI builder
     */
    public function buildReport(int $reportId, array $filters): array
    {
        // 1. Fetch Report definition & SQL template
        $sql = "SELECT id, name, sql_template, config FROM reports 
                WHERE id = ? AND tenant_id = ? AND is_active = TRUE";
        
        $report = $this->db->GetRow($sql, [$reportId, $this->tenantId]);

        if (!$report) throw new \RuntimeException("Report not found or not in draft: " . $reportId);

        // 2. Automated Filter Parsing (Advanced BI)
        $sqlQuery = str_replace(
            ['{{tenant_id}}', '{{company_code}}'],
            [$this->tenantId, $filters['company_code'] ?? '01'],
            $report['sql_template']
        );

        // 3. Execute query and returns results
        $results = $this->db->GetAll($sqlQuery);

        return [
            'report_name' => $report['name'],
            'results_count' => count($results),
            'data' => $results,
            'summary' => [
                'total_primary' => array_sum(array_column($results, 'primary_value')),
                'avg_primary' => count($results) > 0 ? array_sum(array_column($results, 'primary_value')) / count($results) : 0
            ]
        ];
    }
}
