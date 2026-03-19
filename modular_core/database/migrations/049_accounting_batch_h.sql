-- Migration 049: Payroll and Salary Module (Batch H)
-- Task 36.1: CREATE payroll_runs, payroll_lines (28 allowances + 18 deductions implicitly structured via JSONB or explicit columns)

CREATE TABLE payroll_runs (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    fin_period          VARCHAR(6) NOT NULL, -- YYYYMM
    run_date            DATE NOT NULL,
    run_type            VARCHAR(30) DEFAULT 'regular', -- regular, board_compensation, bonus
    status              VARCHAR(20) DEFAULT 'draft', -- draft, computed, posted, paid
    total_gross         DECIMAL(15, 2) NOT NULL DEFAULT 0,
    total_deductions    DECIMAL(15, 2) NOT NULL DEFAULT 0,
    total_net           DECIMAL(15, 2) NOT NULL DEFAULT 0,
    journal_entry_id    BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(tenant_id, company_code, fin_period, run_type)
);

CREATE TABLE payroll_lines (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    payroll_run_id      BIGINT NOT NULL REFERENCES payroll_runs(id),
    employee_id         BIGINT NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    status              VARCHAR(20) DEFAULT 'valid', -- valid, excluded_negative_net
    
    -- Component Breakdown (Using JSONB to accommodate exactly 28 Allowances + 18 Deductions dynamically as per config)
    allowances          JSONB NOT NULL DEFAULT '{}',
    deductions          JSONB NOT NULL DEFAULT '{}',
    
    -- Computed Totals
    gross_pay           DECIMAL(15, 2) NOT NULL DEFAULT 0,
    total_deduction     DECIMAL(15, 2) NOT NULL DEFAULT 0,
    net_pay             DECIMAL(15, 2) NOT NULL DEFAULT 0,
    
    employer_social_ins DECIMAL(15, 2) NOT NULL DEFAULT 0,
    employee_social_ins DECIMAL(15, 2) NOT NULL DEFAULT 0,
    
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE on_loan_employees (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    employee_id         BIGINT NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    loan_type           VARCHAR(50) NOT NULL, -- loanee_from_other, loanee_to_other, onloan_epsco
    start_date          DATE NOT NULL,
    end_date            DATE,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE payroll_runs IS 'Master record for standard monthly payrolls or specialized board compensation runs (Req 52.1, 52.3, 52.8)';
COMMENT ON TABLE payroll_lines IS 'Calculated line per employee capturing 28+ allowance and 18+ deduction matrices via JSONB (Req 52.2, 52.4)';
COMMENT ON TABLE on_loan_employees IS 'Tracking loan directions mapped into particular Salary GL Accounts (Req 52.7)';
