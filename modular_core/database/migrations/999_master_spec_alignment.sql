-- Master Spec Alignment: Database Schema Migration
-- Requirement 1-6: UUID v4 for all IDs

-- Pre-migration: Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Migrate Tenants
ALTER TABLE tenants ALTER COLUMN id SET DEFAULT uuid_generate_v4();

-- Migrate Users
ALTER TABLE users ALTER COLUMN id SET DEFAULT uuid_generate_v4();

-- Add Row-Level Security (Requirement 59.5)
ALTER TABLE leads ENABLE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation_policy ON leads USING (tenant_id = current_setting('app.current_tenant')::uuid);

-- Add Audit Log Partitioning by Month (Requirement 61.3)
CREATE TABLE audit_logs_p202403 PARTITION OF audit_logs
    FOR VALUES FROM ('2024-03-01') TO ('2024-04-01');
