-- Migration 022: Calendar Integrations (Google Calendar & Microsoft Outlook OAuth 2.0)
-- Requirements: 16.1, 16.2, 16.3, 16.4, 16.5

-- calendar_connections: per-user OAuth 2.0 calendar connections
CREATE TABLE IF NOT EXISTS calendar_connections (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID         NOT NULL,
    company_code        VARCHAR(2)   NOT NULL DEFAULT '01',
    user_id             BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider            VARCHAR(20)  NOT NULL,   -- 'google' | 'outlook'
    access_token        TEXT         NOT NULL,   -- AES-256-CBC encrypted
    refresh_token       TEXT         NOT NULL,   -- AES-256-CBC encrypted
    token_expires_at    TIMESTAMPTZ  NOT NULL,
    calendar_id         VARCHAR(255) NOT NULL DEFAULT 'primary',
    sync_token          TEXT,                    -- Google incremental sync token
    delta_link          TEXT,                    -- Microsoft Graph delta link
    is_active           BOOLEAN      NOT NULL DEFAULT TRUE,
    created_by          BIGINT       REFERENCES users(id),
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,

    CONSTRAINT chk_calendar_provider CHECK (provider IN ('google', 'outlook')),
    CONSTRAINT uq_calendar_user_provider UNIQUE (tenant_id, user_id, provider)
);

CREATE INDEX IF NOT EXISTS idx_calendar_connections_tenant
    ON calendar_connections(tenant_id, user_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_calendar_connections_active
    ON calendar_connections(is_active, user_id)
    WHERE deleted_at IS NULL AND is_active = TRUE;

-- calendar_sync_log: tracks sync events for debugging
CREATE TABLE IF NOT EXISTS calendar_sync_log (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID         NOT NULL,
    company_code        VARCHAR(2)   NOT NULL DEFAULT '01',
    user_id             BIGINT       NOT NULL,
    activity_id         BIGINT       REFERENCES activities(id) ON DELETE SET NULL,
    provider            VARCHAR(20)  NOT NULL,
    external_event_id   VARCHAR(512),
    sync_direction      VARCHAR(10)  NOT NULL,   -- 'push' | 'pull'
    status              VARCHAR(10)  NOT NULL,   -- 'success' | 'failed'
    error_message       TEXT,
    synced_at           TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

    CONSTRAINT chk_sync_direction CHECK (sync_direction IN ('push', 'pull')),
    CONSTRAINT chk_sync_status    CHECK (status         IN ('success', 'failed'))
);

CREATE INDEX IF NOT EXISTS idx_calendar_sync_log_tenant
    ON calendar_sync_log(tenant_id, user_id, synced_at DESC);

CREATE INDEX IF NOT EXISTS idx_calendar_sync_log_activity
    ON calendar_sync_log(activity_id)
    WHERE activity_id IS NOT NULL;

-- scheduling_links: public booking URLs
CREATE TABLE IF NOT EXISTS scheduling_links (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID         NOT NULL,
    company_code        VARCHAR(2)   NOT NULL DEFAULT '01',
    user_id             BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    slug                VARCHAR(100) NOT NULL UNIQUE,
    title               VARCHAR(255) NOT NULL,
    duration_minutes    INT          NOT NULL DEFAULT 30,
    buffer_minutes      INT          NOT NULL DEFAULT 0,
    availability_rules  JSONB        NOT NULL DEFAULT '{"weekdays":[1,2,3,4,5],"start_time":"09:00","end_time":"17:00","timezone":"UTC"}',
    is_active           BOOLEAN      NOT NULL DEFAULT TRUE,
    created_by          BIGINT       REFERENCES users(id),
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,

    CONSTRAINT chk_scheduling_duration CHECK (duration_minutes > 0),
    CONSTRAINT chk_scheduling_buffer   CHECK (buffer_minutes >= 0)
);

CREATE INDEX IF NOT EXISTS idx_scheduling_links_slug
    ON scheduling_links(slug)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_scheduling_links_user
    ON scheduling_links(tenant_id, user_id)
    WHERE deleted_at IS NULL;

-- scheduling_bookings: bookings made via scheduling links
CREATE TABLE IF NOT EXISTS scheduling_bookings (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID         NOT NULL,
    company_code        VARCHAR(2)   NOT NULL DEFAULT '01',
    scheduling_link_id  BIGINT       NOT NULL REFERENCES scheduling_links(id) ON DELETE CASCADE,
    booker_name         VARCHAR(255) NOT NULL,
    booker_email        VARCHAR(255) NOT NULL,
    start_at            TIMESTAMPTZ  NOT NULL,
    end_at              TIMESTAMPTZ  NOT NULL,
    notes               TEXT,
    activity_id         BIGINT       REFERENCES activities(id) ON DELETE SET NULL,
    status              VARCHAR(20)  NOT NULL DEFAULT 'confirmed',  -- pending|confirmed|cancelled
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

    CONSTRAINT chk_booking_status CHECK (status IN ('pending', 'confirmed', 'cancelled')),
    CONSTRAINT chk_booking_times  CHECK (end_at > start_at)
);

CREATE INDEX IF NOT EXISTS idx_scheduling_bookings_link
    ON scheduling_bookings(scheduling_link_id, start_at)
    WHERE status != 'cancelled';

CREATE INDEX IF NOT EXISTS idx_scheduling_bookings_activity
    ON scheduling_bookings(activity_id)
    WHERE activity_id IS NOT NULL;

-- Extend activities table with external calendar fields
ALTER TABLE activities
    ADD COLUMN IF NOT EXISTS external_event_id          VARCHAR(512),
    ADD COLUMN IF NOT EXISTS external_calendar_provider VARCHAR(20);

CREATE INDEX IF NOT EXISTS idx_activities_external_event
    ON activities(external_event_id)
    WHERE deleted_at IS NULL AND external_event_id IS NOT NULL;
