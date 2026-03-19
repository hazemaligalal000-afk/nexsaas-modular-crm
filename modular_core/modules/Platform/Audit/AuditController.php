<?php

namespace ModularCore\Modules\Platform\Audit;

use ModularCore\Core\BaseController;

/**
 * Audit Controller
 * 
 * REST API for audit log access
 * Requirements: 30.4, 30.5
 */
class AuditController extends BaseController
{
    protected $auditService;
    
    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService();
        
        // Require audit.view permission
        $this->requirePermission('audit.view');
    }
    
    /**
     * GET /api/v1/platform/audit
     * Search audit log with filters
     */
    public function index()
    {
        $filters = [
            'user_id' => $_GET['user_id'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'table_name' => $_GET['table_name'] ?? null,
            'operation' => $_GET['operation'] ?? null,
            'company_code' => $_GET['company_code'] ?? null,
            'record_id' => $_GET['record_id'] ?? null,
        ];
        
        // Remove null filters
        $filters = array_filter($filters, fn($v) => $v !== null);
        
        $limit = (int)($_GET['limit'] ?? 100);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $result = $this->auditService->search($filters, $limit, $offset);
        
        return $this->respond($result);
    }
    
    /**
     * GET /api/v1/platform/audit/{table}/{recordId}
     * Get audit history for specific record
     */
    public function recordHistory($table, $recordId)
    {
        $history = $this->auditService->getRecordHistory($table, (int)$recordId);
        
        return $this->respond([
            'table' => $table,
            'record_id' => $recordId,
            'history' => $history
        ]);
    }
    
    /**
     * GET /api/v1/platform/audit/export
     * Export audit log as PDF
     */
    public function export()
    {
        $this->requirePermission('audit.export');
        
        $filters = [
            'user_id' => $_GET['user_id'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'table_name' => $_GET['table_name'] ?? null,
            'operation' => $_GET['operation'] ?? null,
            'company_code' => $_GET['company_code'] ?? null,
        ];
        
        $filters = array_filter($filters, fn($v) => $v !== null);
        
        $filepath = $this->auditService->exportToPDF($filters);
        
        // Return file download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        unlink($filepath);
        exit;
    }
}
