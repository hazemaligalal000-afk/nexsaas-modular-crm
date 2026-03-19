-- ══════════════════════════════════════════════════════════════════════════
-- Migration 034: Extended RBAC - SalesFlow CRM + Accounting Roles
-- Based on: salesflow-crm-roles.md
-- Extends existing role_permissions table with 10 CRM roles + 5 Accounting roles
-- ══════════════════════════════════════════════════════════════════════════

-- ──────────────────────────────────────────────────────────────────────────
-- 1. Update role_permissions table to support new roles
-- ──────────────────────────────────────────────────────────────────────────
ALTER TABLE role_permissions DROP CONSTRAINT IF EXISTS role_permissions_role_check;

ALTER TABLE role_permissions ADD CONSTRAINT role_permissions_role_check
    CHECK (role IN (
        -- CRM Roles (10)
        'Sales Manager', 'Sales Person', 'Admin',
        'Marketing Manager', 'Marketing Specialist',
        'Support Manager', 'Support Agent',
        'Finance Manager', 'Finance Analyst', 'Viewer',
        -- Accounting Roles (5)
        'Owner', 'Accountant', 'Reviewer',
        -- Legacy roles (keep for backward compatibility)
        'Manager', 'Agent', 'Support'
    ));

-- ──────────────────────────────────────────────────────────────────────────
-- 2. Update users table to support dual roles (CRM + Accounting)
-- ──────────────────────────────────────────────────────────────────────────
ALTER TABLE users ADD COLUMN IF NOT EXISTS crm_role VARCHAR(50);
ALTER TABLE users ADD COLUMN IF NOT EXISTS accounting_role VARCHAR(50);

-- Migrate existing platform_role to crm_role
UPDATE users SET crm_role = platform_role WHERE crm_role IS NULL AND platform_role IS NOT NULL;

COMMENT ON COLUMN users.crm_role IS 'CRM role: Sales Manager, Sales Person, Admin, Marketing Manager, Marketing Specialist, Support Manager, Support Agent, Finance Manager, Finance Analyst, Viewer';
COMMENT ON COLUMN users.accounting_role IS 'Accounting role: Owner, Admin, Accountant, Reviewer, Viewer';

-- ──────────────────────────────────────────────────────────────────────────
-- 3. Role metadata table
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL DEFAULT '01',
    role_name VARCHAR(50) NOT NULL,
    role_type VARCHAR(20) NOT NULL,  -- 'crm', 'accounting', 'platform'
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(10),  -- Emoji icon
    access_level VARCHAR(20),  -- 'full', 'high', 'medium', 'limited', 'minimal', 'read-only'
    is_active BOOLEAN DEFAULT TRUE,
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, role_name, deleted_at),
    CONSTRAINT chk_role_type CHECK (role_type IN ('crm', 'accounting', 'platform')),
    CONSTRAINT chk_access_level CHECK (access_level IN ('full', 'high', 'medium', 'limited', 'minimal', 'read-only'))
);

CREATE INDEX idx_roles_tenant ON roles(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_roles_type ON roles(tenant_id, role_type) WHERE deleted_at IS NULL;

COMMENT ON TABLE roles IS 'Role metadata - defines all available roles in the system';

-- ──────────────────────────────────────────────────────────────────────────
-- 4. Role hierarchy (for inheritance)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS role_hierarchy (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    parent_role VARCHAR(50) NOT NULL,
    child_role VARCHAR(50) NOT NULL,
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, parent_role, child_role, deleted_at)
);

CREATE INDEX idx_role_hierarchy_tenant ON role_hierarchy(tenant_id) WHERE deleted_at IS NULL;

COMMENT ON TABLE role_hierarchy IS 'Role inheritance - child roles inherit parent permissions';

-- ══════════════════════════════════════════════════════════════════════════
-- SEED DATA: CRM Roles (from salesflow-crm-roles.md)
-- ══════════════════════════════════════════════════════════════════════════

-- Note: tenant_id will be set during tenant provisioning
-- This is template data

