-- ─────────────────────────────────────────────────────────────
-- 028 — CTI Call Log, Screen Pop Log, Disposition Codes
-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS call_log (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL,
    platform            VARCHAR(50) NOT NULL,
    call_sid            VARCHAR(120) UNIQUE NOT NULL,
    parent_call_sid     VARCHAR(120),
    direction           VARCHAR(10) NOT NULL CHECK (direction IN ('inbound','outbound')),
    from_number         VARCHAR(30) NOT NULL,
    to_number           VARCHAR(30) NOT NULL,
    did_number          VARCHAR(30),
    contact_id          BIGINT,
    company_id          BIGINT,
    deal_id             BIGINT,
    ticket_id           BIGINT,
    agent_id            VARCHAR(20) NOT NULL,
    queue_name          VARCHAR(100),
    ivr_path            TEXT,
    initiated_at        TIMESTAMP NOT NULL DEFAULT NOW(),
    answered_at         TIMESTAMP,
    ended_at            TIMESTAMP,
    duration_sec        INT GENERATED ALWAYS AS (
                            EXTRACT(EPOCH FROM (ended_at - answered_at))::INT
                        ) STORED,
    ring_duration_sec   INT GENERATED ALWAYS AS (
                            EXTRACT(EPOCH FROM (answered_at - initiated_at))::INT
                        ) STORED,
    status              VARCHAR(30) NOT NULL DEFAULT 'initiated'
                        CHECK (status IN (
                            'initiated','ringing','answered','missed',
                            'voicemail','transferred','conference','failed','completed'
                        )),
    disposition_code    VARCHAR(50),
    disposition_notes   TEXT,
    recording_url       TEXT,
    recording_s3_key    TEXT,
    recording_duration  INT,
    recording_status    VARCHAR(20) DEFAULT 'pending'
                        CHECK (recording_status IN (
                            'pending','processing','available','failed','deleted'
                        )),
    transcript_text     TEXT,
    transcript_ar       TEXT,
    transcript_en       TEXT,
    ai_summary          TEXT,
    ai_action_items     JSONB,
    ai_sentiment        DECIMAL(4,3),
    ai_intent           VARCHAR(50),
    ai_keywords         JSONB,
    transcript_status   VARCHAR(20) DEFAULT 'pending'
                        CHECK (transcript_status IN (
                            'pending','processing','completed','failed'
                        )),
    raw_platform_data   JSONB,
    created_at          TIMESTAMP DEFAULT NOW(),
    updated_at          TIMESTAMP DEFAULT NOW(),
    deleted_at          TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_call_log_tenant_date ON call_log(tenant_id, initiated_at DESC);
CREATE INDEX IF NOT EXISTS idx_call_log_contact     ON call_log(contact_id);
CREATE INDEX IF NOT EXISTS idx_call_log_agent       ON call_log(agent_id, initiated_at DESC);
CREATE INDEX IF NOT EXISTS idx_call_log_status      ON call_log(status);
CREATE INDEX IF NOT EXISTS idx_call_log_from_to     ON call_log(from_number, to_number);
CREATE INDEX IF NOT EXISTS idx_call_log_platform    ON call_log(platform, call_sid);

CREATE TABLE IF NOT EXISTS screen_pop_log (
    id              BIGSERIAL PRIMARY KEY,
    tenant_id       UUID NOT NULL,
    call_sid        VARCHAR(120) NOT NULL,
    agent_id        VARCHAR(20),
    phone_number    VARCHAR(30),
    lookup_strategy VARCHAR(30),
    matched_contact BIGINT,
    pop_delivered   BOOLEAN DEFAULT FALSE,
    pop_latency_ms  INT,
    created_at      TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS disposition_codes (
    id              BIGSERIAL PRIMARY KEY,
    tenant_id       UUID NOT NULL,
    company_code    VARCHAR(2),
    code            VARCHAR(50) NOT NULL,
    label_en        VARCHAR(200),
    label_ar        VARCHAR(200),
    category        VARCHAR(50),
    is_active       SMALLINT DEFAULT 1,
    UNIQUE(tenant_id, code)
);

-- Seed default disposition codes (idempotent)
INSERT INTO disposition_codes (tenant_id, code, label_en, label_ar, category)
SELECT gen_random_uuid(),'SALE_CLOSED','Sale Closed','بيع مكتمل','sale'
WHERE NOT EXISTS (SELECT 1 FROM disposition_codes WHERE code='SALE_CLOSED')
UNION ALL
SELECT gen_random_uuid(),'CALLBACK_REQUESTED','Callback Requested','طلب معاودة الاتصال','callback'
WHERE NOT EXISTS (SELECT 1 FROM disposition_codes WHERE code='CALLBACK_REQUESTED')
UNION ALL
SELECT gen_random_uuid(),'NO_ANSWER','No Answer','لا توجد إجابة','no_answer'
WHERE NOT EXISTS (SELECT 1 FROM disposition_codes WHERE code='NO_ANSWER')
UNION ALL
SELECT gen_random_uuid(),'VOICEMAIL_LEFT','Voicemail Left','رسالة صوتية','no_answer'
WHERE NOT EXISTS (SELECT 1 FROM disposition_codes WHERE code='VOICEMAIL_LEFT')
UNION ALL
SELECT gen_random_uuid(),'ESCALATED','Escalated to Manager','تصعيد للمدير','escalated'
WHERE NOT EXISTS (SELECT 1 FROM disposition_codes WHERE code='ESCALATED')
UNION ALL
SELECT gen_random_uuid(),'ISSUE_RESOLVED','Issue Resolved','تم حل المشكلة','support'
WHERE NOT EXISTS (SELECT 1 FROM disposition_codes WHERE code='ISSUE_RESOLVED')
UNION ALL
SELECT gen_random_uuid(),'WRONG_NUMBER','Wrong Number','رقم خاطئ','no_answer'
WHERE NOT EXISTS (SELECT 1 FROM disposition_codes WHERE code='WRONG_NUMBER');
