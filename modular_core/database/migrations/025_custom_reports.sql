-- Migration 025: Custom Report Builder
-- Requirements: 17.2, 17.4, 17.5
--
-- Tables:
--   custom_reports    — stores report definitions (data source, dimensions, metrics, filters)
--   report_schedules  — stores scheduled delivery config per report

-- ---------------------------------------------------------------------------
-- custom_reports
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS custom_reports (
    id            BIGSERIAL       PRIMARY KEY,
    tenant_id     UUID            NOT NULL,
    company_code  VARCHAR(10)     NOT NULL,
    name          VARCHAR(255)    NOT NULL,
    description   TEXT,
    data_source   VARCHAR(50)     NOT NULL,   -- contacts|leads|deals|activities|conversations
    dimensions    JSONB           NOT NULL DEFAULT '[]',
    metrics       JSONB           NOT NULL DEFAULT '[]',
    filters       JSONB           NOT NULL DEFAULT '[]',
    sort_config   JSONB           NOT NULL DEFAULT '{}',
    owner_id      BIGINT          NOT NULL,
    created_by    BIGINT          NOT NULL,
    created_at    TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at    TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_custom_reports_tenant
    ON custom_reports (tenant_id, company_code)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_custom_reports_owner
    ON custom_reports (owner_id, tenant_id)
    WHERE deleted_at IS NULL;

-- ---------------------------------------------------------------------------
-- report_schedules
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS report_schedules (
    id            BIGSERIAL       PRIMARY KEY,
    tenant_id     UUID            NOT NULL,
    company_code  VARCHAR(10)     NOT NULL,
    report_id     BIGINT          NOT NULL REFERENCES custom_reports(id) ON DELETE CASCADE,
    frequency     VARCHAR(20)     NOT NULL CHECK (frequency IN ('daily', 'weekly', 'monthly')),
    next_run_at   TIMESTAMPTZ     NOT NULL,
    recipients    JSONB           NOT NULL DEFAULT '[]',   -- array of email strings
    format        VARCHAR(10)     NOT NULL DEFAULT 'csv' CHECK (format IN ('csv', 'pdf')),
    is_active     BOOLEAN         NOT NULL DEFAULT TRUE,
    created_by    BIGINT          NOT NULL,
    created_at    TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at    TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_report_schedules_report
    ON report_schedules (report_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_report_schedules_due
    ON report_schedules (next_run_at, is_active)
    WHERE deleted_at IS NULL AND is_active = TRUE;

CREATE INDEX IF NOT EXISTS idx_report_schedules_tenant
    ON report_schedules (tenant_id, company_code)
    WHERE deleted_at IS NULL;
