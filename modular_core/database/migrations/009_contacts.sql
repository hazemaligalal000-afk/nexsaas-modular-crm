-- Migration 009: Contacts
-- Requirements: 6.1

-- ============================================================
-- contacts table
-- ============================================================
CREATE TABLE IF NOT EXISTS contacts (
    id           BIGSERIAL PRIMARY KEY,
    company_code VARCHAR(2)   NOT NULL DEFAULT '01',
    tenant_id    UUID         NOT NULL,
    first_name   VARCHAR(100),
    last_name    VARCHAR(100),
    email        VARCHAR(255),
    phone        VARCHAR(50),
    company_name VARCHAR(255),
    job_title    VARCHAR(100),
    custom_fields JSONB       NOT NULL DEFAULT '{}',
    search_vector TSVECTOR,
    created_by   BIGINT,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at   TIMESTAMPTZ
);

-- Full-text search index (GIN)
CREATE INDEX IF NOT EXISTS idx_contacts_search
    ON contacts USING GIN(search_vector);

-- Tenant + company composite index (active records only)
CREATE INDEX IF NOT EXISTS idx_contacts_tenant
    ON contacts(tenant_id, company_code)
    WHERE deleted_at IS NULL;

-- Unique email per tenant (active records only, NULLs excluded)
CREATE UNIQUE INDEX IF NOT EXISTS idx_contacts_email_tenant
    ON contacts(tenant_id, email)
    WHERE deleted_at IS NULL AND email IS NOT NULL;

-- ============================================================
-- contact_custom_fields — per-tenant field schema definitions
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_custom_fields (
    id           BIGSERIAL PRIMARY KEY,
    company_code VARCHAR(2)  NOT NULL DEFAULT '01',
    tenant_id    UUID        NOT NULL,
    field_name   VARCHAR(100) NOT NULL,
    field_type   VARCHAR(50)  NOT NULL,   -- text|number|date|boolean|select
    is_required  BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at   TIMESTAMPTZ
);

-- ============================================================
-- Trigger: auto-update search_vector on INSERT / UPDATE
-- ============================================================
CREATE OR REPLACE FUNCTION contacts_search_vector_update()
RETURNS TRIGGER AS $$
BEGIN
    NEW.search_vector := to_tsvector(
        'english',
        coalesce(NEW.first_name,   '') || ' ' ||
        coalesce(NEW.last_name,    '') || ' ' ||
        coalesce(NEW.email,        '') || ' ' ||
        coalesce(NEW.phone,        '') || ' ' ||
        coalesce(NEW.company_name, '')
    );
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_contacts_search_vector ON contacts;
CREATE TRIGGER trg_contacts_search_vector
    BEFORE INSERT OR UPDATE ON contacts
    FOR EACH ROW EXECUTE FUNCTION contacts_search_vector_update();
