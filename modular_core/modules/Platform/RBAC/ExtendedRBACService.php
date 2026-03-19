<?php
/**
 * Platform/RBAC/ExtendedRBACService.php
 *
 * Extended RBAC Service supporting:
 * - 10 CRM roles (Sales Manager, Sales Person, Admin, etc.)
 * - 5 Accounting roles (Owner, Admin, Accountant, Reviewer, Viewer)
 * - Dual role assignment (CRM + Accounting)
 * - Role hierarchy with permission inheritance
 * - Data visibility rules (own vs all records)
 * - Assignment permissions
 * 
 * Based on: salesflow-crm-roles.md
 */

declare(strict_types=1);

namespace Modules\Platform\RBAC;

class ExtendedRBACService extends RBACService
{
    /**
     * Check if user can view all records of a resource type
     * 
     * @param int $userId
     * @param string $resource 'deals', 'leads', 'contacts', 'tasks', 'tickets'
     * @return bool
     */
    public function canViewAll(int $userId, string $resource): bool
    {
        $permission = "crm.{$resource}.view_all";
        return $this->check($userId, $permission);
    }

    /**
     * Check if user can only view their own records
     * 
     * @param int $userId
     * @param string $resource
     * @return bool
     */
    public function canViewOwn(int $userId, string $resource): bool
    {
        $permission = "crm.{$resource}.view_own";
        return $this->check($userId, $permission);
    }

    /**
     * Check if user can edit any record of a resource type
     * 
     * @param int $userId
     * @param string $resource
     * @return bool
     */
    public function canEditAny(int $userId, string $resource): bool
    {
        $permission = "crm.{$resource}.edit_any";
        return $this->check($userId, $permission);
    }

    /**
     * Check if user can edit their own records
     * 
     * @param int $userId
     * @param string $resource
     * @return bool
     */
    public function canEditOwn(int $userId, string $resource): bool
    {
        $permission = "crm.{$resource}.edit_own";
        return $this->check($userId, $permission);
    }

    /**
     * Check if user can assign records to others
     * 
     * @param int $userId
     * @param string $resource
     * @return bool
     */
    public function canAssign(int $userId, string $resource): bool
    {
        $permission = "crm.{$resource}.assign";
        return $this->check($userId, $permission);
    }

    /**
     * Check if user can comment on deals
     * Only Sales Manager and Admin can comment
     * 
     * @param int $userId
     * @return bool
     */
    public function canComment(int $userId): bool
    {
        return $this->check($userId, 'crm.deals.comment');
    }

    /**
     * Check if user can read comments on their own deals
     * Sales Person can read manager comments
     * 
     * @param int $userId
     * @return bool
     */
    public function canReadComments(int $userId): bool
    {
        return $this->check($userId, 'crm.deals.read_comments') || 
               $this->canComment($userId);
    }

    /**
     * Check if user can export data
     * 
     * @param int $userId
     * @param string $module 'crm', 'finance', 'accounting'
     * @return bool
     */
    public function canExport(int $userId, string $module = 'crm'): bool
    {
        return $this->check($userId, "{$module}.reports.export");
    }

    /**
     * Check if user can manage system users
     * Admin only
     * 
     * @param int $userId
     * @return bool
     */
    public function canManageUsers(int $userId): bool
    {
        return $this->check($userId, 'system.users.manage');
    }

    /**
     * Check if user can manage roles
     * Admin only
     * 
     * @param int $userId
     * @return bool
     */
    public function canManageRoles(int $userId): bool
    {
        return $this->check($userId, 'system.roles.manage');
    }

    /**
     * Check if user can approve discounts
     * Finance Manager and Admin only
     * 
     * @param int $userId
     * @return bool
     */
    public function canApproveDiscounts(int $userId): bool
    {
        return $this->check($userId, 'finance.approve_discounts');
    }

    /**
     * Get user's CRM role
     * 
     * @param int $userId
     * @return string|null
     */
    public function getUserCRMRole(int $userId): ?string
    {
        $sql = "SELECT crm_role FROM users 
                WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL";
        $rs = $this->db->Execute($sql, [$userId, $this->tenantId]);
        
        if ($rs === false || $rs->EOF) {
            return null;
        }
        
        return $rs->fields['crm_role'] ?? null;
    }

    /**
     * Get user's Accounting role
     * 
     * @param int $userId
     * @return string|null
     */
    public function getUserAccountingRole(int $userId): ?string
    {
        $sql = "SELECT accounting_role FROM users 
                WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL";
        $rs = $this->db->Execute($sql, [$userId, $this->tenantId]);
        
        if ($rs === false || $rs->EOF) {
            return null;
        }
        
        return $rs->fields['accounting_role'] ?? null;
    }

