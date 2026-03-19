-- Audit Log Table Migration
-- Requirements: 30.1, 30.2, 30.3
-- Append-only table with row-level security preventing UPDATE/DELETE

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    user_id BIGINT NOT NULL,
    operation VARCHAR(20) NOT NULL, -- create, update, delete, permission_change
    table_name VARCHAR(100) NOT NULL,
    record_id BIGINT,
    prev_values JSONB,
    new_values JSONB,
    ip_address INET,
    user_agent TEXT,
    timestamp TIMESTAMP NOT NULL DEFAULT NOW(),
    company_code VARCHAR(2),
    metadata JSONB
);

-- Indexes for fast searching
CREATE INDEX idx_audit_log_tenant ON audit_log(tenant_id);
CREATE INDEX idx_audit_log_user ON audit_log(user_id);
CREATE INDEX idx_audit_log_table ON audit_log(table_name);
CREATE INDEX idx_audit_log_timestamp ON audit_log(timestamp DESC);
CREATE INDEX idx_audit_log_operation ON audit_log(operation);
CREATE INDEX idx_audit_log_record ON audit_log(table_name, record_id);

-- Enable Row Level Security
ALTER TABLE audit_log ENABLE ROW LEVEL SECURITY;

-- Policy: Allow INSERT only
CREATE POLICY audit_log_insert_only ON audit_log
    FOR INSERT
    WITH CHECK (true);

-- Policy: Prevent UPDATE
CREATE POLICY audit_log_no_update ON audit_log
    FOR UPDATE
    USING (false);

-- Policy: Prevent DELETE
CREATE POLICY audit_log_no_delete ON audit_log
    FOR DELETE
    USING (false);

-- Policy: Allow SELECT with tenant isolation
CREATE POLICY audit_log_select_tenant ON audit_log
    FOR SELECT
    USING (tenant_id = current_setting('app.current_tenant_id')::UUID);

-- Grant permissions
GRANT SELECT, INSERT ON audit_log TO nexsaas_app;
GRANT USAGE, SELECT ON SEQUENCE audit_log_id_seq TO nexsaas_app;

COMMENT ON TABLE audit_log IS 'Immutable audit trail of all system operations';
COMMENT ON COLUMN audit_log.operation IS 'Type of operation: create, update, delete, permission_change';
COMMENT ON COLUMN audit_log.prev_values IS 'Previous values before update/delete (JSON)';
COMMENT ON COLUMN audit_log.new_values IS 'New values after create/update (JSON)';
