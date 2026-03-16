<?php
/**
 * Modules/Roles/ApiController.php
 * CRUD for tenant-specific roles and permission management.
 */

namespace Modules\Roles;

use Core\Database;
use Core\TenantEnforcer;
use Core\AuditLogger;
use Core\Auth\RbacGuard;
use Core\Auth\JwtManager;

class ApiController {

    /**
     * GET /api/roles — List all roles for the current tenant.
     */
    public function index() {
        RbacGuard::enforce('users', 'manage_roles');
        $tenantId = TenantEnforcer::getTenantId();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            "SELECT r.id, r.role_name, r.display_name, r.is_system, r.permissions_json,
                    (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) as user_count
             FROM roles r WHERE r.tenant_id = ? ORDER BY r.is_system DESC, r.role_name ASC"
        );
        $stmt->execute([$tenantId]);
        $roles = $stmt->fetchAll();

        // Parse permissions_json for frontend
        foreach ($roles as &$role) {
            $role['permissions'] = json_decode($role['permissions_json'], true);
            unset($role['permissions_json']);
        }

        return json_encode(['success' => true, 'data' => $roles]);
    }

    /**
     * POST /api/roles — Create a new custom role for the tenant.
     */
    public function store($data) {
        RbacGuard::enforce('users', 'manage_roles');
        $tenantId = TenantEnforcer::getTenantId();

        if (empty($data['role_name']) || empty($data['permissions'])) {
            throw new \Exception("role_name and permissions are required", 400);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO roles (tenant_id, role_name, display_name, is_system, permissions_json) VALUES (?, ?, ?, 0, ?)"
        );
        $stmt->execute([
            $tenantId,
            strtolower(str_replace(' ', '_', $data['role_name'])),
            $data['display_name'] ?? $data['role_name'],
            json_encode($data['permissions'])
        ]);

        $roleId = $pdo->lastInsertId();
        AuditLogger::log($tenantId, JwtManager::getCurrentUserId(), 'role_created', 'role', $roleId, null, $data);

        return json_encode(['success' => true, 'id' => $roleId]);
    }

    /**
     * PATCH /api/roles/{id} — Update a custom role's permissions.
     */
    public function update($id, $data) {
        RbacGuard::enforce('users', 'manage_roles');
        $tenantId = TenantEnforcer::getTenantId();
        $pdo = Database::getConnection();

        // Fetch existing to audit
        $old = $pdo->prepare("SELECT permissions_json FROM roles WHERE id = ? AND tenant_id = ?");
        $old->execute([$id, $tenantId]);
        $oldRole = $old->fetch();

        if (!$oldRole) throw new \Exception("Role not found", 404);

        $fields = [];
        $params = [];

        if (isset($data['display_name'])) {
            $fields[] = "display_name = ?";
            $params[] = $data['display_name'];
        }
        if (isset($data['permissions'])) {
            $fields[] = "permissions_json = ?";
            $params[] = json_encode($data['permissions']);
        }

        if (empty($fields)) throw new \Exception("Nothing to update", 400);

        $params[] = $id;
        $params[] = $tenantId;

        $pdo->prepare("UPDATE roles SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?")
            ->execute($params);

        // Invalidate RBAC cache for all users with this role
        $affected = $pdo->prepare("SELECT user_id FROM user_roles WHERE role_id = ?");
        $affected->execute([$id]);
        foreach ($affected->fetchAll() as $row) {
            RbacGuard::invalidateCache($row['user_id'], $tenantId);
        }

        AuditLogger::log($tenantId, JwtManager::getCurrentUserId(), 'role_updated', 'role', $id, 
            json_decode($oldRole['permissions_json'], true), $data['permissions'] ?? null);

        return json_encode(['success' => true]);
    }

    /**
     * DELETE /api/roles/{id} — Delete a custom role (system roles are protected).
     */
    public function destroy($id) {
        RbacGuard::enforce('users', 'manage_roles');
        $tenantId = TenantEnforcer::getTenantId();
        $pdo = Database::getConnection();

        // Cannot delete system roles
        $check = $pdo->prepare("SELECT is_system FROM roles WHERE id = ? AND tenant_id = ?");
        $check->execute([$id, $tenantId]);
        $role = $check->fetch();

        if (!$role) throw new \Exception("Role not found", 404);
        if ($role['is_system']) throw new \Exception("Cannot delete system roles", 403);

        $pdo->prepare("DELETE FROM roles WHERE id = ? AND tenant_id = ?")->execute([$id, $tenantId]);

        AuditLogger::log($tenantId, JwtManager::getCurrentUserId(), 'role_deleted', 'role', $id);

        return json_encode(['success' => true]);
    }

    /**
     * POST /api/users/{userId}/roles — Assign a role to a user.
     */
    public function assignRole($userId, $data) {
        RbacGuard::enforce('users', 'manage_roles');
        $tenantId = TenantEnforcer::getTenantId();
        $pdo = Database::getConnection();

        $roleId = $data['role_id'] ?? null;
        if (!$roleId) throw new \Exception("role_id is required", 400);

        // Verify role belongs to this tenant
        $check = $pdo->prepare("SELECT id FROM roles WHERE id = ? AND tenant_id = ?");
        $check->execute([$roleId, $tenantId]);
        if (!$check->fetch()) throw new \Exception("Role not found in your organization", 404);

        $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)")
            ->execute([$userId, $roleId, JwtManager::getCurrentUserId()]);

        RbacGuard::invalidateCache($userId, $tenantId);
        AuditLogger::log($tenantId, JwtManager::getCurrentUserId(), 'role_assigned', 'user', $userId, null, ['role_id' => $roleId]);

        return json_encode(['success' => true]);
    }
}
