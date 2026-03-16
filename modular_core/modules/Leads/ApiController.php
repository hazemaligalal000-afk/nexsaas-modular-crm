<?php
/**
 * Modules/Leads/ApiController.php
 * Handles Lead-related operations with automatic tenant scoping.
 */

namespace Modules\Leads;

use Core\Database;
use Core\TenantEnforcer;
use Core\Auth\RbacGuard;

class ApiController {
    
    /**
     * GET /api/leads
     */
    public function index() {
        try {
            RbacGuard::enforce('leads', 'read');
            
            $stmt = Database::query("SELECT * FROM contacts WHERE lifecycle_stage = 'lead'");
            $leads = $stmt->fetchAll();

            return json_encode([
                'success' => true,
                'data' => $leads
            ]);
        } catch (\Exception $e) {
            http_response_code($e->getCode() ?: 500);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/leads
     */
    public function store($data) {
        try {
            RbacGuard::enforce('leads', 'create');
            
            $tenantId = TenantEnforcer::getTenantId();
            
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO contacts (tenant_id, first_name, last_name, email, phone, company) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tenantId,
                $data['first_name'] ?? '',
                $data['last_name'] ?? '',
                $data['email'] ?? '',
                $data['phone'] ?? '',
                $data['company'] ?? ''
            ]);

            return json_encode([
                'success' => true,
                'message' => 'Lead created successfully',
                'id' => $pdo->lastInsertId()
            ]);
        } catch (\Exception $e) {
            http_response_code($e->getCode() ?: 500);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
