-- Migration 013: Omnichannel Inbox
-- Creates inbox_conversations and inbox_messages tables
-- Requirements: 12.1

-- inbox_conversations: one thread per contact/lead per channel
CREATE TABLE inbox_conversations (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    channel             VARCHAR(20) NOT NULL,          -- email|sms|whatsapp|chat|voip
    contact_id          BIGINT REFERENCES contacts(id),
    lead_id             BIGINT REFERENCES leads(id),
    assigned_agent_id   BIGINT REFERENCES users(id),
    status              VARCHAR(20) NOT NULL DEFAULT 'open',  -- open|pending|resolved|closed
    first_response_at   TIMESTAMPTZ,
    resolved_at         TIMESTAMPTZ,
    created_by          BIGINT REFERENCES users(id),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    CONSTRAINT chk_inbox_conv_channel CHECK (channel IN ('email', 'sms', 'whatsapp', 'chat', 'voip')),
    CONSTRAINT chk_inbox_conv_status  CHECK (status  IN ('open', 'pending', 'resolved', 'closed')),
    CONSTRAINT chk_inbox_conv_linked  CHECK (contact_id IS NOT NULL OR lead_id IS NOT NULL)
);

CREATE INDEX idx_inbox_conv_tenant     ON inbox_conversations(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_inbox_conv_contact    ON inbox_conversations(tenant_id, contact_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_inbox_conv_lead       ON inbox_conversations(tenant_id, lead_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_inbox_conv_agent      ON inbox_conversations(tenant_id, assigned_agent_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_inbox_conv_status     ON inbox_conversations(tenant_id, status) WHERE deleted_at IS NULL;

-- inbox_messages: individual messages within a conversation
CREATE TABLE inbox_messages (
    id                      BIGSERIAL PRIMARY KEY,
    tenant_id               UUID NOT NULL,
    company_code            VARCHAR(2) NOT NULL DEFAULT '01',
    conversation_id         BIGINT NOT NULL REFERENCES inbox_conversations(id),
    direction               VARCHAR(10) NOT NULL,          -- inbound|outbound
    body                    TEXT NOT NULL,
    sentiment               VARCHAR(10),                   -- positive|neutral|negative
    sentiment_confidence    DECIMAL(4,3) CHECK(sentiment_confidence BETWEEN 0 AND 1),
    created_by              BIGINT REFERENCES users(id),
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at              TIMESTAMPTZ,
    CONSTRAINT chk_inbox_msg_direction  CHECK (direction IN ('inbound', 'outbound')),
    CONSTRAINT chk_inbox_msg_sentiment  CHECK (sentiment IN ('positive', 'neutral', 'negative') OR sentiment IS NULL)
);

CREATE INDEX idx_inbox_msg_conversation ON inbox_messages(conversation_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_inbox_msg_tenant       ON inbox_messages(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_inbox_msg_created_at   ON inbox_messages(conversation_id, created_at) WHERE deleted_at IS NULL;
