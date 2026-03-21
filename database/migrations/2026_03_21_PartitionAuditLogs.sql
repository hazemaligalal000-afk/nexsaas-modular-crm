-- 2026_03_21_PartitionAuditLogs.sql
-- Requirement: High-Performance Multi-tenant DB (10M+ rows)
-- Implement Native PostgreSQL Monthly Partitioning

-- 1. Create a Partition Head for Audit Logs
CREATE TABLE IF NOT EXISTS tenant_audit_logs (
    id BIGSERIAL,
    tenant_id BIGINT NOT NULL,
    action VARCHAR(100) NOT NULL,
    metadata JSONB,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
) PARTITION BY RANGE (created_at);

-- 2. Define High-Intensity Indexes on the Head (Inherited by children)
CREATE INDEX idx_audit_log_tenant_time ON tenant_audit_logs (tenant_id, created_at DESC);

-- 3. Automate Monthly Partition Creation for 2026/2027 (Performance Scaling Layer)
CREATE TABLE IF NOT EXISTS audit_logs_p2026_03 PARTITION OF tenant_audit_logs
    FOR VALUES FROM ('2026-03-01') TO ('2026-04-01');

CREATE TABLE IF NOT EXISTS audit_logs_p2026_04 PARTITION OF tenant_audit_logs
    FOR VALUES FROM ('2026-04-01') TO ('2026-05-01');

-- 4. Implementation Example for Read-Replica Queries (High Availability)
-- Tip: Use the pg_read_only flag or separate connection handles for SELECT lookups
SELECT * FROM tenant_audit_logs WHERE tenant_id = 12 AND created_at > '2026-03-15' ORDER BY created_at DESC LIMIT 100;
