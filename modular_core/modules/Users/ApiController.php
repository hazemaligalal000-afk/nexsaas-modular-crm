<?php
/**
 * Modules/Users/ApiController.php
 * Full CRUD for Tenant User Management with RBAC enforcement.
 */

namespace Modules\Users;

use Core\Database;
use Core\TenantEnforcer;
use Core\AuditLogger;
use Core\Auth\RbacGuard;
use Core\Auth\JwtManager;

class ApiController {

    /**
     * GET /api/users
     */
    public function index() {
        RbacGuard::enforce('users', 'read');
        $tenantId = TenantEnforcer::getTenantId();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            "SELECT u.id, u.name, u.email, u.is_active, u.last_login_at, u.created_at,
                    GROUP_CONCAT(r.display_name) as roles
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE u.tenant_id = ?
             GROUP BY u.id
             ORDER BY u.created_at DESC"
        );
        $stmt->execute([$tenantId]);

        return json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    /**
     * POST /api/users
     */
    public function store($data) {
        RbacGuard::enforce('users', 'create');
        $tenantId = TenantEnforcer::getTenantId();
        $pdo = Database::getConnection();

        // Validate
        if (empty($data['email']) || empty($data['password']) || empty($data['name'])) {
            throw new \Exception("Name, email, and password are required", 400);
        }

        // Check plan limits
        $userCount = $pdo->prepare("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?");
        $userCount->execute([$tenantId]);
        $count = $userCount->fetch()['cnt'];
        // TODO: Compare against plan max_users limit

        $stmt = $pdo->prepare(
            "INSERT INTO users (tenant_id, uuid, name, email, password_hash) VALUES (?, UUID(), ?, ?, ?)"
        );
        $stmt->execute([
            $tenantId,
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12])
        ]);

        $newUserId = $pdo->lastInsertId();

        // Assign default role if provided
        if (!empty($data['role_id'])) {
            $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")
                ->execute([$newUserId, $data['role_id']]);
        }

        AuditLogger::log($tenantId, JwtManager::getCurrentUserId(), 'user_created', 'user', $newUserId);

        return json_encode(['success' => true, 'id' => $newUserId]);
    }

    /**
     * PATCH /api/users/{id}
     */
    public function update($id, $data) {
        RbacGuard::enforce('users', 'update');
        $tenantId = TenantEnforcer::getTenantId();
        $pdo = Database::getConnection();

        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $params[] = (int)$data['is_active'];
        }

        if (empty($fields)) throw new \Exception("No fields to update", 400);

        $params[] = $id;
        $params[] = $tenantId;

        $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?")
            ->execute($params);

        AuditLogger::log($tenantId, JwtManager::getCurrentUserId(), 'user_updated', 'user', $id);

        return json_encode(['success' => true]);
    }

    /**
     * DELETE /api/users/{id}
     */
    public function destroy($id) {
        RbacGuard::enforce('users', 'delete');
        $tenantId = TenantEnforcer::getTenantId();
        $pdo = Database::getConnection();

        // Prevent self-deletion
        if ($id == JwtManager::getCurrentUserId()) {
            throw new \Exception("Cannot delete your own account", 400);
        }

        $pdo->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?")->execute([$id, $tenantId]);

        AuditLogger::log($tenantId, JwtManager::getCurrentUserId(), 'user_deleted', 'user', $id);

        return json_encode(['success' => true]);
    }
}
