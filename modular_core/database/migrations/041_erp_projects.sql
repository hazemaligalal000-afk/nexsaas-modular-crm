-- Migration 041: Project Management (ERP)
-- Requirements: 25.1, 25.2, 25.3, 25.4, 25.5, 25.6, 25.7
-- Task 26.1: Create projects, project_tasks, milestones, time_logs table migrations

-- projects table
CREATE TABLE projects (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    name                VARCHAR(255) NOT NULL,
    description         TEXT,
    
    -- Client Link
    account_id          BIGINT REFERENCES accounts(id),
    
    -- Budget and Costs
    budget_hours        DECIMAL(15,2) DEFAULT 0.00,
    actual_hours        DECIMAL(15,2) DEFAULT 0.00,
    budget_amount       DECIMAL(15,2) DEFAULT 0.00,
    actual_amount       DECIMAL(15,2) DEFAULT 0.00,
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    
    -- Dates
    start_date          DATE NOT NULL,
    end_date            DATE,
    actual_end_date     DATE,
    
    -- Status
    status              VARCHAR(20) NOT NULL DEFAULT 'planning', -- planning|active|on_hold|completed|cancelled
    priority            VARCHAR(10) DEFAULT 'medium',
    
    -- Owner
    manager_id          BIGINT REFERENCES users(id),
    
    -- Progress
    completion_pct      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, name)
);

-- milestones table
CREATE TABLE milestones (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    project_id          BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name                VARCHAR(255) NOT NULL,
    description         TEXT,
    
    due_date            DATE NOT NULL,
    achieved_at         TIMESTAMPTZ,
    
    status              VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending|completed|missed
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- project_tasks table
CREATE TABLE project_tasks (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    project_id          BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    milestone_id        BIGINT REFERENCES milestones(id),
    parent_task_id      BIGINT REFERENCES project_tasks(id),
    
    name                VARCHAR(255) NOT NULL,
    description         TEXT,
    
    -- Hierarchy Info
    hierarchy_depth     SMALLINT NOT NULL DEFAULT 0,  -- Up to 3 levels
    
    -- Task Duration and Schedule
    start_date          DATE,
    end_date            DATE,
    budget_hours        DECIMAL(10,2) DEFAULT 0.00,
    actual_hours        DECIMAL(10,2) DEFAULT 0.00,
    
    -- Dependencies (finish-to-start)
    depends_on_task_ids BIGINT[] DEFAULT '{}',
    
    -- Status and Priority
    status              VARCHAR(20) NOT NULL DEFAULT 'todo', -- todo|in_progress|review|done|blocked
    priority            VARCHAR(10) DEFAULT 'medium',
    
    -- Assignments
    assignee_id         BIGINT REFERENCES users(id),
    
    -- Progress
    completion_pct      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- time_logs table (Actual hours tracking)
CREATE TABLE time_logs (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    project_id          BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    task_id             BIGINT REFERENCES project_tasks(id) ON DELETE CASCADE,
    employee_id         BIGINT REFERENCES users(id),
    
    log_date            DATE NOT NULL,
    hours               DECIMAL(5,2) NOT NULL,
    description         TEXT,
    
    -- Financial Link
    billable            BOOLEAN NOT NULL DEFAULT true,
    billing_status      VARCHAR(20) DEFAULT 'unbilled', -- unbilled|billed|non_billable
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- Comments
COMMENT ON TABLE projects IS 'Large-scale complex initiatives with budget and timeline tracking (Req 25.1, 25.2)';
COMMENT ON TABLE milestones IS 'Key target dates and deliverables within a project (Req 25.5)';
COMMENT ON TABLE project_tasks IS 'Individual work items with hierarchy and dependency support (Req 25.2, 25.3, 25.4)';
COMMENT ON TABLE time_logs IS 'Activity logs for actual vs budgeted hour tracking (Req 25.6)';
