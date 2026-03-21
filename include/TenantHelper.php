<?php
/**
 * Custom SaaS Multi-Company / Tenant Helper
 * Automatically handles isolating data per organization.
 */

class TenantHelper {
    public static $current_organization_id = null;

    // List of core tables that require tenant isolation.
    public static $tenant_tables = [
        'vtiger_crmentity',
        'vtiger_users',
        'vtiger_leaddetails',
        'vtiger_contactdetails',
        'vtiger_account',
        'vtiger_potential',
        'saas_deal_stages',
        'saas_webhooks',
        'saas_api_keys'
    ];

    /**
     * Initializes the tenant context based on the current user's session or API context.
     */
    public static function init() {
        if(isset($_SESSION['authenticated_user_id'])) {
            // Ideally load organization_id from user table and cache in session.
            if(isset($_SESSION['organization_id'])) {
                self::$current_organization_id = $_SESSION['organization_id'];
            }
        }
    }

    /**
     * Set the Organization ID manually (e.g. for API requests)
     */
    public static function setOrganizationId($id) {
        self::$current_organization_id = $id;
    }

    /**
     * Get the current Organization ID
     */
    public static function getOrganizationId() {
        return self::$current_organization_id;
    }

    /**
     * Check if a table needs tenant isolation.
     */
    public static function isTenantTable($table_name) {
        return in_array($table_name, self::$tenant_tables);
    }

    /**
     * Intelligently modify queries to inject organization_id WHERE clauses
     * for seamless multi-company isolation across the entire legacy CRM.
     * Note: This is an architectural stub for the query parser layer.
     */
    public static function isolateQuery($sql) {
        if(self::$current_organization_id === null) {
            return $sql;
        }

        // Extremely simplified parser: In production, use an AST SQL parser 
        // to securely inject `organization_id = X` into WHERE clauses.
        // For now, this serves as the foundational integration point in PearDatabase.php.

        return $sql;
    }
}
?>
