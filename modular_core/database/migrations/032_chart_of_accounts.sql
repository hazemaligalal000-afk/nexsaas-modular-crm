-- ══════════════════════════════════════════════════════════════════════════
-- Migration 032: Chart of Accounts (COA)
-- Based on: chart.xls - Full multi-level account hierarchy
-- 5 levels: Category → Group → Sub-group → Account → Sub-account
-- ══════════════════════════════════════════════════════════════════════════

-- ──────────────────────────────────────────────────────────────────────────
-- 1. CHART OF ACCOUNTS
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS chart_of_accounts (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,  -- FK to companies.code
    account_code VARCHAR(20) NOT NULL,  -- Hierarchical code (e.g., '1.1.1.001')
    account_name_en VARCHAR(300) NOT NULL,
    account_name_ar VARCHAR(300),
    parent_code VARCHAR(20),  -- NULL for top-level categories
    account_level INT NOT NULL,  -- 1=Category, 2=Group, 3=Sub-group, 4=Account, 5=Sub-account
    account_type VARCHAR(50) NOT NULL,  -- Asset, Liability, Equity, Income, Expense, Cost, Allocation
    account_subtype VARCHAR(100),  -- Bank, Cash, AR, AP, Fixed Asset, etc.
    currency_restriction VARCHAR(2),  -- FK to currencies.code (NULL = all currencies allowed)
    is_active BOOLEAN DEFAULT TRUE,
    is_blocked BOOLEAN DEFAULT FALSE,  -- Prevent new postings
    allow_posting BOOLEAN DEFAULT TRUE,  -- FALSE for parent/summary accounts
    balance_type VARCHAR(10) DEFAULT 'debit',  -- 'debit' or 'credit' normal balance
    description TEXT,
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, company_code, account_code, deleted_at),
    CONSTRAINT chk_account_level CHECK (account_level BETWEEN 1 AND 5),
    CONSTRAINT chk_account_type CHECK (account_type IN ('Asset', 'Liability', 'Equity', 'Income', 'Expense', 'Cost', 'Allocation')),
    CONSTRAINT chk_balance_type CHECK (balance_type IN ('debit', 'credit'))
);

