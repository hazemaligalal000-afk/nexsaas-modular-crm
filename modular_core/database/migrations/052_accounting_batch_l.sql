-- Migration 052: Reporting and Dashboard Widgets (Batch L)
-- Task 40.1, 40.3

CREATE TABLE dashboards (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    user_id             BIGINT NOT NULL,
    dashboard_name      VARCHAR(100) NOT NULL,
    is_default          BOOLEAN DEFAULT FALSE,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE dashboard_widgets (
    id                  BIGSERIAL PRIMARY KEY,
    dashboard_id        BIGINT NOT NULL REFERENCES dashboards(id) ON DELETE CASCADE,
    widget_type         VARCHAR(50) NOT NULL, -- ratio_card, ar_aging_chart, cash_flow_graph, etc.
    position_x          INT NOT NULL DEFAULT 0,
    position_y          INT NOT NULL DEFAULT 0,
    width               INT NOT NULL DEFAULT 3,
    height              INT NOT NULL DEFAULT 2,
    configuration       JSONB NOT NULL DEFAULT '{}',
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE report_presets (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    user_id             BIGINT NOT NULL,
    preset_name         VARCHAR(100) NOT NULL,
    data_source         VARCHAR(50) NOT NULL, -- ar, ap, gl
    selected_columns    JSONB NOT NULL DEFAULT '[]',
    filters             JSONB NOT NULL DEFAULT '{}',
    group_by            VARCHAR(50),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE scheduled_reports (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    report_preset_id    BIGINT NOT NULL REFERENCES report_presets(id),
    schedule_interval   VARCHAR(20) NOT NULL, -- daily, weekly, monthly
    export_format       VARCHAR(10) NOT NULL, -- pdf, excel
    delivery_emails     TEXT[] NOT NULL,
    last_run_at         TIMESTAMPTZ,
    next_run_at         TIMESTAMPTZ,
    is_active           BOOLEAN DEFAULT TRUE,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE dashboards IS 'Customizable dashboard containers per user (Req 56.1)';
COMMENT ON TABLE dashboard_widgets IS 'Widget layouts preserving XY coordinates and JSON configurations (Property 27)';
COMMENT ON TABLE report_presets IS 'Saved dynamic queries built via Custom Report Builder (Req 56.3)';
COMMENT ON TABLE scheduled_reports IS 'Metadata for Celery cron workers to generate and email PDF/Excel reports (Req 56.4)';
