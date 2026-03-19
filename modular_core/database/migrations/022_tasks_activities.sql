-- Migration 022: Tasks and Activities
-- Creates tasks and activities tables
-- Requirements: 15.1, 15.2

-- ============================================================
-- tasks
-- ============================================================
CREATE TABLE IF NOT EXISTS tasks (
    id              BIGSERIAL PRIMARY KEY,
    company_code    VARCHAR(2)   NOT NULL DEFAULT '01',
    tenant_id       UUID         NOT NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT,
    due_date        TIMESTAMPTZ,
    priority        VARCHAR(20)  NOT NULL DEFAULT 'medium',
    status          VARCHAR(20)  NOT NULL DEFAULT 'open',
    assigned_user_id BIGINT      REFERENCES users(id),
    linked_type     VARCHAR(20),                          -- contact|lead|deal|account
    linked_id       BIGINT,
    reminder_sent_at TIMESTAMPTZ,
    created_by      BIGINT       REFERENCES users(id),
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ,
    CONSTRAINT chk_task_priority CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    CONSTRAINT chk_task_status   CHECK (status   IN ('open', 'in_progress', 'completed', 'cancelled')),
    CONSTRAINT chk_task_linked_type CHECK (linked_type IN ('contact', 'lead', 'deal', 'account') OR linked_type IS NULL)
);

CREATE INDEX IF NOT EXISTS idx_tasks_tenant
    ON tasks(tenant_id, company_code)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_tasks_assigned_user
    ON tasks(tenant_id, assigned_user_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_tasks_due_date
    ON tasks(tenant_id, due_date)
    WHERE deleted_at IS NULL AND status NOT IN ('completed', 'cancelled');

CREATE INDEX IF NOT EXISTS idx_tasks_linked
    ON tasks(linked_type, linked_id)
    WHERE deleted_at IS NULL;

-- ============================================================
-- activities
-- ============================================================
CREATE TABLE IF NOT EXISTS activities (
    id               BIGSERIAL PRIMARY KEY,
    company_code     VARCHAR(2)   NOT NULL DEFAULT '01',
    tenant_id        UUID         NOT NULL,
    type             VARCHAR(20)  NOT NULL,               -- call|email|meeting|note|task
    subject          VARCHAR(255),
    body             TEXT,
    outcome          VARCHAR(255),
    duration_minutes INT,
    activity_date    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    linked_type      VARCHAR(20),                         -- contact|lead|deal|account
    linked_id        BIGINT,
    performed_by     BIGINT       REFERENCES users(id),
    task_id          BIGINT       REFERENCES tasks(id),
    created_by       BIGINT       REFERENCES users(id),
    created_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at       TIMESTAMPTZ,
    CONSTRAINT chk_activity_type CHECK (type IN ('call', 'email', 'meeting', 'note', 'task')),
    CONSTRAINT chk_activity_linked_type CHECK (linked_type IN ('contact', 'lead', 'deal', 'account') OR linked_type IS NULL)
);

CREATE INDEX IF NOT EXISTS idx_activities_tenant
    ON activities(tenant_id, company_code)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_activities_linked
    ON activities(linked_type, linked_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_activities_performed_by
    ON activities(tenant_id, performed_by)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_activities_task
    ON activities(task_id)
    WHERE deleted_at IS NULL AND task_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_activities_date
    ON activities(tenant_id, activity_date DESC)
    WHERE deleted_at IS NULL;
