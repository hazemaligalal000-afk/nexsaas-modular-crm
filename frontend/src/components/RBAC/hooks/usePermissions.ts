/**
 * usePermissions Hook
 * 
 * React hook for checking user permissions and roles
 * Integrates with the backend RBAC system
 */

import { useQuery } from '@tanstack/react-query';
import { useAuth } from '@/hooks/useAuth';

interface PermissionsData {
  permissions: string[];
  crmRole: string | null;
  accountingRole: string | null;
  allowedPages: string[];
}

export const usePermissions = () => {
  const { user } = useAuth();

  // Fetch user permissions from backend
  const { data, isLoading } = useQuery<PermissionsData>({
    queryKey: ['permissions', user?.id],
    queryFn: async () => {
      const response = await fetch('/api/rbac/permissions', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to fetch permissions');
      }
      
      return response.json();
    },
    enabled: !!user,
    staleTime: 5 * 60 * 1000, // 5 minutes
    cacheTime: 10 * 60 * 1000 // 10 minutes
  });

  /**
   * Check if user has a specific permission
   * Supports wildcard matching (e.g., 'crm.*' matches 'crm.deals.view')
   */
  const hasPermission = (permission: string): boolean => {
    if (!data?.permissions) return false;

    // Direct match
    if (data.permissions.includes(permission)) {
      return true;
    }

    // Wildcard match
    for (const perm of data.permissions) {
      if (perm.includes('*')) {
        const pattern = perm.replace('*', '.*');
        const regex = new RegExp(`^${pattern}$`);
        if (regex.test(permission)) {
          return true;
        }
      }
    }

    return false;
  };

  /**
   * Check if user has a specific role
   */
  const hasRole = (role: string, type: 'crm' | 'accounting' = 'crm'): boolean => {
    if (type === 'crm') {
      return data?.crmRole === role;
    }
    return data?.accountingRole === role;
  };

  /**
   * Check if user can view all records of a resource
   */
  const canViewAll = (resource: string): boolean => {
    return hasPermission(`crm.${resource}.view_all`);
  };

  /**
   * Check if user can only view own records
   */
  const canViewOwn = (resource: string): boolean => {
    return hasPermission(`crm.${resource}.view_own`);
  };

  /**
   * Check if user can edit any record
   */
  const canEditAny = (resource: string): boolean => {
    return hasPermission(`crm.${resource}.edit_any`);
  };

  /**
   * Check if user can edit own records
   */
  const canEditOwn = (resource: string): boolean => {
    return hasPermission(`crm.${resource}.edit_own`);
  };

  /**
   * Check if user can assign records
   */
  const canAssign = (resource: string): boolean => {
    return hasPermission(`crm.${resource}.assign`);
  };

  /**
   * Check if user can comment on deals
   */
  const canComment = (): boolean => {
    return hasPermission('crm.deals.comment');
  };

  /**
   * Check if user can read comments
   */
  const canReadComments = (): boolean => {
    return hasPermission('crm.deals.read_comments') || canComment();
  };

  /**
   * Check if user can export data
   */
  const canExport = (module: string = 'crm'): boolean => {
    return hasPermission(`${module}.reports.export`);
  };

  /**
   * Get visibility scope for a resource
   * Returns 'all', 'own', or 'none'
   */
  const getVisibilityScope = (resource: string): 'all' | 'own' | 'none' => {
    if (canViewAll(resource)) return 'all';
    if (canViewOwn(resource)) return 'own';
    return 'none';
  };

  /**
   * Check if user can access a specific page
   */
  const canAccessPage = (page: string): boolean => {
    return data?.allowedPages?.includes(page) ?? false;
  };

  /**
   * Check if user can manage users
   */
  const canManageUsers = (): boolean => {
    return hasPermission('system.users.manage');
  };

  /**
   * Check if user can manage roles
   */
  const canManageRoles = (): boolean => {
    return hasPermission('system.roles.manage');
  };

  /**
   * Check if user is Admin (either CRM or Accounting)
   */
  const isAdmin = (): boolean => {
    return data?.crmRole === 'Admin' || data?.accountingRole === 'Admin' || data?.accountingRole === 'Owner';
  };

  /**
   * Get user's CRM role
   */
  const userCRMRole = data?.crmRole ?? null;

  /**
   * Get user's Accounting role
   */
  const userAccountingRole = data?.accountingRole ?? null;

  return {
    // Permission checks
    hasPermission,
    hasRole,
    canViewAll,
    canViewOwn,
    canEditAny,
    canEditOwn,
    canAssign,
    canComment,
    canReadComments,
    canExport,
    getVisibilityScope,
    canAccessPage,
    canManageUsers,
    canManageRoles,
    isAdmin,
    
    // Role info
    userCRMRole,
    userAccountingRole,
    
    // Loading state
    isLoading,
    
    // Raw data
    permissions: data?.permissions ?? [],
    allowedPages: data?.allowedPages ?? []
  };
};

/**
 * useResourcePermissions Hook
 * 
 * Specialized hook for checking permissions on a specific resource
 */
export const useResourcePermissions = (resource: string) => {
  const permissions = usePermissions();

  return {
    canViewAll: permissions.canViewAll(resource),
    canViewOwn: permissions.canViewOwn(resource),
    canEditAny: permissions.canEditAny(resource),
    canEditOwn: permissions.canEditOwn(resource),
    canAssign: permissions.canAssign(resource),
    visibilityScope: permissions.getVisibilityScope(resource),
    isLoading: permissions.isLoading
  };
};

/**
 * useRoleInfo Hook
 * 
 * Get detailed role information
 */
export const useRoleInfo = () => {
  const { userCRMRole, userAccountingRole, isAdmin } = usePermissions();

  const getRoleIcon = (role: string | null): string => {
    const icons: Record<string, string> = {
      'Sales Manager': '👔',
      'Sales Person': '💼',
      'Admin': '⚙️',
      'Marketing Manager': '📢',
      'Marketing Specialist': '🎯',
      'Support Manager': '🛠️',
      'Support Agent': '🎧',
      'Finance Manager': '💰',
      'Finance Analyst': '📊',
      'Viewer': '👁️',
      'Owner': '👑',
      'Accountant': '📒',
      'Reviewer': '✅'
    };
    return icons[role || ''] || '👤';
  };

  const getRoleDisplayName = (role: string | null): string => {
    return role || 'No Role';
  };

  return {
    crmRole: userCRMRole,
    accountingRole: userAccountingRole,
    crmRoleIcon: getRoleIcon(userCRMRole),
    accountingRoleIcon: getRoleIcon(userAccountingRole),
    crmRoleDisplay: getRoleDisplayName(userCRMRole),
    accountingRoleDisplay: getRoleDisplayName(userAccountingRole),
    isAdmin
  };
};
