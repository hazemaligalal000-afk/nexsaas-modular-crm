-- Migration 040: Payroll Processing (ERP)
-- Requirements: 24.1, 24.2, 24.3, 24.4, 24.5, 24.6
-- Task 25.1: Create payroll_runs and payroll_lines table migrations with all allowance + deduction components

-- payroll_runs table
CREATE TABLE payroll_runs (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    fin_period          VARCHAR(6) NOT NULL,   -- YYYYMM
    
    -- Run identification
    run_no              VARCHAR(50) NOT NULL,
    run_date            DATE NOT NULL,
    
    -- Totals
    gross_pay           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_allowances    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_deductions    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    net_pay             DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Currency
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    exchange_rate       DECIMAL(10,6) NOT NULL DEFAULT 1.000000,
    
    -- Workflow status
    status              VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft|submitted|approved|paid|cancelled
    
    -- Journal entry link
    journal_entry_id    BIGINT,
    
    -- Export tracking
    payment_file_exported_at TIMESTAMPTZ,
    payment_file_path   VARCHAR(500),
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, run_no)
);

-- payroll_lines table (Detailed line item per employee per run)
CREATE TABLE payroll_lines (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    payroll_run_id      BIGINT NOT NULL REFERENCES payroll_runs(id) ON DELETE CASCADE,
    employee_id         BIGINT NOT NULL REFERENCES employees(id),
    
    -- Base figures
    base_salary         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    monthly_rate        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Individual totals
    line_gross_pay      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_allowances     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_deductions     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_net_pay        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Detailed allowances (28 slots per requirements)
    allowance_01        DECIMAL(15,2) DEFAULT 0.00,  -- Basic Allowance
    allowance_02        DECIMAL(15,2) DEFAULT 0.00,  -- Housing Allowance
    allowance_03        DECIMAL(15,2) DEFAULT 0.00,  -- Transport Allowance
    allowance_04        DECIMAL(15,2) DEFAULT 0.00,  -- Meal Allowance
    allowance_05        DECIMAL(15,2) DEFAULT 0.00,  -- Medical Allowance
    allowance_06        DECIMAL(15,2) DEFAULT 0.00,  -- Bonus
    allowance_07        DECIMAL(15,2) DEFAULT 0.00,  -- Overtime
    allowance_08        DECIMAL(15,2) DEFAULT 0.00,  -- Commision
    allowance_09        DECIMAL(15,2) DEFAULT 0.00,  -- Performance Bonus
    allowance_10        DECIMAL(15,2) DEFAULT 0.00,  -- Special Allowance
    allowance_11        DECIMAL(15,2) DEFAULT 0.00,  -- Arrears
    allowance_12        DECIMAL(15,2) DEFAULT 0.00,  -- Leave Encashment
    allowance_13        DECIMAL(15,2) DEFAULT 0.00,  -- Holiday Pay
    allowance_14        DECIMAL(15,2) DEFAULT 0.00,  -- Education Allowance
    allowance_15        DECIMAL(15,2) DEFAULT 0.00,  -- Hardship Allowance
    allowance_16        DECIMAL(15,2) DEFAULT 0.00,  -- Shift Allowance
    allowance_17        DECIMAL(15,2) DEFAULT 0.00,  -- Remote Work Allowance
    allowance_18        DECIMAL(15,2) DEFAULT 0.00,  -- Car Allowance
    allowance_19        DECIMAL(15,2) DEFAULT 0.00,  -- Fuel Allowance
    allowance_20        DECIMAL(15,2) DEFAULT 0.00,  -- Miscellaneous Allowance
    allowance_21        DECIMAL(15,2) DEFAULT 0.00,  -- Allowance 21
    allowance_22        DECIMAL(15,2) DEFAULT 0.00,  -- Allowance 22
    allowance_23        DECIMAL(15,2) DEFAULT 0.00,  -- Allowance 23
    allowance_24        DECIMAL(15,2) DEFAULT 0.00,  -- Allowance 24
    allowance_25        DECIMAL(15,2) DEFAULT 0.00,  -- Allowance 25
    allowance_26        DECIMAL(15,2) DEFAULT 0.00,  -- Allowance 26
    allowance_27        DECIMAL(15,2) DEFAULT 0.00,  -- Allowance 27
    allowance_28        DECIMAL(15,2) DEFAULT 0.00,  -- Allowance 28
    
    -- Detailed deductions (18 slots per requirements)
    deduction_01        DECIMAL(15,2) DEFAULT 0.00,  -- Social Insurance (Employee)
    deduction_02        DECIMAL(15,2) DEFAULT 0.00,  -- Income Tax
    deduction_03        DECIMAL(15,2) DEFAULT 0.00,  -- Health Insurance
    deduction_04        DECIMAL(15,2) DEFAULT 0.00,  -- Loan Repayment
    deduction_05        DECIMAL(15,2) DEFAULT 0.00,  -- Advance Deduction
    deduction_06        DECIMAL(15,2) DEFAULT 0.00,  -- Pension Fund
    deduction_07        DECIMAL(15,2) DEFAULT 0.00,  -- Life Insurance
    deduction_08        DECIMAL(15,2) DEFAULT 0.00,  -- Professional Fees
    deduction_09        DECIMAL(15,2) DEFAULT 0.00,  -- Union Dues
    deduction_10        DECIMAL(15,2) DEFAULT 0.00,  -- Charity/Donations
    deduction_11        DECIMAL(15,2) DEFAULT 0.00,  -- Late Penalty
    deduction_12        DECIMAL(15,2) DEFAULT 0.00,  -- Absence Penalty
    deduction_13        DECIMAL(15,2) DEFAULT 0.00,  -- Disciplinary Fine
    deduction_14        DECIMAL(15,2) DEFAULT 0.00,  -- Tool Rental
    deduction_15        DECIMAL(15,2) DEFAULT 0.00,  -- Deduction 15
    deduction_16        DECIMAL(15,2) DEFAULT 0.00,  -- Deduction 16
    deduction_17        DECIMAL(15,2) DEFAULT 0.00,  -- Deduction 17
    deduction_18        DECIMAL(15,2) DEFAULT 0.00,  -- Deduction 18
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- Comments
COMMENT ON TABLE payroll_runs IS 'Master record for monthly payroll computation (Req 24.1, 24.2)';
COMMENT ON TABLE payroll_lines IS 'Individual employee payroll breakdowns with 28 allowance/18 deduction slots (Req 24.1, 24.3)';
