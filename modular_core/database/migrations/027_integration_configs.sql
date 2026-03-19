-- ─────────────────────────────────────────────────────────────
-- 027 — Integration Configs, Communication Log, DID Numbers
-- Shared foundation for all telecom/call-center integrations
-- ─────────────────────────────────────────────────────────────

-- Master integration config per tenant
CREATE TABLE IF NOT EXISTS integration_configs (
    id              BIGSERIAL PRIMARY KEY,
    tenant_id       UUID NOT NULL,
    company_code    VARCHAR(2),
    platform        VARCHAR(50) NOT NULL,
    is_active       SMALLINT DEFAULT 1,
    credentials     TEXT NOT NULL,          -- AES-256 encrypted JSON
    settings        JSONB,
    webhook_secret  VARCHAR(255),
    created_at      TIMESTAMP DEFAULT NOW(),
    updated_at      TIMESTAMP DEFAULT NOW(),
    deleted_at      TIMESTAMP,
    UNIQUE(tenant_id, platform)
);

-- Unified communication log across ALL platforms
CREATE TABLE IF NOT EXISTS communication_log (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2),
    platform            VARCHAR(50),
    direction           VARCHAR(10) CHECK (direction IN ('inbound','outbound')),
    channel             VARCHAR(20) CHECK (channel IN ('voice','sms','whatsapp','email','chat')),
    from_number         VARCHAR(50),
    to_number           VARCHAR(50),
    contact_id          BIGINT,
    agent_id            VARCHAR(20),
    call_duration_sec   INT,
    recording_url       TEXT,
    transcript_text     TEXT,
    sentiment_score     DECIMAL(4,3),
    status              VARCHAR(30),
    metadata            JSONB,
    created_at          TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_comm_log_tenant    ON communication_log(tenant_id, created_at);
CREATE INDEX IF NOT EXISTS idx_comm_log_contact   ON communication_log(contact_id);
CREATE INDEX IF NOT EXISTS idx_comm_log_platform  ON communication_log(platform, direction);

-- DID / phone number registry per tenant
CREATE TABLE IF NOT EXISTS did_numbers (
    id              BIGSERIAL PRIMARY KEY,
    tenant_id       UUID NOT NULL,
    company_code    VARCHAR(2),
    platform        VARCHAR(50),
    number          VARCHAR(30) UNIQUE NOT NULL,
    country_code    VARCHAR(5),
    type            VARCHAR(20) CHECK (type IN ('local','tollfree','mobile','shortcode')),
    is_active       SMALLINT DEFAULT 1,
    assigned_to     VARCHAR(50),
    monthly_cost    DECIMAL(10,2),
    currency_code   VARCHAR(3),
    created_at      TIMESTAMP DEFAULT NOW()
);
