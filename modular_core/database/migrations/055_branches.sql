-- Migration 055: Branches / Area Registry
-- Based on System Reference Section 2

CREATE TABLE IF NOT EXISTS branches (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,
    branch_code VARCHAR(2) NOT NULL, -- '01', '02', etc.
    name_en VARCHAR(200) NOT NULL,
    name_ar VARCHAR(200),
    address TEXT,
    is_hq BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, company_code, branch_code, deleted_at)
);

CREATE INDEX idx_branches_lookup ON branches(tenant_id, company_code, branch_code) WHERE deleted_at IS NULL;

-- Initial branches for Globalize (01)
-- tenant_id to be set by application
COMMENT ON TABLE branches IS 'Branch/Area registry per company';
