-- Migration 005: Create role_permissions table
-- Requirements: 2.1, 2.2, 2.3
--
-- Stores per-tenant, per-role permission grants.
-- Every controllable operation is represented as a permission string
-- in the format module.action (e.g. crm.contacts.create).
--
-- Universal columns: id, company_code, tenant_id, created_by,
--                    created_at, updated_at, deleted_at

CREATE TABLE IF NOT EXISTS role_permissions (
    id           BIGSERIAL    PRIMARY KEY,

    -- Multi-tenancy & company scope (universal columns)
    tenant_id    UUID         NOT NULL,
    company_code VARCHAR(2)   NOT NULL DEFAULT '01',

    -- RBAC
    role         VARCHAR(20)  NOT NULL
                     CHECK (role IN ('Owner','Admin','Manager','Agent','Support',
                                     'Accountant','Reviewer','Viewer')),
    permission   VARCHAR(100) NOT NULL,   -- module.action format, e.g. crm.contacts.create

    -- Audit columns
    created_by   BIGINT       REFERENCES users(id) ON DELETE SET NULL,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at   TIMESTAMPTZ,             -- NULL = active (soft delete)

    -- A role may hold a given permission only once per tenant
    CONSTRAINT uq_role_permissions_tenant_role_perm
        UNIQUE (tenant_id, role, permission)
);

-- Indexes for fast permission lookups
CREATE INDEX IF NOT EXISTS idx_rp_tenant_role
    ON role_permissions(tenant_id, role)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_rp_tenant_role_perm
    ON role_permissions(tenant_id, role, permission)
    WHERE deleted_at IS NULL;

-- Auto-update updated_at on every row change
CREATE TRIGGER trg_role_permissions_updated_at
    BEFORE UPDATE ON role_permissions
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();
