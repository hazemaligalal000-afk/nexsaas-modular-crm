-- Migration 008: SSO providers and role mappings
-- Requirements: 34.1, 34.2, 34.3, 34.4, 34.5
--
-- Creates sso_providers (per-tenant IdP config), sso_role_mappings
-- (IdP group → RBAC role), and extends users with SSO identity columns.

-- SSO provider configuration per tenant
CREATE TABLE IF NOT EXISTS sso_providers (
    id            BIGSERIAL PRIMARY KEY,
    company_code  VARCHAR(2)   NOT NULL DEFAULT '01',
    tenant_id     UUID         NOT NULL REFERENCES tenants(tenant_id) ON DELETE RESTRICT,
    provider_name VARCHAR(50)  NOT NULL,   -- 'saml' | 'google' | 'microsoft' | 'github'
    config        JSONB        NOT NULL DEFAULT '{}',
    -- SAML config keys: entity_id, sso_url, slo_url, x509cert, sp_entity_id, sp_acs_url
    -- OAuth2 config keys: client_id, client_secret, redirect_uri, scopes[]
    is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at    TIMESTAMPTZ,

    CONSTRAINT uq_sso_providers_tenant_provider UNIQUE (tenant_id, provider_name)
);

CREATE INDEX IF NOT EXISTS idx_sso_providers_tenant
    ON sso_providers(tenant_id)
    WHERE deleted_at IS NULL;

-- IdP group → RBAC role mappings per tenant
CREATE TABLE IF NOT EXISTS sso_role_mappings (
    id            BIGSERIAL PRIMARY KEY,
    company_code  VARCHAR(2)   NOT NULL DEFAULT '01',
    tenant_id     UUID         NOT NULL REFERENCES tenants(tenant_id) ON DELETE RESTRICT,
    provider_name VARCHAR(50)  NOT NULL,
    idp_group     VARCHAR(255) NOT NULL,   -- group name as returned by IdP
    rbac_role     VARCHAR(100) NOT NULL,   -- platform_role value, e.g. 'Admin', 'Manager'
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at    TIMESTAMPTZ,

    CONSTRAINT uq_sso_role_mappings UNIQUE (tenant_id, provider_name, idp_group)
);

CREATE INDEX IF NOT EXISTS idx_sso_role_mappings_tenant
    ON sso_role_mappings(tenant_id, provider_name)
    WHERE deleted_at IS NULL;

-- Extend users table with SSO identity columns
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS sso_provider VARCHAR(50),
    ADD COLUMN IF NOT EXISTS sso_subject  VARCHAR(255);

-- Index for fast SSO subject lookup (used during callback to find existing user)
CREATE INDEX IF NOT EXISTS idx_users_sso_subject
    ON users(tenant_id, sso_provider, sso_subject)
    WHERE deleted_at IS NULL AND sso_subject IS NOT NULL;
