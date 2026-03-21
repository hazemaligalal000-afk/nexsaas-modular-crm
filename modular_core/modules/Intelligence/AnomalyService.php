<?php
/**
 * Intelligence/AnomalyService.php
 * 
 * CORE → ADVANCED: Financial Anomaly Detection (P2)
 */

declare(strict_types=1);

namespace Modules\Intelligence;

use Core\BaseService;

class AnomalyService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Scan for unusual vouchers (E.g. unusually high amounts or late-night postings)
     * Rule: Detection by standard deviation (Simplified for baseline) or historical context
     */
    public function scanFinancialAnomalies(string $tenantId, string $companyCode): array
    {
        // 1. Fetch unusually high vouchers ( > 3x average )
        $sql = "SELECT AVG(total_amount_base) FROM journal_entry_headers 
                WHERE tenant_id = ? AND company_code = ? AND deleted_at IS NULL";
        $avg = $this->db->GetOne($sql, [$tenantId, $companyCode]);

        $threshold = ($avg ?? 0) * 3;

        $sql = "SELECT id, voucher_no, total_amount_base, created_at, created_by
                FROM journal_entry_headers 
                WHERE tenant_id = ? AND company_code = ? 
                  AND total_amount_base > ? AND deleted_at IS NULL";
        
        $highVouchers = $this->db->GetAll($sql, [$tenantId, $companyCode, $threshold]);

        // 2. Fetch late-night postings (between 11 PM and 5 AM)
        $sql = "SELECT id, voucher_no, created_at, created_by 
                FROM journal_entry_headers 
                WHERE tenant_id = ? AND company_code = ? 
                  AND EXTRACT(HOUR FROM created_at) NOT BETWEEN 7 AND 19 
                  AND deleted_at IS NULL";
        
        $lateVouchers = $this->db->GetAll($sql, [$tenantId, $companyCode]);

        $anomalies = [];

        foreach ($highVouchers as $hv) {
             $anomalies[] = [
                'type' => 'ABNORMAL_AMOUNT',
                'severity' => 'High',
                'description' => "Voucher #{$hv['voucher_no']} exceeds 3x average amount.",
                'meta' => $hv
             ];
        }

        foreach ($lateVouchers as $lv) {
             $anomalies[] = [
                'type' => 'UNUSUAL_HOUR',
                'severity' => 'Medium',
                'description' => "Voucher #{$lv['voucher_no']} was created outside of standard working hours.",
                'meta' => $lv
             ];
        }

        return [
            'scan_time' => date('Y-m-d H:i:s'),
            'total_anomalies' => count($anomalies),
            'threat_level' => count($anomalies) > 10 ? 'Critical' : (count($anomalies) > 3 ? 'Caution' : 'Low'),
            'anomalies' => $anomalies
        ];
    }
}
