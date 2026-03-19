-- Migration 026: Dashboard Builder
-- Requirements: 17.6, 17.7
--
-- Tables:
--   dashboards        — stores dashboard definitions with grid layout config
--   dashboard_widgets — stores individual widgets placed on a dashboard

-- ---------------------------------------------------------------------------
-- dashboards
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS dashboards (
    id             BIGSERIAL       PRIMARY KEY,
    tenant_id      UUID            NOT NULL,
    company_code   VARCHAR(10)     NOT NULL,
    name           VARCHAR(255)    NOT NULL,
    owner_id       BIGINT          NOT NULL,
    layout_config  JSONB           NOT NULL DEFAULT '{}',  -- grid metadata (cols, row_height, etc.)
    is_default     BOOLEAN         NOT NULL DEFAULT FALSE,
    created_by     BIGINT          NOT NULL,
    created_at     TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at     TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_dashboards_tenant
    ON dashboards (tenant_id, company_code)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_dashboards_owner
    ON dashboards (owner_id, tenant_id)
    WHERE deleted_at IS NULL;

-- Only one default dashboard per tenant+owner
CREATE UNIQUE INDEX IF NOT EXISTS idx_dashboards_default
    ON dashboards (tenant_id, company_code, owner_id)
    WHERE is_default = TRUE AND deleted_at IS NULL;

-- ---------------------------------------------------------------------------
-- dashboard_widgets
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id                       BIGSERIAL       PRIMARY KEY,
    tenant_id                UUID            NOT NULL,
    company_code             VARCHAR(10)     NOT NULL,
    dashboard_id             BIGINT          NOT NULL REFERENCES dashboards(id) ON DELETE CASCADE,
    widget_type              VARCHAR(50)     NOT NULL
                                 CHECK (widget_type IN (
                                     'report',
                                     'pipeline_summary',
                                     'deal_velocity',
                                     'lead_conversion',
                                     'activity_summary',
                                     'revenue_forecast',
                                     'custom'
                                 )),
    report_id                BIGINT          REFERENCES custom_reports(id) ON DELETE SET NULL,
    title                    VARCHAR(255)    NOT NULL,
    config                   JSONB           NOT NULL DEFAULT '{}',  -- filters, date_range, etc.
    grid_x                   SMALLINT        NOT NULL DEFAULT 0,
    grid_y                   SMALLINT        NOT NULL DEFAULT 0,
    grid_w                   SMALLINT        NOT NULL DEFAULT 4,
    grid_h                   SMALLINT        NOT NULL DEFAULT 3,
    refresh_interval_seconds INT             NOT NULL DEFAULT 300,
    last_refreshed_at        TIMESTAMPTZ,
    created_by               BIGINT          NOT NULL,
    created_at               TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at               TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at               TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_dashboard_widgets_dashboard
    ON dashboard_widgets (dashboard_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_dashboard_widgets_tenant
    ON dashboard_widgets (tenant_id, company_code)
    WHERE deleted_at IS NULL;

-- Index for the refresh task: find widgets due for refresh
CREATE INDEX IF NOT EXISTS idx_dashboard_widgets_refresh
    ON dashboard_widgets (last_refreshed_at, refresh_interval_seconds)
    WHERE deleted_at IS NULL;
