<?php

namespace ModularCore\Modules\Platform\Audit;

use ModularCore\Core\BaseService;

/**
 * Audit Service
 * 
 * Immutable audit logging for all system operations
 * Requirements: 30.1, 30.2, 30.3, 30.4, 30.5
 */
class AuditService extends BaseService
{
    /**
     * Log an audit event
     * Append-only, cannot be modified or deleted
     */
    public function log(
        string $operation,
        string $tableName,
        ?int $recordId = null,
        ?array $prevValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): int {
        global $db;
        
        $userId = $this->getCurrentUserId();
        $tenantId = $this->getCurrentTenantId();
        $companyCode = $this->getCurrentCompanyCode();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $sql = "INSERT INTO audit_log (
            tenant_id, user_id, operation, table_name, record_id,
            prev_values, new_values, ip_address, user_agent, company_code, metadata, timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) RETURNING id";
        
        $result = $db->Execute($sql, [
            $tenantId,
            $userId,
            $operation,
            $tableName,
            $recordId,
            $prevValues ? json_encode($prevValues) : null,
            $newValues ? json_encode($newValues) : null,
            $ipAddress,
            $userAgent,
            $companyCode,
            $metadata ? json_encode($metadata) : null
        ]);
        
        if ($result && !$result->EOF) {
            return $result->fields['id'];
        }
        
        return 0;
    }
    
    /**
     * Search audit log with filters
     * Return within 5s for 10M entries
     */
    public function search(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        global $db;
        
        $tenantId = $this->getCurrentTenantId();
        
        $where = ["tenant_id = ?"];
        $params = [$tenantId];
        
        // Filter by user
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $where[] = "timestamp >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "timestamp <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Filter by table/record type
        if (!empty($filters['table_name'])) {
            $where[] = "table_name = ?";
            $params[] = $filters['table_name'];
        }
        
        // Filter by operation type
        if (!empty($filters['operation'])) {
            $where[] = "operation = ?";
            $params[] = $filters['operation'];
        }
        
        // Filter by company code
        if (!empty($filters['company_code'])) {
            $where[] = "company_code = ?";
            $params[] = $filters['company_code'];
        }
        
        // Filter by record ID
        if (!empty($filters['record_id'])) {
            $where[] = "record_id = ?";
            $params[] = $filters['record_id'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM audit_log WHERE {$whereClause}";
        $countResult = $db->Execute($countSql, $params);
        $total = $countResult->fields['total'];
        
        // Get paginated results
        $sql = "SELECT * FROM audit_log 
                WHERE {$whereClause}
                ORDER BY timestamp DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $result = $db->Execute($sql, $params);
        
        $logs = [];
        while ($result && !$result->EOF) {
            $log = $result->fields;
            
            // Decode JSON fields
            if ($log['prev_values']) {
                $log['prev_values'] = json_decode($log['prev_values'], true);
            }
            if ($log['new_values']) {
                $log['new_values'] = json_decode($log['new_values'], true);
            }
            if ($log['metadata']) {
                $log['metadata'] = json_decode($log['metadata'], true);
            }
            
            $logs[] = $log;
            $result->MoveNext();
        }
        
        return [
            'logs' => $logs,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Get audit trail for specific record
     */
    public function getRecordHistory(string $tableName, int $recordId): array
    {
        global $db;
        
        $tenantId = $this->getCurrentTenantId();
        
        $sql = "SELECT * FROM audit_log 
                WHERE tenant_id = ? AND table_name = ? AND record_id = ?
                ORDER BY timestamp DESC";
        
        $result = $db->Execute($sql, [$tenantId, $tableName, $recordId]);
        
        $history = [];
        while ($result && !$result->EOF) {
            $log = $result->fields;
            
            if ($log['prev_values']) {
                $log['prev_values'] = json_decode($log['prev_values'], true);
            }
            if ($log['new_values']) {
                $log['new_values'] = json_decode($log['new_values'], true);
            }
            if ($log['metadata']) {
                $log['metadata'] = json_decode($log['metadata'], true);
            }
            
            $history[] = $log;
            $result->MoveNext();
        }
        
        return $history;
    }
    
    /**
     * Export audit log as PDF
     */
    public function exportToPDF(array $filters = []): string
    {
        $data = $this->search($filters, 10000, 0);
        
        // Use mPDF for bilingual PDF generation
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'orientation' => 'L'
        ]);
        
        $html = $this->generateAuditReportHTML($data['logs'], $filters);
        $mpdf->WriteHTML($html);
        
        $filename = 'audit_log_' . date('Y-m-d_His') . '.pdf';
        $filepath = '/tmp/' . $filename;
        $mpdf->Output($filepath, 'F');
        
        return $filepath;
    }
    
    /**
     * Generate HTML for audit report
     */
    protected function generateAuditReportHTML(array $logs, array $filters): string
    {
        $html = '<h1>Audit Log Report</h1>';
        $html .= '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
        
        if (!empty($filters)) {
            $html .= '<h3>Filters Applied:</h3><ul>';
            foreach ($filters as $key => $value) {
                $html .= "<li><strong>{$key}:</strong> {$value}</li>";
            }
            $html .= '</ul>';
        }
        
        $html .= '<table border="1" cellpadding="5" style="width:100%; border-collapse:collapse;">';
        $html .= '<thead><tr>';
        $html .= '<th>Timestamp</th><th>User</th><th>Operation</th><th>Table</th>';
        $html .= '<th>Record ID</th><th>IP Address</th><th>Company</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($logs as $log) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($log['timestamp']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['user_id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['operation']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['table_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['record_id'] ?? '-') . '</td>';
            $html .= '<td>' . htmlspecialchars($log['ip_address'] ?? '-') . '</td>';
            $html .= '<td>' . htmlspecialchars($log['company_code'] ?? '-') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
}
