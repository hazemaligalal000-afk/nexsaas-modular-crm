CREATE TABLE IF NOT EXISTS workflows (
    workflow_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    trigger_event VARCHAR(100) NOT NULL, -- e.g., 'lead_created', 'deal_won'
    payload JSONB NOT NULL, -- Full visual flow storage
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'paused'
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_workflows_tenant_trigger ON workflows(tenant_id, trigger_event);

-- Log table for high-intensity monitoring
CREATE TABLE IF NOT EXISTS workflow_logs (
    log_id BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    workflow_id UUID REFERENCES workflows(workflow_id),
    status VARCHAR(20), -- 'success', 'error', 'waiting'
    execution_time NUMERIC(10, 4),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
