/**
 * PermissionGate Component
 * 
 * Conditionally renders children based on user permissions
 * Supports:
 * - Single permission check
 * - Multiple permissions (AND/OR logic)
 * - Role-based rendering
 * - Fallback content for denied access
 */

import React from 'react';
import { usePermissions } from './hooks/usePermissions';

interface PermissionGateProps {
  /** Single permission string or array of permissions */
  permission?: string | string[];
  
  /** Logic for multiple permissions: 'and' (all required) or 'or' (any required) */
  logic?: 'and' | 'or';
  
  /** Required CRM role(s) */
  crmRole?: string | string[];
  
  /** Required Accounting role(s) */
  accountingRole?: string | string[];
  
  /** Content to render if permission is granted */
  children: React.ReactNode;
  
  /** Optional fallback content if permission is denied */
  fallback?: React.ReactNode;
  
  /** If true, renders children but disables interactive elements */
  disableOnly?: boolean;
}

export const PermissionGate: React.FC<PermissionGateProps> = ({
  permission,
  logic = 'and',
  crmRole,
  accountingRole,
  children,
  fallback = null,
  disableOnly = false
}) => {
  const { hasPermission, hasRole, userCRMRole, userAccountingRole } = usePermissions();

  // Check permissions
  let hasRequiredPermission = true;
  if (permission) {
    const permissions = Array.isArray(permission) ? permission : [permission];
    
    if (logic === 'and') {
      hasRequiredPermission = permissions.every(p => hasPermission(p));
    } else {
      hasRequiredPermission = permissions.some(p => hasPermission(p));
    }
  }

  // Check CRM role
  let hasRequiredCRMRole = true;
  if (crmRole) {
    const roles = Array.isArray(crmRole) ? crmRole : [crmRole];
    hasRequiredCRMRole = roles.includes(userCRMRole || '');
  }

  // Check Accounting role
  let hasRequiredAccountingRole = true;
  if (accountingRole) {
    const roles = Array.isArray(accountingRole) ? accountingRole : [accountingRole];
    hasRequiredAccountingRole = roles.includes(userAccountingRole || '');
  }

  const hasAccess = hasRequiredPermission && hasRequiredCRMRole && hasRequiredAccountingRole;

  if (!hasAccess) {
    return <>{fallback}</>;
  }

  if (disableOnly) {
    return (
      <div className="pointer-events-none opacity-50">
        {children}
      </div>
    );
  }

  return <>{children}</>;
};

/**
 * PermissionButton Component
 * 
 * Button that is disabled (but visible) when user lacks permission
 * Shows tooltip explaining why it's disabled
 */
interface PermissionButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  permission: string;
  children: React.ReactNode;
  deniedTooltip?: string;
}

export const PermissionButton: React.FC<PermissionButtonProps> = ({
  permission,
  children,
  deniedTooltip = 'You do not have permission to perform this action',
  ...buttonProps
}) => {
  const { hasPermission } = usePermissions();
  const allowed = hasPermission(permission);

  return (
    <button
      {...buttonProps}
      disabled={!allowed || buttonProps.disabled}
      title={!allowed ? deniedTooltip : buttonProps.title}
      className={`${buttonProps.className} ${!allowed ? 'cursor-not-allowed opacity-50' : ''}`}
    >
      {children}
    </button>
  );
};

/**
 * VisibilityScope Component
 * 
 * Renders different content based on user's visibility scope (all vs own)
 */
interface VisibilityScopeProps {
  resource: string;
  children: {
    all?: React.ReactNode;
    own?: React.ReactNode;
    none?: React.ReactNode;
  };
}

export const VisibilityScope: React.FC<VisibilityScopeProps> = ({
  resource,
  children
}) => {
  const { getVisibilityScope } = usePermissions();
  const scope = getVisibilityScope(resource);

  if (scope === 'all' && children.all) {
    return <>{children.all}</>;
  }

  if (scope === 'own' && children.own) {
    return <>{children.own}</>;
  }

  if (scope === 'none' && children.none) {
    return <>{children.none}</>;
  }

  return null;
};

/**
 * RoleBasedNav Component
 * 
 * Navigation component that only shows allowed pages
 */
interface RoleBasedNavProps {
  pages: Array<{
    path: string;
    label: string;
    icon?: React.ReactNode;
  }>;
  className?: string;
}

export const RoleBasedNav: React.FC<RoleBasedNavProps> = ({ pages, className }) => {
  const { canAccessPage } = usePermissions();

  const allowedPages = pages.filter(page => canAccessPage(page.path));

  return (
    <nav className={className}>
      {allowedPages.map(page => (
        <a
          key={page.path}
          href={page.path}
          className="flex items-center gap-2 px-4 py-2 hover:bg-gray-100 rounded"
        >
          {page.icon}
          <span>{page.label}</span>
        </a>
      ))}
    </nav>
  );
};