INSERT INTO roles (tenant_id, role_name, role_type, display_name, description, icon, access_level, created_by)
VALUES
    -- CRM Roles
    (:tenant_id, 'Sales Manager', 'crm', 'Sales Manager', 'High-level sales oversight with full team visibility and comment authority', '👔', 'high', 'system'),
    (:tenant_id, 'Sales Person', 'crm', 'Sales Person', 'Personal view only — own assigned records, no team data', '💼', 'limited', 'system'),
    (:tenant_id, 'Admin', 'crm', 'Admin', 'Full system access — all modules, all settings, user and role management', '⚙️', 'full', 'system'),
    (:tenant_id, 'Marketing Manager', 'crm', 'Marketing Manager', 'Full marketing access — leads, campaigns, and marketing reports', '📢', 'medium', 'system'),
    (:tenant_id, 'Marketing Specialist', 'crm', 'Marketing Specialist', 'Limited marketing access — assigned leads and view-only campaigns', '🎯', 'limited', 'system'),
    (:tenant_id, 'Support Manager', 'crm', 'Support Manager', 'Full support access — ticket management, team assignment, SLA oversight', '🛠️', 'medium', 'system'),
    (:tenant_id, 'Support Agent', 'crm', 'Support Agent', 'Assigned tickets only — no deal, lead, or financial access', '🎧', 'limited', 'system'),
    (:tenant_id, 'Finance Manager', 'crm', 'Finance Manager', 'Full financial access — deal values, revenue, discount approval, and exports', '💰', 'medium', 'system'),
    (:tenant_id, 'Finance Analyst', 'crm', 'Finance Analyst', 'Read-only financial view — no edit, no export, no discount approval', '📊', 'read-only', 'system'),
    (:tenant_id, 'Viewer', 'crm', 'Viewer', 'Minimal read-only access — dashboard summary only, no interactions', '👁️', 'minimal', 'system'),
    
    -- Accounting Roles
    (:tenant_id, 'Owner', 'accounting', 'Owner', 'Full accounting system access with all permissions', '👑', 'full', 'system'),
    (:tenant_id, 'Accountant', 'accounting', 'Accountant', 'Create and edit journal entries, manage accounts', '📒', 'high', 'system'),
    (:tenant_id, 'Reviewer', 'accounting', 'Reviewer', 'Review and approve journal entries, view reports', '✅', 'medium', 'system')
ON CONFLICT (tenant_id, role_name, deleted_at) DO NOTHING;

-- ══════════════════════════════════════════════════════════════════════════
-- SEED DATA: CRM Permissions (from salesflow-crm-roles.md permission matrix)
-- ══════════════════════════════════════════════════════════════════════════

