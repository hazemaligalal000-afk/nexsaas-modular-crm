-- Migration: 012_pipeline_deals.sql
-- Creates pipelines, pipeline_stages, deals, and deal_stage_history tables.
-- Requirements: 10.1, 10.2

-- ============================================================
-- pipelines
-- ============================================================
CREATE TABLE IF NOT EXISTS pipelines (
    id           BIGSERIAL    PRIMARY KEY,
    tenant_id    UUID         NOT NULL,
    company_code VARCHAR(2)   NOT NULL DEFAULT '01',
    name         VARCHAR(100) NOT NULL,
    created_by   BIGINT,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at   TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_pipelines_tenant
    ON pipelines(tenant_id, company_code)
    WHERE deleted_at IS NULL;

-- ============================================================
-- pipeline_stages
-- ============================================================
CREATE TABLE IF NOT EXISTS pipeline_stages (
    id             BIGSERIAL   PRIMARY KEY,
    tenant_id      UUID        NOT NULL,
    company_code   VARCHAR(2)  NOT NULL DEFAULT '01',
    pipeline_id    BIGINT      NOT NULL REFERENCES pipelines(id),
    name           VARCHAR(100) NOT NULL,
    position       SMALLINT    NOT NULL,
    is_closed_won  BOOLEAN     NOT NULL DEFAULT FALSE,
    is_closed_lost BOOLEAN     NOT NULL DEFAULT FALSE,
    created_by     BIGINT,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at     TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_pipeline_stages_pipeline
    ON pipeline_stages(pipeline_id)
    WHERE deleted_at IS NULL;

-- ============================================================
-- deals
-- ============================================================
CREATE TABLE IF NOT EXISTS deals (
    id                         BIGSERIAL    PRIMARY KEY,
    tenant_id                  UUID         NOT NULL,
    company_code               VARCHAR(2)   NOT NULL DEFAULT '01',
    title                      VARCHAR(255) NOT NULL,
    value                      DECIMAL(15,2) NOT NULL DEFAULT 0,
    currency_code              VARCHAR(3)   NOT NULL DEFAULT 'EGP',
    pipeline_id                BIGINT       NOT NULL REFERENCES pipelines(id),
    stage_id                   BIGINT       NOT NULL REFERENCES pipeline_stages(id),
    close_date                 DATE,
    probability                DECIMAL(5,2),              -- manual probability
    win_probability            DECIMAL(5,4),              -- AI predicted 0.0000–1.0000
    win_probability_updated_at TIMESTAMPTZ,
    owner_id                   BIGINT       REFERENCES users(id),
    contact_id                 BIGINT       REFERENCES contacts(id),
    account_id                 BIGINT       REFERENCES accounts(id),
    is_overdue                 BOOLEAN      NOT NULL DEFAULT FALSE,
    is_stale                   BOOLEAN      NOT NULL DEFAULT FALSE,
    last_activity_at           TIMESTAMPTZ,
    created_by                 BIGINT,
    created_at                 TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at                 TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at                 TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_deals_tenant
    ON deals(tenant_id, company_code)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_deals_pipeline
    ON deals(pipeline_id, stage_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_deals_account
    ON deals(account_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_deals_owner
    ON deals(tenant_id, owner_id)
    WHERE deleted_at IS NULL;

-- ============================================================
-- deal_stage_history
-- ============================================================
CREATE TABLE IF NOT EXISTS deal_stage_history (
    id            BIGSERIAL   PRIMARY KEY,
    tenant_id     UUID        NOT NULL,
    company_code  VARCHAR(2)  NOT NULL DEFAULT '01',
    deal_id       BIGINT      NOT NULL REFERENCES deals(id),
    from_stage_id BIGINT      REFERENCES pipeline_stages(id),
    to_stage_id   BIGINT      NOT NULL REFERENCES pipeline_stages(id),
    changed_by    BIGINT      NOT NULL,
    changed_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_deal_stage_history_deal
    ON deal_stage_history(deal_id);
