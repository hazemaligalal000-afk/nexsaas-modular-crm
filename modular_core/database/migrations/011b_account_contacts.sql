-- Migration: 011b_account_contacts.sql
-- Join table linking multiple contacts to an account.
-- Requirements: 9.2

CREATE TABLE IF NOT EXISTS account_contacts (
    id           BIGSERIAL    PRIMARY KEY,
    tenant_id    UUID         NOT NULL,
    company_code VARCHAR(2)   NOT NULL DEFAULT '01',
    account_id   BIGINT       NOT NULL REFERENCES accounts(id),
    contact_id   BIGINT       NOT NULL REFERENCES contacts(id),
    created_by   BIGINT,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    UNIQUE(tenant_id, account_id, contact_id)
);

CREATE INDEX IF NOT EXISTS idx_account_contacts_account
    ON account_contacts(tenant_id, account_id);

CREATE INDEX IF NOT EXISTS idx_account_contacts_contact
    ON account_contacts(tenant_id, contact_id);
