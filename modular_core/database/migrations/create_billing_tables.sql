-- Billing Tables Migration
-- Requirements: Master Spec - Complete Stripe Integration

-- Tenant Trials Table
CREATE TABLE IF NOT EXISTS tenant_trials (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    stripe_customer_id VARCHAR(255) NOT NULL,
    stripe_subscription_id VARCHAR(255) NOT NULL,
    trial_start TIMESTAMP NOT NULL,
    trial_end TIMESTAMP NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active', -- active, converted, cancelled, expired
    converted_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    reminder_sent BOOLEAN DEFAULT FALSE,
    reminder_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_trials_tenant (tenant_id),
    INDEX idx_tenant_trials_status (status),
    INDEX idx_tenant_trials_end (trial_end)
);

-- Usage Records Table
CREATE TABLE IF NOT EXISTS usage_records (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    service VARCHAR(100) NOT NULL, -- ai_lead_scoring, ai_email_drafting, storage, api_calls
    metric VARCHAR(100) NOT NULL, -- ai_tokens, storage_gb, api_calls
    quantity DECIMAL(15,4) NOT NULL,
    metadata JSONB NULL,
    recorded_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usage_tenant (tenant_id),
    INDEX idx_usage_service (service),
    INDEX idx_usage_recorded (recorded_at),
    INDEX idx_usage_tenant_service (tenant_id, service, recorded_at)
);

-- Subscription Items Table (for metered billing)
CREATE TABLE IF NOT EXISTS subscription_items (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    stripe_subscription_item_id VARCHAR(255) NOT NULL,
    metric_type VARCHAR(100) NOT NULL, -- ai_usage, storage, api_calls
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subscription_items_tenant (tenant_id),
    INDEX idx_subscription_items_metric (metric_type),
    UNIQUE KEY unique_tenant_metric (tenant_id, metric_type, status)
);

-- Payment History Table
CREATE TABLE IF NOT EXISTS payment_history (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    invoice_id VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(50) NOT NULL, -- succeeded, failed, pending
    payment_method VARCHAR(100) NULL,
    paid_at TIMESTAMP NULL,
    attempted_at TIMESTAMP NULL,
    failure_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_tenant (tenant_id),
    INDEX idx_payment_status (status),
    INDEX idx_payment_date (paid_at)
);

-- Dunning Sequences Table
CREATE TABLE IF NOT EXISTS dunning_sequences (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    invoice_id VARCHAR(255) NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 1,
    status VARCHAR(50) NOT NULL DEFAULT 'active', -- active, resolved, suspended
    started_at TIMESTAMP NOT NULL,
    last_attempt_at TIMESTAMP NULL,
    next_attempt_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    suspended_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dunning_tenant (tenant_id),
    INDEX idx_dunning_status (status),
    INDEX idx_dunning_next_attempt (next_attempt_at)
);

-- Seat Changes Table
CREATE TABLE IF NOT EXISTS seat_changes (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    previous_seats INT NOT NULL,
    new_seats INT NOT NULL,
    changed_by BIGINT NULL REFERENCES users(id),
    reason VARCHAR(255) NULL,
    changed_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seat_changes_tenant (tenant_id),
    INDEX idx_seat_changes_date (changed_at)
);

-- Invoices Table (local copy)
CREATE TABLE IF NOT EXISTS invoices (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    stripe_invoice_id VARCHAR(255) NOT NULL UNIQUE,
    amount_due DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(50) NOT NULL, -- draft, open, paid, void, uncollectible
    invoice_pdf_url TEXT NULL,
    hosted_invoice_url TEXT NULL,
    period_start TIMESTAMP NOT NULL,
    period_end TIMESTAMP NOT NULL,
    due_date TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoices_tenant (tenant_id),
    INDEX idx_invoices_status (status),
    INDEX idx_invoices_period (period_start, period_end)
);

-- Billing Events Log
CREATE TABLE IF NOT EXISTS billing_events (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NULL REFERENCES tenants(id),
    event_type VARCHAR(100) NOT NULL,
    event_data JSONB NOT NULL,
    stripe_event_id VARCHAR(255) NULL,
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_events_tenant (tenant_id),
    INDEX idx_billing_events_type (event_type),
    INDEX idx_billing_events_processed (processed)
);

-- Subscription Plans Table
CREATE TABLE IF NOT EXISTS subscription_plans (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    stripe_price_id VARCHAR(255) NOT NULL,
    price_per_seat DECIMAL(10,2) NOT NULL,
    billing_interval VARCHAR(20) NOT NULL, -- month, year
    max_seats INT NULL, -- NULL = unlimited
    features JSONB NOT NULL,
    limits JSONB NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plans_active (is_active)
);

-- Insert default plans
INSERT INTO subscription_plans (name, stripe_price_id, price_per_seat, billing_interval, max_seats, features, limits) VALUES
('Starter', 'price_starter', 29.00, 'month', 5, '{"crm": true, "basic_ai": true}', '{"ai_tokens": 10000, "storage_gb": 10}'),
('Professional', 'price_professional', 79.00, 'month', 25, '{"crm": true, "erp": true, "full_ai": true}', '{"ai_tokens": 50000, "storage_gb": 100}'),
('Enterprise', 'price_enterprise', 199.00, 'month', NULL, '{"crm": true, "erp": true, "accounting": true, "full_ai": true, "priority_support": true}', '{"ai_tokens": 200000, "storage_gb": 500}')
ON CONFLICT DO NOTHING;

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_usage_records_aggregation ON usage_records(tenant_id, service, recorded_at);
CREATE INDEX IF NOT EXISTS idx_payment_history_lookup ON payment_history(tenant_id, status, paid_at);
CREATE INDEX IF NOT EXISTS idx_dunning_active ON dunning_sequences(tenant_id, status) WHERE status = 'active';

-- Add foreign key constraints if not exists
ALTER TABLE tenant_subscriptions ADD COLUMN IF NOT EXISTS seat_count INT DEFAULT 1;
ALTER TABLE tenant_subscriptions ADD COLUMN IF NOT EXISTS max_seats INT NULL;
ALTER TABLE tenant_subscriptions ADD COLUMN IF NOT EXISTS price_per_seat DECIMAL(10,2) DEFAULT 0;
ALTER TABLE tenant_subscriptions ADD COLUMN IF NOT EXISTS stripe_subscription_item_id VARCHAR(255) NULL;

-- Comments for documentation
COMMENT ON TABLE tenant_trials IS 'Tracks 14-day free trials for new tenants';
COMMENT ON TABLE usage_records IS 'Stores metered usage data for AI, storage, and API calls';
COMMENT ON TABLE subscription_items IS 'Maps Stripe subscription items to tenant metrics';
COMMENT ON TABLE payment_history IS 'Complete history of all payment attempts';
COMMENT ON TABLE dunning_sequences IS 'Tracks failed payment recovery attempts';
COMMENT ON TABLE seat_changes IS 'Audit log of seat count changes';
COMMENT ON TABLE invoices IS 'Local copy of Stripe invoices for quick access';
COMMENT ON TABLE billing_events IS 'Log of all Stripe webhook events';
COMMENT ON TABLE subscription_plans IS 'Available subscription plans and pricing';
