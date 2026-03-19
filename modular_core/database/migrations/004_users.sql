-- Migration 004: Create users table
-- Requirements: 1.1, 1.2, 4.1
--
-- Carries all universal columns: id, company_code, tenant_id,
-- created_by, created_at, updated_at, deleted_at

CREATE TABLE IF NOT EXISTS users (
    id               BIGSERIAL PRIMARY KEY,

    -- Multi-tenancy & company scope (universal columns)
    tenant_id        UUID         NOT NULL REFERENCES tenants(tenant_id) ON DELETE RESTRICT,
    company_code     VARCHAR(2)   NOT NULL DEFAULT '01',

    -- Identity
    email            VARCHAR(255) NOT NULL,
    password_hash    VARCHAR(255) NOT NULL,  -- bcrypt cost 12
    full_name        VARCHAR(255) NOT NULL,

    -- RBAC roles
    platform_role    VARCHAR(20)  NOT NULL
                         CHECK (platform_role IN ('Owner','Admin','Manager','Agent','Support')),
    accounting_role  VARCHAR(20)
                         CHECK (accounting_role IN ('Owner','Admin','Accountant','Reviewer','Viewer')),

    -- 2FA
    totp_secret      VARCHAR(100),

    -- Status
    is_active        BOOLEAN      NOT NULL DEFAULT true,

    -- Universal audit columns
    created_by       BIGINT,                 -- self-referential; NULL for first Owner
    created_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at       TIMESTAMPTZ,            -- NULL = active (soft delete)

    -- Constraints
    CONSTRAINT uq_users_tenant_email UNIQUE (tenant_id, email)
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_users_tenant_id    ON users(tenant_id)    WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_users_email        ON users(email)        WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_users_company_code ON users(tenant_id, company_code) WHERE deleted_at IS NULL;

-- Auto-update updated_at
CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

-- Add self-referential FK for created_by now that users table exists
ALTER TABLE users
    ADD CONSTRAINT fk_users_created_by
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;
