<?php
/**
 * Modules/Leads/ApiController.php
 * Handles Lead-related operations with automatic tenant scoping.
 */

namespace Modules\Leads;

use Core\Database;
use Core\TenantEnforcer;

class ApiController {
    
    /**
     * GET /api/leads
     * Returns leads scoped to the current tenant.
     */
    public function index() {
        try {
            // The Query is automatically scoped with 'WHERE organization_id = X' via Database::query() 
            // which calls TenantEnforcer::scopeQuery().
            $stmt = Database::query("SELECT * FROM contacts WHERE lifecycle_stage = 'lead'");
            $leads = $stmt->fetchAll();

            return json_encode([
                'success' => true,
                'tenant_id' => TenantEnforcer::getTenantId(),
                'data' => $leads
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/leads
     * Creates a new lead for the current tenant.
     */
    public function store($data) {
        try {
            $orgId = TenantEnforcer::getTenantId();
            
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO contacts (organization_id, first_name, last_name, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $orgId,
                $data['first_name'] ?? '',
                $data['last_name'] ?? '',
                $data['email'] ?? ''
            ]);

            return json_encode([
                'success' => true,
                'message' => 'Lead created successfully',
                'id' => $pdo->lastInsertId()
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
