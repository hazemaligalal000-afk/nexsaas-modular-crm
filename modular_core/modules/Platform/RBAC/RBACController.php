<?php
/**
 * Platform/RBAC/RBACController.php
 *
 * RBAC API Controller
 * Provides endpoints for permission checks, role management, and user permissions
 */

declare(strict_types=1);

namespace Modules\Platform\RBAC;

use Core\BaseController;
use Modules\Platform\Auth\AuthMiddleware;

class RBACController extends BaseController
{
    private ExtendedRBACService $rbacService;

    public function __construct($db, ExtendedRBACService $rbacService)
    {
        $this->rbacService = $rbacService;
    }

    /**
     * GET /api/rbac/permissions
     * Get current user's permissions and roles
     */
    public function getPermissions($request): Response
    {
        // Verify authentication
        $user = AuthMiddleware::verify($request);
        
        // Get user permissions
        $permissions = $this->rbacService->resolvePermissionsWithHierarchy($user->id);
        $crmRole = $this->rbacService->getUserCRMRole($user->id);
        $accountingRole = $this->rbacService->getUserAccountingRole($user->id);
        $allowedPages = $this->rbacService->getAllowedPages($user->id);

        $data = [
            'permissions' => $permissions,
            'crmRole' => $crmRole,
            'accountingRole' => $accountingRole,
            'allowedPages' => $allowedPages
        ];

        return $this->respond($data);
    }

    /**
     * POST /api/rbac/check
     * Check if user has a specific permission
     * 
     * Body: { "permission": "crm.deals.view_all" }
     */
    public function checkPermission($request): Response
    {
        $user = AuthMiddleware::verify($request);
        
        $body = json_decode($request->body, true);
        $permission = $body['permission'] ?? null;

        if ($permission === null) {
            return $this->respond(null, 'Permission parameter is required', 400);
        }

        $hasPermission = $this->rbacService->checkWithWildcard($user->id, $permission);

        return $this->respond(['hasPermission' => $hasPermission]);
    }

    /**
     * GET /api/rbac/roles
     * Get all available roles
     * 
     * Query params: ?type=crm|accounting|platform
     */
    public function getRoles($request): Response
    {
        $user = AuthMiddleware::verify($request);
        
        // Check if user can view roles
        if (!PermissionChecker::can($user, 'system.roles.manage')) {
            return $this->respond(null, 'Permission denied', 403);
        }

        $roleType = $request->query['type'] ?? null;
        $roles = $this->rbacService->getAllRoles($roleType);

        return $this->respond($roles);
    }

    /**
     * GET /api/rbac/roles/{roleName}
     * Get role metadata and permissions
     */
    public function getRoleDetails($request, $roleName): Response
    {
        $user = AuthMiddleware::verify($request);
        
        if (!PermissionChecker::can($user, 'system.roles.manage')) {
            return $this->respond(null, 'Permission denied', 403);
        }

        $roleMetadata = $this->rbacService->getRoleMetadata($roleName);
        
        if ($roleMetadata === null) {
            return $this->respond(null, 'Role not found', 404);
        }

        // Get permissions for this role
        $sql = "SELECT permission FROM role_permissions 
                WHERE tenant_id = ? AND role = ? AND deleted_at IS NULL
                ORDER BY permission";
        $rs = $this->rbacService->db->Execute($sql, [$this->tenantId, $roleName]);

        $permissions = [];
        if ($rs !== false) {
            while (!$rs->EOF) {
                $permissions[] = $rs->fields['permission'];
                $rs->MoveNext();
            }
        }

        $data = [
            'role' => $roleMetadata,
            'permissions' => $permissions
        ];

        return $this->respond($data);
    }

