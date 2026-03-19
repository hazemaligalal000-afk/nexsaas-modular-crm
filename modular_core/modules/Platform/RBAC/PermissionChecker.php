<?php
/**
 * Platform/RBAC/PermissionChecker.php
 *
 * Static permission checker utility for controllers
 * Provides convenient methods for permission checks in controllers
 */

declare(strict_types=1);

namespace Modules\Platform\RBAC;

class PermissionChecker
{
    private static ?ExtendedRBACService $rbacService = null;

    /**
     * Initialize the RBAC service
     * 
     * @param ExtendedRBACService $service
     */
    public static function init(ExtendedRBACService $service): void
    {
        self::$rbacService = $service;
    }

    /**
     * Check if user has permission
     * 
     * @param object $user User object with 'id' property
     * @param string $permission Permission string
     * @return bool
     * @throws \RuntimeException if RBAC service not initialized
     */
    public static function can(object $user, string $permission): bool
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized. Call PermissionChecker::init() first.');
        }

        return self::$rbacService->checkWithWildcard($user->id, $permission);
    }

    /**
     * Check permission and throw exception if denied
     * 
     * @param object $user
     * @param string $permission
     * @throws \RuntimeException if permission denied
     */
    public static function require(object $user, string $permission): void
    {
        if (!self::can($user, $permission)) {
            throw new \RuntimeException("Permission denied: {$permission}");
        }
    }

    /**
     * Check if user can view all records of a resource
     * 
     * @param object $user
     * @param string $resource
     * @return bool
     */
    public static function canViewAll(object $user, string $resource): bool
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized.');
        }

        return self::$rbacService->canViewAll($user->id, $resource);
    }

    /**
     * Check if user can only view own records
     * 
     * @param object $user
     * @param string $resource
     * @return bool
     */
    public static function canViewOwn(object $user, string $resource): bool
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized.');
        }

        return self::$rbacService->canViewOwn($user->id, $resource);
    }

    /**
     * Check if user can edit any record
     * 
     * @param object $user
     * @param string $resource
     * @return bool
     */
    public static function canEditAny(object $user, string $resource): bool
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized.');
        }

        return self::$rbacService->canEditAny($user->id, $resource);
    }

    /**
     * Check if user can edit own records
     * 
     * @param object $user
     * @param string $resource
     * @return bool
     */
    public static function canEditOwn(object $user, string $resource): bool
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized.');
        }

        return self::$rbacService->canEditOwn($user->id, $resource);
    }

    /**
     * Check if user can assign records
     * 
     * @param object $user
     * @param string $resource
     * @return bool
     */
    public static function canAssign(object $user, string $resource): bool
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized.');
        }

        return self::$rbacService->canAssign($user->id, $resource);
    }

    /**
     * Get visibility scope for user
     * Returns 'all', 'own', or 'none'
     * 
     * @param object $user
     * @param string $resource
     * @return string
     */
    public static function getVisibilityScope(object $user, string $resource): string
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized.');
        }

        return self::$rbacService->getVisibilityScope($user->id, $resource);
    }

    /**
     * Check if user can access a page
     * 
     * @param object $user
     * @param string $page
     * @return bool
     */
    public static function canAccessPage(object $user, string $page): bool
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized.');
        }

        return self::$rbacService->canAccessPage($user->id, $page);
    }

    /**
     * Get user's CRM role
     * 
     * @param object $user
     * @return string|null
     */
    public static function getCRMRole(object $user): ?string
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized.');
        }

        return self::$rbacService->getUserCRMRole($user->id);
    }

    /**
     * Get user's Accounting role
     * 
     * @param object $user
     * @return string|null
     */
    public static function getAccountingRole(object $user): ?string
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized.');
        }

        return self::$rbacService->getUserAccountingRole($user->id);
    }

    /**
     * Check if user can comment on deals
     * 
     * @param object $user
     * @return bool
     */
    public static function canComment(object $user): bool
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized.');
        }

        return self::$rbacService->canComment($user->id);
    }

    /**
     * Check if user can export data
     * 
     * @param object $user
     * @param string $module
     * @return bool
     */
    public static function canExport(object $user, string $module = 'crm'): bool
    {
        if (self::$rbacService === null) {
            throw new \RuntimeException('PermissionChecker not initialized.');
        }

        return self::$rbacService->canExport($user->id, $module);
    }
}
