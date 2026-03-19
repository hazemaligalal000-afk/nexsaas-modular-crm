-- Migration: 011_accounts.sql
-- Creates the accounts table with hierarchy support, AI churn scoring,
-- and aggregate deal tracking.
-- Requirements: 9.1, 9.2, 9.3, 9.4, 9.5

CREATE TABLE IF NOT EXISTS accounts (
    id                      BIGSERIAL       PRIMARY KEY,
    company_code            VARCHAR(2)      NOT NULL,
    tenant_id               UUID            NOT NULL,
    -- company_name is the canonical field per design spec (alias: name)
    company_name            VARCHAR(255)    NOT NULL,
    parent_account_id       BIGINT          REFERENCES accounts(id),
    -- hierarchy_depth: 0 = root, max 4 (enforced in service layer for max 5 levels)
    hierarchy_depth         SMALLINT        NOT NULL DEFAULT 0
                                CHECK(hierarchy_depth BETWEEN 0 AND 4),
    industry                VARCHAR(100),
    website                 VARCHAR(255),
    phone                   VARCHAR(50),
    -- billing_address stored as JSONB for flexible address schema
    billing_address         JSONB,
    annual_revenue          DECIMAL(15,2),
    employee_count          INT,
    -- owner: the CRM user responsible for this account
    owner_id                BIGINT          REFERENCES users(id),
    -- AI-computed churn score (0–100); updated by ChurnPredictionService
    churn_score             SMALLINT        CHECK(churn_score BETWEEN 0 AND 100),
    churn_score_updated_at  TIMESTAMPTZ,
    -- aggregate deal metrics, updated by DealService on deal changes
    total_deal_value        DECIMAL(15,2)   NOT NULL DEFAULT 0,
    win_rate                DECIMAL(5,2)    NOT NULL DEFAULT 0,
    created_by              BIGINT,
    created_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at              TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_accounts_tenant
    ON accounts(tenant_id, company_code)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_accounts_parent
    ON accounts(parent_account_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_accounts_owner
    ON accounts(tenant_id, owner_id)
    WHERE deleted_at IS NULL;
