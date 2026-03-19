-- Migration 003: Create tenants table
-- Requirements: 1.1, 1.2, 5.1
--
-- Note: tenants is the root table; it does NOT carry company_code or created_by
-- (no users exist yet at tenant creation time). All other tables reference tenant_id.

CREATE TABLE IF NOT EXISTS tenants (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    name                VARCHAR(255) NOT NULL,
    plan                VARCHAR(50)  NOT NULL,
    stripe_customer_id  VARCHAR(100),
    seat_limit          INT          NOT NULL DEFAULT 5,
    is_active           BOOLEAN      NOT NULL DEFAULT true,
    e_invoice_active    BOOLEAN      NOT NULL DEFAULT false,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- Index for fast tenant lookup by UUID (used on every authenticated request)
CREATE INDEX IF NOT EXISTS idx_tenants_tenant_id ON tenants(tenant_id);
CREATE INDEX IF NOT EXISTS idx_tenants_active    ON tenants(is_active) WHERE is_active = true;

-- Auto-update updated_at on row change
CREATE OR REPLACE FUNCTION fn_set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_tenants_updated_at
    BEFORE UPDATE ON tenants
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();
