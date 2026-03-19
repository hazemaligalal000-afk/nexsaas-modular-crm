-- ─────────────────────────────────────────────────────────────
-- 029 — Lead Attribution, Ad Conversion Events, UTM Sessions
-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS lead_attributions (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL,
    contact_id          BIGINT,
    platform            VARCHAR(30) NOT NULL,
    -- Click IDs
    gclid               VARCHAR(200),
    fbclid              VARCHAR(200),
    ttclid              VARCHAR(200),
    sccid               VARCHAR(200),
    li_fat_id           VARCHAR(200),
    twclid              VARCHAR(200),
    msclkid             VARCHAR(200),
    wbraid              VARCHAR(200),
    gbraid              VARCHAR(200),
    -- UTM
    utm_source          VARCHAR(200),
    utm_medium          VARCHAR(200),
    utm_campaign        VARCHAR(500),
    utm_content         VARCHAR(500),
    utm_term            VARCHAR(500),
    utm_id              VARCHAR(200),
    -- Campaign detail
    ad_platform_id      VARCHAR(200),
    ad_set_id           VARCHAR(200),
    ad_id               VARCHAR(200),
    campaign_name       VARCHAR(500),
    ad_set_name         VARCHAR(500),
    ad_name             VARCHAR(500),
    creative_id         VARCHAR(200),
    placement           VARCHAR(200),
    conversion_type     VARCHAR(50),
    touch_position      VARCHAR(20) DEFAULT 'last',
    -- Page data
    landing_page_url    TEXT,
    referrer_url        TEXT,
    ip_address          INET,
    user_agent          TEXT,
    device_type         VARCHAR(20),
    browser             VARCHAR(50),
    os                  VARCHAR(50),
    country_code        VARCHAR(5),
    city                VARCHAR(100),
    -- Platform lead data
    platform_lead_id    VARCHAR(200),
    platform_form_id    VARCHAR(200),
    platform_ad_account VARCHAR(200),
    -- Meta cookies
    fbc                 VARCHAR(500),
    fbp                 VARCHAR(500),
    -- CAPI status
    capi_sent_meta      BOOLEAN DEFAULT FALSE,
    capi_sent_google    BOOLEAN DEFAULT FALSE,
    capi_sent_tiktok    BOOLEAN DEFAULT FALSE,
    capi_sent_snapchat  BOOLEAN DEFAULT FALSE,
    capi_sent_linkedin  BOOLEAN DEFAULT FALSE,
    capi_sent_at        TIMESTAMP,
    capi_match_score    DECIMAL(3,1),
    raw_payload         JSONB,
    created_at          TIMESTAMP DEFAULT NOW(),
    updated_at          TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_attr_tenant_contact ON lead_attributions(tenant_id, contact_id);
CREATE INDEX IF NOT EXISTS idx_attr_platform       ON lead_attributions(platform, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_attr_campaign       ON lead_attributions(tenant_id, utm_campaign);
CREATE INDEX IF NOT EXISTS idx_attr_gclid          ON lead_attributions(gclid) WHERE gclid IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_attr_fbclid         ON lead_attributions(fbclid) WHERE fbclid IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_attr_ttclid         ON lead_attributions(ttclid) WHERE ttclid IS NOT NULL;

CREATE TABLE IF NOT EXISTS ad_conversion_events (
    id              BIGSERIAL PRIMARY KEY,
    tenant_id       UUID NOT NULL,
    attribution_id  BIGINT REFERENCES lead_attributions(id),
    contact_id      BIGINT,
    platform        VARCHAR(30) NOT NULL,
    event_name      VARCHAR(100) NOT NULL,
    event_time      TIMESTAMP NOT NULL DEFAULT NOW(),
    event_id        VARCHAR(200),
    value           DECIMAL(15,2),
    currency        VARCHAR(3) DEFAULT 'EGP',
    status          VARCHAR(20) DEFAULT 'pending'
                    CHECK (status IN ('pending','sent','failed','duplicate')),
    response_body   JSONB,
    retry_count     INT DEFAULT 0,
    sent_at         TIMESTAMP,
    created_at      TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS utm_sessions (
    id              BIGSERIAL PRIMARY KEY,
    tenant_id       UUID NOT NULL,
    session_id      VARCHAR(100) UNIQUE NOT NULL,
    contact_id      BIGINT,
    gclid VARCHAR(200), fbclid VARCHAR(200), ttclid VARCHAR(200),
    sccid VARCHAR(200), li_fat_id VARCHAR(200), twclid VARCHAR(200),
    utm_source VARCHAR(200), utm_medium VARCHAR(200),
    utm_campaign VARCHAR(500), utm_content VARCHAR(500), utm_term VARCHAR(500),
    landing_page_url TEXT,
    ip_address INET, user_agent TEXT, device_type VARCHAR(20),
    converted       BOOLEAN DEFAULT FALSE,
    converted_at    TIMESTAMP,
    created_at      TIMESTAMP DEFAULT NOW(),
    expires_at      TIMESTAMP DEFAULT NOW() + INTERVAL '90 days'
);