    /**
     * Get all permissions for a user (including inherited from role hierarchy)
     * 
     * @param int $userId
     * @return array
     */
    public function resolvePermissionsWithHierarchy(int $userId): array
    {
        $cacheKey = "permissions_hierarchy:{$this->tenantId}:{$userId}";
        
        // Try cache first
        $cached = $this->redis->get($cacheKey);
        if ($cached !== false && $cached !== null) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        
        // Get user's roles
        $crmRole = $this->getUserCRMRole($userId);
        $accountingRole = $this->getUserAccountingRole($userId);
        
        $allRoles = array_filter([$crmRole, $accountingRole]);
        if (empty($allRoles)) {
            return [];
        }
        
        // Get all roles including inherited ones
        $expandedRoles = $this->expandRolesWithHierarchy($allRoles);
        
        // Get permissions for all roles
        $permissions = $this->getPermissionsForRoles($expandedRoles);
        
        // Cache the result
        $this->redis->setex($cacheKey, self::CACHE_TTL, json_encode($permissions));
        
        return $permissions;
    }

    /**
     * Expand roles to include inherited roles from hierarchy
     * 
     * @param array $roles
     * @return array
     */
    private function expandRolesWithHierarchy(array $roles): array
    {
        $expanded = $roles;
        $toProcess = $roles;
        $processed = [];
        
        while (!empty($toProcess)) {
            $currentRole = array_shift($toProcess);
            
            if (in_array($currentRole, $processed)) {
                continue;
            }
            
            $processed[] = $currentRole;
            
            // Get child roles
            $sql = "SELECT child_role FROM role_hierarchy 
                    WHERE tenant_id = ? AND parent_role = ? AND deleted_at IS NULL";
            $rs = $this->db->Execute($sql, [$this->tenantId, $currentRole]);
            
            if ($rs !== false) {
                while (!$rs->EOF) {
                    $childRole = $rs->fields['child_role'];
                    if (!in_array($childRole, $expanded)) {
                        $expanded[] = $childRole;
                        $toProcess[] = $childRole;
                    }
                    $rs->MoveNext();
                }
            }
        }
        
        return $expanded;
    }

    /**
     * Get permissions for multiple roles
     * 
     * @param array $roles
     * @return array
     */
    private function getPermissionsForRoles(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql = "SELECT DISTINCT permission FROM role_permissions
                WHERE tenant_id = ? AND role IN ({$placeholders}) AND deleted_at IS NULL";
        
        $params = array_merge([$this->tenantId], $roles);
        $rs = $this->db->Execute($sql, $params);
        
        if ($rs === false) {
            return [];
        }
        
        $permissions = [];
        while (!$rs->EOF) {
            $perm = $rs->fields['permission'];
            
            // Handle wildcard permissions (e.g., 'crm.*', 'accounting.*')
            if (strpos($perm, '*') !== false) {
                $permissions[] = $perm;
            } else {
                $permissions[] = $perm;
            }
            
            $rs->MoveNext();
        }
        
        return $permissions;
    }

