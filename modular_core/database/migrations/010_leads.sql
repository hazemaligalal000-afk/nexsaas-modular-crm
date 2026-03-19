-- Migration 010: Leads
-- Requirements: 7.1

CREATE TABLE IF NOT EXISTS leads (
    id                   BIGSERIAL PRIMARY KEY,
    tenant_id            UUID         NOT NULL,
    company_code         VARCHAR(2)   NOT NULL DEFAULT '01',
    full_name            VARCHAR(255) NOT NULL,
    email                VARCHAR(255),
    phone                VARCHAR(50),
    source               VARCHAR(50)  NOT NULL,  -- web_form|api|import|manual
    status               VARCHAR(30)  NOT NULL DEFAULT 'new',
    owner_id             BIGINT REFERENCES users(id),
    lead_score           SMALLINT     CHECK(lead_score BETWEEN 0 AND 100),
    score_updated_at     TIMESTAMPTZ,
    converted_at         TIMESTAMPTZ,
    converted_contact_id BIGINT,
    converted_account_id BIGINT,
    converted_deal_id    BIGINT,
    created_by           BIGINT,
    created_at           TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at           TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_leads_tenant
    ON leads(tenant_id, company_code)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_leads_status
    ON leads(tenant_id, status)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_leads_email
    ON leads(tenant_id, email)
    WHERE deleted_at IS NULL AND email IS NOT NULL;
