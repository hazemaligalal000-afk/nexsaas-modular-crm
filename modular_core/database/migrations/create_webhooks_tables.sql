-- Webhooks Tables Migration
-- Requirements: 31.1

CREATE TABLE IF NOT EXISTS webhooks (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2),
    name VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    events TEXT[] NOT NULL, -- Array of event types to subscribe to
    secret VARCHAR(255) NOT NULL, -- For HMAC-SHA256 signing
    is_active BOOLEAN DEFAULT true,
    created_by BIGINT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id BIGSERIAL PRIMARY KEY,
    webhook_id BIGINT NOT NULL REFERENCES webhooks(id),
    tenant_id UUID NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSONB NOT NULL,
    status VARCHAR(20) NOT NULL, -- pending, success, failed
    http_status_code INTEGER,
    response_body TEXT,
    error_message TEXT,
    attempt_number INTEGER DEFAULT 1,
    delivered_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Indexes
CREATE INDEX idx_webhooks_tenant ON webhooks(tenant_id);
CREATE INDEX idx_webhooks_active ON webhooks(is_active) WHERE deleted_at IS NULL;
CREATE INDEX idx_webhook_deliveries_webhook ON webhook_deliveries(webhook_id);
CREATE INDEX idx_webhook_deliveries_status ON webhook_deliveries(status);
CREATE INDEX idx_webhook_deliveries_created ON webhook_deliveries(created_at);

-- Cleanup old deliveries (30 days retention)
CREATE INDEX idx_webhook_deliveries_cleanup ON webhook_deliveries(created_at) 
    WHERE created_at < NOW() - INTERVAL '30 days';

COMMENT ON TABLE webhooks IS 'Webhook subscriptions for external integrations';
COMMENT ON TABLE webhook_deliveries IS 'Webhook delivery attempts and responses (30-day retention)';
COMMENT ON COLUMN webhooks.secret IS 'Secret key for HMAC-SHA256 payload signing';
COMMENT ON COLUMN webhook_deliveries.attempt_number IS 'Retry attempt number (max 5)';