    /**
     * Check permission with wildcard support
     * 
     * @param int $userId
     * @param string $permission
     * @return bool
     */
    public function checkWithWildcard(int $userId, string $permission): bool
    {
        $permissions = $this->resolvePermissionsWithHierarchy($userId);
        
        // Direct match
        if (in_array($permission, $permissions, true)) {
            return true;
        }
        
        // Check wildcard matches
        foreach ($permissions as $perm) {
            if (strpos($perm, '*') !== false) {
                $pattern = str_replace('*', '.*', preg_quote($perm, '/'));
                if (preg_match("/^{$pattern}$/", $permission)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Get data visibility scope for a user
     * Returns 'all', 'own', or 'none'
     * 
     * @param int $userId
     * @param string $resource
     * @return string
     */
    public function getVisibilityScope(int $userId, string $resource): string
    {
        if ($this->canViewAll($userId, $resource)) {
            return 'all';
        }
        
        if ($this->canViewOwn($userId, $resource)) {
            return 'own';
        }
        
        return 'none';
    }

    /**
     * Get allowed navigation pages for a user based on their role
     * 
     * @param int $userId
     * @return array
     */
    public function getAllowedPages(int $userId): array
    {
        $crmRole = $this->getUserCRMRole($userId);
        $accountingRole = $this->getUserAccountingRole($userId);
        
        $pages = [];
        
        // CRM pages based on role
        $crmPages = $this->getCRMPagesForRole($crmRole);
        $pages = array_merge($pages, $crmPages);
        
        // Accounting pages based on role
        $accountingPages = $this->getAccountingPagesForRole($accountingRole);
        $pages = array_merge($pages, $accountingPages);
        
        return array_unique($pages);
    }

    /**
     * Get CRM pages allowed for a role
     * 
     * @param string|null $role
     * @return array
     */
    private function getCRMPagesForRole(?string $role): array
    {
        if ($role === null) {
            return [];
        }
        
        $rolePages = [
            'Sales Manager' => [
                'dashboard', 'leads', 'contacts', 'deals', 'tasks', 
                'reports', 'team-members', 'settings'
            ],
            'Sales Person' => [
                'dashboard', 'my-leads', 'my-contacts', 'my-deals', 
                'my-tasks', 'reports'
            ],
            'Admin' => [
                'dashboard', 'leads', 'contacts', 'deals', 'tasks', 
                'reports', 'team-members', 'roles', 'permissions', 
                'campaigns', 'tickets', 'finance', 'settings'
            ],
            'Marketing Manager' => [
                'dashboard', 'leads', 'campaigns', 'reports'
            ],
            'Marketing Specialist' => [
                'dashboard', 'leads', 'campaigns', 'reports'
            ],
            'Support Manager' => [
                'dashboard', 'tickets', 'contacts', 'reports'
            ],
            'Support Agent' => [
                'dashboard', 'tickets', 'contacts'
            ],
            'Finance Manager' => [
                'dashboard', 'finance', 'reports'
            ],
            'Finance Analyst' => [
                'dashboard', 'finance', 'reports'
            ],
            'Viewer' => [
                'dashboard'
            ]
        ];
        
        return $rolePages[$role] ?? [];
    }

    /**
     * Get Accounting pages allowed for a role
     * 
     * @param string|null $role
     * @return array
     */
    private function getAccountingPagesForRole(?string $role): array
    {
        if ($role === null) {
            return [];
        }
        
        $rolePages = [
            'Owner' => [
                'accounting-dashboard', 'journal-entries', 'chart-of-accounts',
                'financial-statements', 'reports', 'payroll', 'partners',
                'settings'
            ],
            'Admin' => [
                'accounting-dashboard', 'journal-entries', 'chart-of-accounts',
                'financial-statements', 'reports', 'settings'
            ],
            'Accountant' => [
                'accounting-dashboard', 'journal-entries', 'chart-of-accounts',
                'reports'
            ],
            'Reviewer' => [
                'accounting-dashboard', 'journal-entries', 'financial-statements',
                'reports'
            ],
            'Viewer' => [
                'accounting-dashboard'
            ]
        ];
        
        return $rolePages[$role] ?? [];
    }

    /**
     * Check if user can access a specific page
     * 
     * @param int $userId
     * @param string $page
     * @return bool
     */
    public function canAccessPage(int $userId, string $page): bool
    {
        $allowedPages = $this->getAllowedPages($userId);
        return in_array($page, $allowedPages, true);
    }

    /**
     * Get role metadata
     * 
     * @param string $roleName
     * @return array|null
     */
    public function getRoleMetadata(string $roleName): array
    {
        $sql = "SELECT * FROM roles 
                WHERE tenant_id = ? AND role_name = ? AND deleted_at IS NULL";
        $rs = $this->db->Execute($sql, [$this->tenantId, $roleName]);
        
        if ($rs === false || $rs->EOF) {
            return null;
        }
        
        return $rs->fields;
    }

    /**
     * Get all available roles for a tenant
     * 
     * @param string|null $roleType Filter by type: 'crm', 'accounting', 'platform'
     * @return array
     */
    public function getAllRoles(?string $roleType = null): array
    {
        $sql = "SELECT * FROM roles WHERE tenant_id = ? AND is_active = TRUE AND deleted_at IS NULL";
        $params = [$this->tenantId];
        
        if ($roleType !== null) {
            $sql .= " AND role_type = ?";
            $params[] = $roleType;
        }
        
        $sql .= " ORDER BY access_level DESC, role_name";
        
        $rs = $this->db->Execute($sql, $params);
        
        if ($rs === false) {
            return [];
        }
        
        $roles = [];
        while (!$rs->EOF) {
            $roles[] = $rs->fields;
            $rs->MoveNext();
        }
        
        return $roles;
    }
}
