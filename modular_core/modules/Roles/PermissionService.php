<?php
/**
 * Roles/PermissionService.php
 * 
 * CORE → ADVANCED: Granular RBAC Permission Matrix (Batch RBAC-A)
 */

declare(strict_types=1);

namespace Modules\Roles;

use Core\BaseService;

class PermissionService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Check if a user has a specific permission for a resource
     * Used by: Middlewares and Controllers
     */
    public function can(int $userId, string $resource, string $action): bool
    {
        // 1. Fetch user roles and their associated permissions
        $sql = "SELECT p.id FROM user_roles ur
                JOIN role_permissions rp ON ur.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = ? AND p.resource = ? AND p.action = ?
                AND ur.tenant_id = ?";
        
        $valid = $this->db->GetOne($sql, [$userId, $resource, $action, $this->tenantId]);

        return (bool)$valid;
    }

    /**
     * Assign a new role to a user (Admin Only)
     */
    public function assignRole(int $userId, int $roleId): void
    {
        $this->db->Execute(
            "INSERT INTO user_roles (user_id, role_id, tenant_id, assigned_at)
             VALUES (?, ?, ?, NOW()) 
             ON CONFLICT (user_id, role_id, tenant_id) DO NOTHING",
            [$userId, $roleId, $this->tenantId]
        );

        // FIRE EVENT: Role Assigned (Triggers session refresh)
        // $this->fireEvent('auth.role_assigned', ['user_id' => $userId, 'role_id' => $roleId]);
    }
}
