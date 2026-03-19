-- Migration 014: Canned Responses
-- Pre-written reply templates selectable by agents during a conversation.
-- Requirements: 12.8

CREATE TABLE canned_responses (
    id           BIGSERIAL PRIMARY KEY,
    tenant_id    UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL DEFAULT '01',
    shortcut     VARCHAR(50) NOT NULL,          -- e.g. "greeting", "thanks"
    title        VARCHAR(255) NOT NULL,
    body         TEXT NOT NULL,
    created_by   BIGINT REFERENCES users(id),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at   TIMESTAMPTZ,
    UNIQUE(tenant_id, company_code, shortcut)
);

CREATE INDEX idx_canned_tenant ON canned_responses(tenant_id, company_code) WHERE deleted_at IS NULL;
