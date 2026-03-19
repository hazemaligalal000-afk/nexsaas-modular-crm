-- Migration 039: HR and Employee Management
-- Requirements: 23.1, 23.2, 23.3, 23.4, 23.5, 23.6, 23.7
-- Task 24.1: Create employees, departments, leave_types, leave_requests, employee_documents table migrations

-- departments table
CREATE TABLE departments (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    name                VARCHAR(100) NOT NULL,
    parent_id           BIGINT REFERENCES departments(id),
    manager_id          BIGINT,  -- Link to users(id) later
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, name)
);

-- employees table
CREATE TABLE employees (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    user_id             BIGINT REFERENCES users(id),  -- Link to system user if applicable
    
    -- Personal Information
    employee_no         VARCHAR(20) NOT NULL,
    first_name          VARCHAR(100) NOT NULL,
    last_name           VARCHAR(100) NOT NULL,
    full_name           VARCHAR(255) GENERATED ALWAYS AS (first_name || ' ' || last_name) STORED,
    email               VARCHAR(255),
    phone               VARCHAR(50),
    national_id         VARCHAR(50),
    date_of_birth       DATE,
    gender              VARCHAR(10),  -- male|female
    
    -- Job Information
    department_id       BIGINT REFERENCES departments(id),
    job_title           VARCHAR(100),
    manager_id          BIGINT REFERENCES employees(id),  -- Org chart hierarchy
    
    -- Employment Details
    hire_date           DATE NOT NULL,
    employment_status   VARCHAR(20) NOT NULL DEFAULT 'active',  -- onboarding|active|on_leave|offboarding
    employment_type     VARCHAR(20) NOT NULL DEFAULT 'full_time', -- full_time|part_time|contract
    
    -- Termination/Offboarding
    termination_date    DATE,
    termination_reason  TEXT,
    
    -- Payroll Linkage
    base_salary         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    
    -- Address
    address             JSONB,
    emergency_contact   JSONB,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, employee_no)
);

CREATE INDEX idx_employees_tenant_company ON employees(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_employees_status ON employees(employment_status) WHERE deleted_at IS NULL;
CREATE INDEX idx_employees_dept ON employees(department_id) WHERE deleted_at IS NULL;

-- leave_types table
CREATE TABLE leave_types (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    name                VARCHAR(50) NOT NULL,
    is_paid             BOOLEAN NOT NULL DEFAULT true,
    accrual_policy      VARCHAR(50),  -- per_month|per_year|no_accrual
    annual_limit        DECIMAL(5,1) DEFAULT 21.0,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, name)
);

-- leave_requests table
CREATE TABLE leave_requests (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    employee_id         BIGINT NOT NULL REFERENCES employees(id),
    leave_type_id       BIGINT NOT NULL REFERENCES leave_types(id),
    
    start_date          DATE NOT NULL,
    end_date            DATE NOT NULL,
    total_days          DECIMAL(5,1) NOT NULL,
    
    reason              TEXT,
    
    -- Workflow status
    status              VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending|approved|rejected|cancelled
    
    -- Approval info
    approver_id         BIGINT REFERENCES employees(id),   -- Direct manager
    approved_at         TIMESTAMPTZ,
    rejection_reason    TEXT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- employee_documents table
CREATE TABLE employee_documents (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    employee_id         BIGINT NOT NULL REFERENCES employees(id),
    document_type       VARCHAR(50) NOT NULL,  -- passport|visa|id|contract|resume|etc
    title               VARCHAR(255) NOT NULL,
    file_path           VARCHAR(500) NOT NULL,
    file_size           INT,
    mime_type           VARCHAR(100),
    
    expiry_date         DATE,
    version             INT NOT NULL DEFAULT 1,
    
    -- Restricted access
    access_role         VARCHAR(20) NOT NULL DEFAULT 'hr',  -- Restricted to HR role and above
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- Comments
COMMENT ON TABLE employees IS 'Central HR management for all workforce members (Req 23.1, 23.2)';
COMMENT ON TABLE departments IS 'Organizational unit structure for employees and projects (Req 23.1, 23.2)';
COMMENT ON TABLE leave_requests IS 'Tracking of employee absences and leave balances (Req 23.6, 23.7)';
COMMENT ON TABLE employee_documents IS 'Confidential document storage for HR records (Req 23.5)';