CREATE INDEX idx_coa_tenant ON chart_of_accounts(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_coa_company ON chart_of_accounts(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_coa_code ON chart_of_accounts(tenant_id, company_code, account_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_coa_parent ON chart_of_accounts(tenant_id, company_code, parent_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_coa_type ON chart_of_accounts(tenant_id, company_code, account_type) WHERE deleted_at IS NULL;
CREATE INDEX idx_coa_active ON chart_of_accounts(tenant_id, company_code, is_active) WHERE deleted_at IS NULL;

COMMENT ON TABLE chart_of_accounts IS 'Full multi-level chart of accounts - 5-level hierarchy';
COMMENT ON COLUMN chart_of_accounts.account_level IS '1=Category, 2=Group, 3=Sub-group, 4=Account, 5=Sub-account';
COMMENT ON COLUMN chart_of_accounts.currency_restriction IS 'If set, account can only be used with specified currency';
COMMENT ON COLUMN chart_of_accounts.is_blocked IS 'Prevent new postings but show warning if old transactions exist';
COMMENT ON COLUMN chart_of_accounts.allow_posting IS 'FALSE for parent/summary accounts that should not have direct postings';

-- ──────────────────────────────────────────────────────────────────────────
-- 2. ACCOUNT OPENING BALANCES
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS account_opening_balances (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,
    account_code VARCHAR(20) NOT NULL,  -- FK to chart_of_accounts.account_code
    currency_code VARCHAR(2) NOT NULL,  -- FK to currencies.code
    fin_period VARCHAR(6) NOT NULL,  -- YYYYMM format
    opening_dr DECIMAL(15,2) DEFAULT 0.00,
    opening_cr DECIMAL(15,2) DEFAULT 0.00,
    opening_dr_base DECIMAL(15,2) DEFAULT 0.00,  -- In base currency (EGP)
    opening_cr_base DECIMAL(15,2) DEFAULT 0.00,  -- In base currency (EGP)
    exchange_rate DECIMAL(10,6) DEFAULT 1.000000,
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, company_code, account_code, currency_code, fin_period, deleted_at)
);

CREATE INDEX idx_opening_balances_tenant ON account_opening_balances(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_opening_balances_company ON account_opening_balances(tenant_id, company_code, fin_period) WHERE deleted_at IS NULL;
CREATE INDEX idx_opening_balances_account ON account_opening_balances(tenant_id, company_code, account_code) WHERE deleted_at IS NULL;

COMMENT ON TABLE account_opening_balances IS 'Opening balances per account per company per currency per period';

-- ──────────────────────────────────────────────────────────────────────────
-- 3. COST CENTERS
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cost_centers (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,
    cost_center_code VARCHAR(20) NOT NULL,
    cost_center_name_en VARCHAR(200) NOT NULL,
    cost_center_name_ar VARCHAR(200),
    parent_code VARCHAR(20),  -- For hierarchical cost centers
    is_active BOOLEAN DEFAULT TRUE,
    description TEXT,
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, company_code, cost_center_code, deleted_at)
);

CREATE INDEX idx_cost_centers_tenant ON cost_centers(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_cost_centers_company ON cost_centers(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_cost_centers_code ON cost_centers(tenant_id, company_code, cost_center_code) WHERE deleted_at IS NULL;

COMMENT ON TABLE cost_centers IS 'Cost centers for expense allocation and project accounting';

-- ──────────────────────────────────────────────────────────────────────────
-- 4. VENDORS (Clients & Suppliers)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vendors (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,
    vendor_code VARCHAR(20) NOT NULL,
    vendor_name_en VARCHAR(300) NOT NULL,
    vendor_name_ar VARCHAR(300),
    vendor_type VARCHAR(20) NOT NULL,  -- 'customer', 'supplier', 'both'
    tax_id VARCHAR(50),
    email VARCHAR(200),
    phone VARCHAR(50),
    address TEXT,
    payment_terms VARCHAR(100),
    credit_limit DECIMAL(15,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, company_code, vendor_code, deleted_at),
    CONSTRAINT chk_vendor_type CHECK (vendor_type IN ('customer', 'supplier', 'both'))
);

CREATE INDEX idx_vendors_tenant ON vendors(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_vendors_company ON vendors(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_vendors_code ON vendors(tenant_id, company_code, vendor_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_vendors_type ON vendors(tenant_id, company_code, vendor_type) WHERE deleted_at IS NULL;

COMMENT ON TABLE vendors IS 'Vendors (customers and suppliers) for AR/AP tracking';

-- ──────────────────────────────────────────────────────────────────────────
-- 5. FIXED ASSETS
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fixed_assets (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,
    asset_code VARCHAR(20) NOT NULL,
    asset_name_en VARCHAR(300) NOT NULL,
    asset_name_ar VARCHAR(300),
    asset_category VARCHAR(100),  -- BUILDINGS, VEHICLES, COMPUTER HARDWARE, etc.
    account_code VARCHAR(20),  -- FK to chart_of_accounts.account_code
    purchase_date DATE,
    purchase_cost DECIMAL(15,2),
    currency_code VARCHAR(2),
    salvage_value DECIMAL(15,2),
    useful_life_years INT,
    depreciation_method VARCHAR(50),  -- 'straight_line', 'declining_balance'
    accumulated_depreciation DECIMAL(15,2) DEFAULT 0.00,
    net_book_value DECIMAL(15,2),
    status VARCHAR(20) DEFAULT 'active',  -- 'active', 'disposed', 'retired'
    disposal_date DATE,
    disposal_amount DECIMAL(15,2),
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, company_code, asset_code, deleted_at),
    CONSTRAINT chk_asset_status CHECK (status IN ('active', 'disposed', 'retired')),
    CONSTRAINT chk_depreciation_method CHECK (depreciation_method IN ('straight_line', 'declining_balance'))
);

CREATE INDEX idx_fixed_assets_tenant ON fixed_assets(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_fixed_assets_company ON fixed_assets(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_fixed_assets_code ON fixed_assets(tenant_id, company_code, asset_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_fixed_assets_status ON fixed_assets(tenant_id, company_code, status) WHERE deleted_at IS NULL;

COMMENT ON TABLE fixed_assets IS 'Fixed and movable assets register with depreciation tracking';

-- ──────────────────────────────────────────────────────────────────────────
-- 6. EMPLOYEES (for payroll linkage)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS employees (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,
    employee_no VARCHAR(20) NOT NULL,
    employee_name_en VARCHAR(200) NOT NULL,
    employee_name_ar VARCHAR(200),
    department VARCHAR(100),
    position VARCHAR(100),
    hire_date DATE,
    salary_grade VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, company_code, employee_no, deleted_at)
);

CREATE INDEX idx_employees_tenant ON employees(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_employees_company ON employees(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_employees_no ON employees(tenant_id, company_code, employee_no) WHERE deleted_at IS NULL;

COMMENT ON TABLE employees IS 'Employee master for payroll and journal entry linkage';
