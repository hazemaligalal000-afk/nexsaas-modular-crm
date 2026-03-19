-- Migration 021: Workflow Automation Engine
-- Creates workflows, workflow_triggers, workflow_actions,
-- workflow_executions, and workflow_execution_steps tables
-- Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7, 14.8, 14.9

-- workflows: one workflow per tenant, composed of one trigger + N actions
CREATE TABLE workflows (
    id               BIGSERIAL PRIMARY KEY,
    tenant_id        UUID NOT NULL,
    company_code     VARCHAR(2) NOT NULL DEFAULT '01',
    name             VARCHAR(255) NOT NULL,
    module           VARCHAR(100) NOT NULL,          -- e.g. leads, contacts, deals
    trigger_type     VARCHAR(50) NOT NULL,
    trigger_config   JSONB NOT NULL DEFAULT '{}',
    is_enabled       BOOLEAN NOT NULL DEFAULT TRUE,
    created_by       BIGINT REFERENCES users(id),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at       TIMESTAMPTZ,
    CONSTRAINT chk_workflow_trigger_type CHECK (
        trigger_type IN (
            'record_created',
            'record_updated',
            'field_value_changed',
            'date_time_reached',
            'inbound_message_received',
            'manual'
        )
    )
);

CREATE INDEX idx_workflows_tenant         ON workflows(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_workflows_tenant_enabled ON workflows(tenant_id, is_enabled) WHERE deleted_at IS NULL;
CREATE INDEX idx_workflows_trigger_type   ON workflows(tenant_id, trigger_type) WHERE deleted_at IS NULL AND is_enabled = TRUE;

-- workflow_actions: ordered list of actions for each workflow
CREATE TABLE workflow_actions (
    id            BIGSERIAL PRIMARY KEY,
    tenant_id     UUID NOT NULL,
    company_code  VARCHAR(2) NOT NULL DEFAULT '01',
    workflow_id   BIGINT NOT NULL REFERENCES workflows(id) ON DELETE CASCADE,
    action_order  INT NOT NULL DEFAULT 1,
    action_type   VARCHAR(50) NOT NULL,              -- send_email, send_sms, create_task, update_field, assign_owner, add_tag, create_deal, move_deal_stage, call_webhook, wait
    action_config JSONB NOT NULL DEFAULT '{}',
    created_by    BIGINT REFERENCES users(id),
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at    TIMESTAMPTZ
);

CREATE INDEX idx_workflow_actions_workflow ON workflow_actions(workflow_id, action_order) WHERE deleted_at IS NULL;
CREATE INDEX idx_workflow_actions_tenant   ON workflow_actions(tenant_id) WHERE deleted_at IS NULL;

-- workflow_executions: one row per workflow run
CREATE TABLE workflow_executions (
    id           BIGSERIAL PRIMARY KEY,
    tenant_id    UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL DEFAULT '01',
    workflow_id  BIGINT NOT NULL REFERENCES workflows(id),
    status       VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending|running|completed|failed
    trigger_event VARCHAR(100),
    context      JSONB NOT NULL DEFAULT '{}',
    started_at   TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    created_by   BIGINT REFERENCES users(id),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at   TIMESTAMPTZ,
    CONSTRAINT chk_workflow_exec_status CHECK (status IN ('pending', 'running', 'completed', 'failed'))
);

CREATE INDEX idx_workflow_exec_tenant   ON workflow_executions(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_workflow_exec_workflow ON workflow_executions(workflow_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_workflow_exec_status   ON workflow_executions(tenant_id, status) WHERE deleted_at IS NULL;

-- workflow_execution_steps: one row per action step within an execution
CREATE TABLE workflow_execution_steps (
    id              BIGSERIAL PRIMARY KEY,
    tenant_id       UUID NOT NULL,
    company_code    VARCHAR(2) NOT NULL DEFAULT '01',
    execution_id    BIGINT NOT NULL REFERENCES workflow_executions(id) ON DELETE CASCADE,
    action_id       BIGINT REFERENCES workflow_actions(id),
    action_order    INT NOT NULL,
    action_type     VARCHAR(50) NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending|running|completed|failed|skipped
    result          JSONB,
    error_message   TEXT,
    retry_count     INT NOT NULL DEFAULT 0,
    started_at      TIMESTAMPTZ,
    completed_at    TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ,
    CONSTRAINT chk_workflow_step_status CHECK (status IN ('pending', 'running', 'completed', 'failed', 'skipped'))
);

CREATE INDEX idx_workflow_steps_execution ON workflow_execution_steps(execution_id, action_order) WHERE deleted_at IS NULL;
CREATE INDEX idx_workflow_steps_tenant    ON workflow_execution_steps(tenant_id) WHERE deleted_at IS NULL;