-- Sales Manager Permissions
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    -- Deals
    (:tenant_id, 'Sales Manager', 'crm.deals.view_all', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.deals.view_own', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.deals.edit_any', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.deals.edit_own', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.deals.delete', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.deals.assign', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.deals.comment', 'system'),
    -- Leads
    (:tenant_id, 'Sales Manager', 'crm.leads.view_all', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.leads.view_own', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.leads.edit_any', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.leads.edit_own', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.leads.assign', 'system'),
    -- Contacts
    (:tenant_id, 'Sales Manager', 'crm.contacts.view_all', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.contacts.view_own', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.contacts.edit_any', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.contacts.assign', 'system'),
    -- Tasks
    (:tenant_id, 'Sales Manager', 'crm.tasks.view_all', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.tasks.view_own', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.tasks.create', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.tasks.assign', 'system'),
    -- Reports
    (:tenant_id, 'Sales Manager', 'crm.reports.view_team', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.reports.view_personal', 'system'),
    (:tenant_id, 'Sales Manager', 'crm.reports.export', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- Sales Person Permissions
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    (:tenant_id, 'Sales Person', 'crm.deals.view_own', 'system'),
    (:tenant_id, 'Sales Person', 'crm.deals.edit_own', 'system'),
    (:tenant_id, 'Sales Person', 'crm.deals.read_comments', 'system'),
    (:tenant_id, 'Sales Person', 'crm.leads.view_own', 'system'),
    (:tenant_id, 'Sales Person', 'crm.leads.edit_own', 'system'),
    (:tenant_id, 'Sales Person', 'crm.contacts.view_own', 'system'),
    (:tenant_id, 'Sales Person', 'crm.tasks.view_own', 'system'),
    (:tenant_id, 'Sales Person', 'crm.tasks.create', 'system'),
    (:tenant_id, 'Sales Person', 'crm.reports.view_personal', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- Admin Permissions (Full Access)
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    -- All CRM permissions
    (:tenant_id, 'Admin', 'crm.*', 'system'),
    -- System management
    (:tenant_id, 'Admin', 'system.users.manage', 'system'),
    (:tenant_id, 'Admin', 'system.roles.manage', 'system'),
    (:tenant_id, 'Admin', 'system.settings.edit', 'system'),
    (:tenant_id, 'Admin', 'system.audit.view', 'system'),
    -- Finance
    (:tenant_id, 'Admin', 'finance.view', 'system'),
    (:tenant_id, 'Admin', 'finance.approve_discounts', 'system'),
    -- Campaigns
    (:tenant_id, 'Admin', 'crm.campaigns.view', 'system'),
    (:tenant_id, 'Admin', 'crm.campaigns.manage', 'system'),
    -- Support
    (:tenant_id, 'Admin', 'crm.tickets.view_all', 'system'),
    (:tenant_id, 'Admin', 'crm.tickets.manage', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- Marketing Manager Permissions
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    (:tenant_id, 'Marketing Manager', 'crm.leads.view_all', 'system'),
    (:tenant_id, 'Marketing Manager', 'crm.leads.create', 'system'),
    (:tenant_id, 'Marketing Manager', 'crm.leads.edit_any', 'system'),
    (:tenant_id, 'Marketing Manager', 'crm.leads.assign', 'system'),
    (:tenant_id, 'Marketing Manager', 'crm.leads.export', 'system'),
    (:tenant_id, 'Marketing Manager', 'crm.contacts.view_all', 'system'),
    (:tenant_id, 'Marketing Manager', 'crm.campaigns.view', 'system'),
    (:tenant_id, 'Marketing Manager', 'crm.campaigns.manage', 'system'),
    (:tenant_id, 'Marketing Manager', 'crm.campaigns.budget', 'system'),
    (:tenant_id, 'Marketing Manager', 'crm.reports.view_team', 'system'),
    (:tenant_id, 'Marketing Manager', 'crm.reports.export', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- Marketing Specialist Permissions
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    (:tenant_id, 'Marketing Specialist', 'crm.leads.view_own', 'system'),
    (:tenant_id, 'Marketing Specialist', 'crm.leads.edit_own', 'system'),
    (:tenant_id, 'Marketing Specialist', 'crm.contacts.view_own', 'system'),
    (:tenant_id, 'Marketing Specialist', 'crm.campaigns.view', 'system'),
    (:tenant_id, 'Marketing Specialist', 'crm.reports.view_personal', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- Support Manager Permissions
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    (:tenant_id, 'Support Manager', 'crm.tickets.view_all', 'system'),
    (:tenant_id, 'Support Manager', 'crm.tickets.manage', 'system'),
    (:tenant_id, 'Support Manager', 'crm.tickets.assign', 'system'),
    (:tenant_id, 'Support Manager', 'crm.tickets.priority', 'system'),
    (:tenant_id, 'Support Manager', 'crm.contacts.view_all', 'system'),
    (:tenant_id, 'Support Manager', 'crm.contacts.edit', 'system'),
    (:tenant_id, 'Support Manager', 'crm.deals.view_all', 'system'),
    (:tenant_id, 'Support Manager', 'crm.tasks.view_all', 'system'),
    (:tenant_id, 'Support Manager', 'crm.tasks.assign', 'system'),
    (:tenant_id, 'Support Manager', 'crm.reports.view_team', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- Support Agent Permissions
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    (:tenant_id, 'Support Agent', 'crm.tickets.view_own', 'system'),
    (:tenant_id, 'Support Agent', 'crm.tickets.edit_own', 'system'),
    (:tenant_id, 'Support Agent', 'crm.contacts.view_own', 'system'),
    (:tenant_id, 'Support Agent', 'crm.tasks.view_own', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- Finance Manager Permissions
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    (:tenant_id, 'Finance Manager', 'finance.view', 'system'),
    (:tenant_id, 'Finance Manager', 'finance.export', 'system'),
    (:tenant_id, 'Finance Manager', 'finance.approve_discounts', 'system'),
    (:tenant_id, 'Finance Manager', 'finance.revenue', 'system'),
    (:tenant_id, 'Finance Manager', 'finance.budget', 'system'),
    (:tenant_id, 'Finance Manager', 'finance.invoices', 'system'),
    (:tenant_id, 'Finance Manager', 'crm.deals.view_all', 'system'),
    (:tenant_id, 'Finance Manager', 'crm.reports.view_team', 'system'),
    (:tenant_id, 'Finance Manager', 'crm.reports.export', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- Finance Analyst Permissions
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    (:tenant_id, 'Finance Analyst', 'finance.view', 'system'),
    (:tenant_id, 'Finance Analyst', 'crm.deals.view_all', 'system'),
    (:tenant_id, 'Finance Analyst', 'crm.reports.view', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- Viewer Permissions
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    (:tenant_id, 'Viewer', 'crm.dashboard.view', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- ══════════════════════════════════════════════════════════════════════════
-- SEED DATA: Accounting Permissions
-- ══════════════════════════════════════════════════════════════════════════

-- Owner Permissions (Full Accounting Access)
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    (:tenant_id, 'Owner', 'accounting.*', 'system'),
    (:tenant_id, 'Owner', 'accounting.voucher.create', 'system'),
    (:tenant_id, 'Owner', 'accounting.voucher.edit', 'system'),
    (:tenant_id, 'Owner', 'accounting.voucher.delete', 'system'),
    (:tenant_id, 'Owner', 'accounting.voucher.approve', 'system'),
    (:tenant_id, 'Owner', 'accounting.voucher.reverse', 'system'),
    (:tenant_id, 'Owner', 'accounting.period.close', 'system'),
    (:tenant_id, 'Owner', 'accounting.statements.view', 'system'),
    (:tenant_id, 'Owner', 'accounting.statements.export', 'system'),
    (:tenant_id, 'Owner', 'accounting.payroll.run', 'system'),
    (:tenant_id, 'Owner', 'accounting.partner.distribute', 'system'),
    (:tenant_id, 'Owner', 'accounting.coa.manage', 'system'),
    (:tenant_id, 'Owner', 'accounting.settings.edit', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- Accountant Permissions
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    (:tenant_id, 'Accountant', 'accounting.voucher.create', 'system'),
    (:tenant_id, 'Accountant', 'accounting.voucher.edit', 'system'),
    (:tenant_id, 'Accountant', 'accounting.voucher.view', 'system'),
    (:tenant_id, 'Accountant', 'accounting.coa.view', 'system'),
    (:tenant_id, 'Accountant', 'accounting.coa.create', 'system'),
    (:tenant_id, 'Accountant', 'accounting.statements.view', 'system'),
    (:tenant_id, 'Accountant', 'accounting.reports.view', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- Reviewer Permissions
INSERT INTO role_permissions (tenant_id, role, permission, created_by) VALUES
    (:tenant_id, 'Reviewer', 'accounting.voucher.view', 'system'),
    (:tenant_id, 'Reviewer', 'accounting.voucher.approve', 'system'),
    (:tenant_id, 'Reviewer', 'accounting.statements.view', 'system'),
    (:tenant_id, 'Reviewer', 'accounting.reports.view', 'system'),
    (:tenant_id, 'Reviewer', 'accounting.coa.view', 'system')
ON CONFLICT (tenant_id, role, permission) DO NOTHING;

-- ══════════════════════════════════════════════════════════════════════════
-- Role Hierarchy (Inheritance)
-- ══════════════════════════════════════════════════════════════════════════

INSERT INTO role_hierarchy (tenant_id, parent_role, child_role, created_by) VALUES
    -- CRM Hierarchy
    (:tenant_id, 'Admin', 'Sales Manager', 'system'),
    (:tenant_id, 'Sales Manager', 'Sales Person', 'system'),
    (:tenant_id, 'Admin', 'Marketing Manager', 'system'),
    (:tenant_id, 'Marketing Manager', 'Marketing Specialist', 'system'),
    (:tenant_id, 'Admin', 'Support Manager', 'system'),
    (:tenant_id, 'Support Manager', 'Support Agent', 'system'),
    (:tenant_id, 'Admin', 'Finance Manager', 'system'),
    (:tenant_id, 'Finance Manager', 'Finance Analyst', 'system'),
    
    -- Accounting Hierarchy
    (:tenant_id, 'Owner', 'Admin', 'system'),
    (:tenant_id, 'Admin', 'Accountant', 'system'),
    (:tenant_id, 'Admin', 'Reviewer', 'system'),
    (:tenant_id, 'Accountant', 'Viewer', 'system'),
    (:tenant_id, 'Reviewer', 'Viewer', 'system')
ON CONFLICT (tenant_id, parent_role, child_role, deleted_at) DO NOTHING;
