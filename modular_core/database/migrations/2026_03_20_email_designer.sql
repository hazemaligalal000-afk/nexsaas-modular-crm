-- ─────────────────────────────────────────────────────────────
-- NexSaaS Email Designer Database Schema
-- ─────────────────────────────────────────────────────────────

-- 1. Brand Settings per Company
CREATE TABLE IF NOT EXISTS email_brand_settings (
    id                  BIGSERIAL       PRIMARY KEY,
    tenant_id           UUID            NOT NULL,
    company_code        VARCHAR(2)      NOT NULL,  -- 01 Globalize, 02 Digitalize...

    company_name_en     VARCHAR(200),
    company_name_ar     VARCHAR(200),
    logo_url            TEXT,
    favicon_url         TEXT,

    color_primary       VARCHAR(7)      DEFAULT '#1E3A5F',
    color_secondary     VARCHAR(7)      DEFAULT '#2E86C1',
    color_accent        VARCHAR(7)      DEFAULT '#F39C12',
    color_bg            VARCHAR(7)      DEFAULT '#F8F9FA',
    color_text          VARCHAR(7)      DEFAULT '#2C3E50',
    color_text_muted    VARCHAR(7)      DEFAULT '#7F8C8D',
    color_button        VARCHAR(7)      DEFAULT '#1E3A5F',
    color_button_text   VARCHAR(7)      DEFAULT '#FFFFFF',
    color_footer_bg     VARCHAR(7)      DEFAULT '#2C3E50',
    color_footer_text   VARCHAR(7)      DEFAULT '#ECF0F1',

    font_family         VARCHAR(100)    DEFAULT 'Inter, Arial, sans-serif',
    font_size_base      INT             DEFAULT 16,

    sender_name_en      VARCHAR(200),
    sender_email        VARCHAR(200),
    reply_to_email      VARCHAR(200),
    sender_address_en   TEXT,
    website_url         VARCHAR(500),

    smtp_host           VARCHAR(200),
    smtp_port           INT             DEFAULT 587,
    smtp_username       VARCHAR(200),
    smtp_password_enc   TEXT,
    smtp_encryption     VARCHAR(10)     DEFAULT 'tls',
    smtp_provider       VARCHAR(30)     DEFAULT 'smtp',

    created_at          TIMESTAMP       DEFAULT NOW(),
    updated_at          TIMESTAMP       DEFAULT NOW(),
    UNIQUE (tenant_id, company_code)
);

-- 2. Template Library
CREATE TABLE IF NOT EXISTS email_templates (
    id                  BIGSERIAL       PRIMARY KEY,
    tenant_id           UUID            NOT NULL,
    company_code        VARCHAR(2),     -- NULL = shared

    name                VARCHAR(300)    NOT NULL,
    category            VARCHAR(50)     NOT NULL, -- transactional, marketing, invoice
    subject_en          TEXT            NOT NULL,
    preheader_en        VARCHAR(500),

    json_design         JSONB           NOT NULL,   -- The builder state
    mjml_source         TEXT,
    html_compiled       TEXT,

    is_active           BOOLEAN         DEFAULT TRUE,
    is_system           BOOLEAN         DEFAULT FALSE,
    
    send_count          INT             DEFAULT 0,
    open_rate           DECIMAL(5,2)    DEFAULT 0,
    
    created_at          TIMESTAMP       DEFAULT NOW(),
    updated_at          TIMESTAMP       DEFAULT NOW(),
    deleted_at          TIMESTAMP
);

-- 3. Email Send Log
CREATE TABLE IF NOT EXISTS email_sends (
    id                  BIGSERIAL       PRIMARY KEY,
    tenant_id           UUID            NOT NULL,
    company_code        VARCHAR(2)      NOT NULL,
    template_id         BIGINT          REFERENCES email_templates(id),
    contact_id          BIGINT,
    
    to_email            VARCHAR(200)    NOT NULL,
    subject             TEXT            NOT NULL,
    html_body           TEXT            NOT NULL,
    
    status              VARCHAR(20)     DEFAULT 'queued',
    message_id          VARCHAR(300),
    
    queued_at           TIMESTAMP       DEFAULT NOW(),
    sent_at             TIMESTAMP,
    opened_at           TIMESTAMP,
    clicked_at          TIMESTAMP,
    
    open_count          INT             DEFAULT 0,
    click_count         INT             DEFAULT 0,
    created_at          TIMESTAMP       DEFAULT NOW()
);

-- 4. Click Tracking
CREATE TABLE IF NOT EXISTS email_click_events (
    id              BIGSERIAL   PRIMARY KEY,
    send_id         BIGINT      REFERENCES email_sends(id),
    url_original    TEXT        NOT NULL,
    click_count     INT         DEFAULT 0,
    first_clicked   TIMESTAMP,
    last_clicked    TIMESTAMP
);
