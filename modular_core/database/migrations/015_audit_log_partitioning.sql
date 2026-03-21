-- Requirement 10.125: Audit Log Partitioning by Month
-- Prevents performance degradation for high-volume enterprise neighbors.

-- 1. Create the partition master table
CREATE TABLE saas_audit_log_partitioned (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    actor_id INT NOT NULL,
    action_name VARCHAR(100) NOT NULL,
    module_name VARCHAR(100) NOT NULL,
    description TEXT,
    tenant_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    impacted_records JSONB,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
) PARTITION BY RANGE (created_at);

-- 2. Create historical and current partitions
CREATE TABLE saas_audit_log_2024_01 PARTITION OF saas_audit_log_partitioned
    FOR VALUES FROM ('2024-01-01 00:00:00') TO ('2024-02-01 00:00:00');

CREATE TABLE saas_audit_log_2024_02 PARTITION OF saas_audit_log_partitioned
    FOR VALUES FROM ('2024-02-01 00:00:00') TO ('2024-03-01 00:00:00');

CREATE TABLE saas_audit_log_2024_03 PARTITION OF saas_audit_log_partitioned
    FOR VALUES FROM ('2024-03-01 00:00:00') TO ('2024-04-01 00:00:00');

-- 3. Default partition for unexpected ranges
CREATE TABLE saas_audit_log_default PARTITION OF saas_audit_log_partitioned DEFAULT;

-- 4. Automatically create future partitions (Logic to be triggered via CRON)
-- Requirement 10.125 - Maintenance Automation
