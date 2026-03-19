-- Migration 048: Cost Centers and Project Accounting (Batch F)
-- Task 34.1: CREATE cost_center_budgets, afes table migrations

CREATE TABLE cost_center_budgets (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    cost_center_id      BIGINT NOT NULL REFERENCES cost_centers(id),
    coa_account_code    VARCHAR(50) NOT NULL,
    fin_year            INT NOT NULL,
    annual_budget       DECIMAL(15, 2) NOT NULL DEFAULT 0,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(tenant_id, company_code, cost_center_id, coa_account_code, fin_year)
);

CREATE TABLE afes (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    afe_number          VARCHAR(50) NOT NULL,
    description         TEXT NOT NULL,
    approved_budget     DECIMAL(15, 2) NOT NULL,
    actual_spend        DECIMAL(15, 2) NOT NULL DEFAULT 0,
    wip_account         VARCHAR(50) NOT NULL, -- WIP EXP, WIP DEV, WIP CONSTR
    status              VARCHAR(20) DEFAULT 'open', -- open, closed, abandoned
    closed_to_asset_id  BIGINT, -- If capitalized
    closed_to_account   VARCHAR(50), -- If expensed/dry hole
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(tenant_id, company_code, afe_number)
);

CREATE TABLE cost_allocation_rules (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    source_account      VARCHAR(50) NOT NULL,
    target_cost_center_id BIGINT NOT NULL REFERENCES cost_centers(id),
    allocation_pct      DECIMAL(5, 2) NOT NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE department_time_allocations (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    employee_id         BIGINT NOT NULL,
    fin_period          VARCHAR(6) NOT NULL,
    cost_center_id      BIGINT NOT NULL REFERENCES cost_centers(id),
    time_pct            DECIMAL(5, 2) NOT NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(tenant_id, employee_id, fin_period, cost_center_id)
);

COMMENT ON TABLE cost_center_budgets IS 'Annual budgets per cost center per expense account (Req 50.3)';
COMMENT ON TABLE afes IS 'Authorization for Expenditure (AFE) tracking for CAPEX/OPEX WIP (Req 50.5-50.8)';
COMMENT ON TABLE cost_allocation_rules IS 'Ratios to distribute indirect pool expenses to target cost centers (Req 50.4)';
COMMENT ON TABLE department_time_allocations IS 'Time sheets translating to payroll cost distribution (Req 50.10)';