    /**
     * POST /api/rbac/roles/{roleName}/permissions
     * Assign permissions to a role
     * 
     * Body: { "permissions": ["crm.deals.view_all", "crm.deals.edit_any"] }
     */
    public function assignPermissions($request, $roleName): Response
    {
        $user = AuthMiddleware::verify($request);
        
        if (!PermissionChecker::can($user, 'system.roles.manage')) {
            return $this->respond(null, 'Permission denied', 403);
        }

        $body = json_decode($request->body, true);
        $permissions = $body['permissions'] ?? [];

        if (empty($permissions)) {
            return $this->respond(null, 'Permissions array is required', 400);
        }

        try {
            $this->rbacService->assignPermissions($roleName, $permissions, $user->id);
            return $this->respond(['message' => 'Permissions assigned successfully']);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/rbac/roles/{roleName}/permissions
     * Revoke permissions from a role
     * 
     * Body: { "permissions": ["crm.deals.delete"] }
     */
    public function revokePermissions($request, $roleName): Response
    {
        $user = AuthMiddleware::verify($request);
        
        if (!PermissionChecker::can($user, 'system.roles.manage')) {
            return $this->respond(null, 'Permission denied', 403);
        }

        $body = json_decode($request->body, true);
        $permissions = $body['permissions'] ?? [];

        if (empty($permissions)) {
            return $this->respond(null, 'Permissions array is required', 400);
        }

        try {
            $this->rbacService->revokePermissions($roleName, $permissions);
            return $this->respond(['message' => 'Permissions revoked successfully']);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/rbac/visibility/{resource}
     * Get visibility scope for a resource
     */
    public function getVisibilityScope($request, $resource): Response
    {
        $user = AuthMiddleware::verify($request);
        
        $scope = $this->rbacService->getVisibilityScope($user->id, $resource);

        return $this->respond(['scope' => $scope]);
    }

    /**
     * GET /api/rbac/permission-matrix
     * Get full permission matrix for all roles
     */
    public function getPermissionMatrix($request): Response
    {
        $user = AuthMiddleware::verify($request);
        
        if (!PermissionChecker::can($user, 'system.roles.manage')) {
            return $this->respond(null, 'Permission denied', 403);
        }

        // Get all roles
        $roles = $this->rbacService->getAllRoles();

        // Get all unique permissions - access db through service
        $db = $this->rbacService->getDb();
        $sql = "SELECT DISTINCT permission FROM role_permissions 
                WHERE tenant_id = ? AND deleted_at IS NULL 
                ORDER BY permission";
        $rs = $db->Execute($sql, [$this->tenantId]);

        $allPermissions = [];
        if ($rs !== false) {
            while (!$rs->EOF) {
                $allPermissions[] = $rs->fields['permission'];
                $rs->MoveNext();
            }
        }

        // Build matrix
        $matrix = [];
        foreach ($roles as $role) {
            $roleName = $role['role_name'];
            
            // Get permissions for this role
            $db = $this->rbacService->getDb();
            $sql = "SELECT permission FROM role_permissions 
                    WHERE tenant_id = ? AND role = ? AND deleted_at IS NULL";
            $rs = $db->Execute($sql, [$this->tenantId, $roleName]);

            $rolePermissions = [];
            if ($rs !== false) {
                while (!$rs->EOF) {
                    $rolePermissions[] = $rs->fields['permission'];
                    $rs->MoveNext();
                }
            }

            $matrix[$roleName] = [
                'role' => $role,
                'permissions' => $rolePermissions
            ];
        }

        $data = [
            'roles' => $roles,
            'permissions' => $allPermissions,
            'matrix' => $matrix
        ];

        return $this->respond($data);
    }

    /**
     * PUT /api/users/{userId}/roles
     * Update user's roles
     * 
     * Body: { "crmRole": "Sales Manager", "accountingRole": "Accountant" }
     */
    public function updateUserRoles($request, $userId): Response
    {
        $user = AuthMiddleware::verify($request);
        
        if (!PermissionChecker::can($user, 'system.users.manage')) {
            return $this->respond(null, 'Permission denied', 403);
        }

        $body = json_decode($request->body, true);
        $crmRole = $body['crmRole'] ?? null;
        $accountingRole = $body['accountingRole'] ?? null;

        $updates = [];
        $params = [];

        if ($crmRole !== null) {
            $updates[] = "crm_role = ?";
            $params[] = $crmRole;
        }

        if ($accountingRole !== null) {
            $updates[] = "accounting_role = ?";
            $params[] = $accountingRole;
        }

        if (empty($updates)) {
            return $this->respond(null, 'No roles to update', 400);
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $userId;
        $params[] = $this->tenantId;

        $sql = "UPDATE users SET " . implode(', ', $updates) . " 
                WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL";

        $db = $this->rbacService->getDb();
        $result = $db->Execute($sql, $params);

        if ($result === false) {
            return $this->respond(null, 'Failed to update user roles', 500);
        }

        // Invalidate user's permission cache
        $this->rbacService->invalidate($userId);

        return $this->respond(['message' => 'User roles updated successfully']);
    }
}
