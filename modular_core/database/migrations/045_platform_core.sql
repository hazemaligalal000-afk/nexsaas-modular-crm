-- Combined Migration 045: Platform Core Services (Webhooks, Audit Log, Notifications)
-- Requirements: 27.2, 30.1, 31.1
-- Tasks: 43-47

CREATE TABLE webhooks (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    url                 VARCHAR(255) NOT NULL,
    secret              VARCHAR(255) NOT NULL,
    event_types         TEXT[] NOT NULL,
    is_active           BOOLEAN DEFAULT TRUE,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE webhook_deliveries (
    id                  BIGSERIAL PRIMARY KEY,
    webhook_id          BIGINT NOT NULL REFERENCES webhooks(id),
    tenant_id           UUID NOT NULL,
    event_type          VARCHAR(100) NOT NULL,
    payload             JSONB NOT NULL,
    status              VARCHAR(20) NOT NULL, -- pending, success, failed
    response_code       INT,
    response_body       TEXT,
    attempts            INT DEFAULT 0,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE audit_log (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    user_id             BIGINT,
    operation           VARCHAR(20) NOT NULL, -- INSERT, UPDATE, DELETE
    table_name          VARCHAR(100) NOT NULL,
    record_id           BIGINT,
    prev_values         JSONB,
    new_values          JSONB,
    ip_address          VARCHAR(45),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Force append-only via Role Level Security (RLS)
ALTER TABLE audit_log ENABLE ROW LEVEL SECURITY;
CREATE POLICY audit_log_insert_only ON audit_log FOR INSERT WITH CHECK (true);
CREATE POLICY audit_log_select_only ON audit_log FOR SELECT USING (true);
-- No UPDATE or DELETE policies allowed for audit_log

CREATE TABLE notifications (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    user_id             BIGINT NOT NULL,
    type                VARCHAR(50) NOT NULL,
    content             TEXT NOT NULL,
    status              VARCHAR(20) DEFAULT 'unread', -- unread, read
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE webhooks IS 'Registered external webhooks for integrations';
COMMENT ON TABLE audit_log IS 'Immutable global audit log (Req 30.1)';
COMMENT ON TABLE notifications IS 'WebSocket notification persistence (Req 27.2)';
