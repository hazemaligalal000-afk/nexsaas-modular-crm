-- Migration 020: Email Integrations (Gmail & Microsoft 365 OAuth 2.0)
-- Requirements: 13.1, 13.2, 13.3, 13.4

-- connected_mailboxes: per-user OAuth 2.0 mailbox connections
CREATE TABLE IF NOT EXISTS connected_mailboxes (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID         NOT NULL,
    company_code        VARCHAR(2)   NOT NULL DEFAULT '01',
    user_id             BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider            VARCHAR(20)  NOT NULL,   -- 'gmail' | 'microsoft365'
    email_address       VARCHAR(255) NOT NULL,
    access_token        TEXT         NOT NULL,   -- AES-256-CBC encrypted
    refresh_token       TEXT         NOT NULL,   -- AES-256-CBC encrypted
    token_expires_at    TIMESTAMPTZ  NOT NULL,
    last_sync_at        TIMESTAMPTZ,
    sync_status         VARCHAR(20)  NOT NULL DEFAULT 'active',  -- active|error|disconnected
    last_error          TEXT,
    created_by          BIGINT       REFERENCES users(id),
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,

    CONSTRAINT chk_mailbox_provider    CHECK (provider     IN ('gmail', 'microsoft365')),
    CONSTRAINT chk_mailbox_sync_status CHECK (sync_status  IN ('active', 'error', 'disconnected')),
    CONSTRAINT uq_mailbox_user_email   UNIQUE (tenant_id, user_id, email_address)
);

CREATE INDEX IF NOT EXISTS idx_connected_mailboxes_tenant
    ON connected_mailboxes(tenant_id, user_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_connected_mailboxes_sync
    ON connected_mailboxes(sync_status, last_sync_at)
    WHERE deleted_at IS NULL AND sync_status = 'active';

-- email_tracking_events: open and click events for outbound emails
CREATE TABLE IF NOT EXISTS email_tracking_events (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID         NOT NULL,
    company_code        VARCHAR(2)   NOT NULL DEFAULT '01',
    inbox_message_id    BIGINT       NOT NULL REFERENCES inbox_messages(id) ON DELETE CASCADE,
    event_type          VARCHAR(10)  NOT NULL,   -- 'open' | 'click'
    tracking_token      UUID         NOT NULL,
    link_url            TEXT,                    -- original URL (clicks only)
    link_destination    TEXT,                    -- resolved destination (clicks only)
    ip_address          VARCHAR(45),
    user_agent          TEXT,
    occurred_at         TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    created_by          BIGINT       REFERENCES users(id),
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,

    CONSTRAINT chk_tracking_event_type CHECK (event_type IN ('open', 'click'))
);

CREATE INDEX IF NOT EXISTS idx_email_tracking_token
    ON email_tracking_events(tracking_token)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_email_tracking_message
    ON email_tracking_events(inbox_message_id)
    WHERE deleted_at IS NULL;

-- Extend inbox_messages with tracking_token and external_message_id
ALTER TABLE inbox_messages
    ADD COLUMN IF NOT EXISTS tracking_token      UUID,
    ADD COLUMN IF NOT EXISTS external_message_id VARCHAR(512),
    ADD COLUMN IF NOT EXISTS opened_at           TIMESTAMPTZ;

CREATE INDEX IF NOT EXISTS idx_inbox_msg_tracking_token
    ON inbox_messages(tracking_token)
    WHERE deleted_at IS NULL AND tracking_token IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_inbox_msg_external_id
    ON inbox_messages(tenant_id, external_message_id)
    WHERE deleted_at IS NULL AND external_message_id IS NOT NULL;
