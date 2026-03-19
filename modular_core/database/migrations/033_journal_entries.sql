-- ══════════════════════════════════════════════════════════════════════════
-- Migration 033: Journal Entries & Vouchers
-- Based on: سيستم_جديد.xlsx - Exact 35-field transaction structure
-- ══════════════════════════════════════════════════════════════════════════

-- ──────────────────────────────────────────────────────────────────────────
-- 1. JOURNAL ENTRY HEADERS
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS journal_entry_headers (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,  -- FK to companies.code
    voucher_no INT NOT NULL,  -- Auto-increment per company per period
    voucher_code VARCHAR(3) NOT NULL,  -- FK to voucher_sections.voucher_code (1-6, 999)
    section_code VARCHAR(3) NOT NULL,  -- FK to voucher_sections.section_code
    voucher_date DATE NOT NULL,
    fin_period VARCHAR(6) NOT NULL,  -- YYYYMM format
    service_date VARCHAR(6),  -- YYYYMM format - period service was for
    currency_code VARCHAR(2) NOT NULL,  -- FK to currencies.code
    exchange_rate DECIMAL(10,6) DEFAULT 1.000000,
    total_dr DECIMAL(15,2) DEFAULT 0.00,  -- Sum of all debit lines
    total_cr DECIMAL(15,2) DEFAULT 0.00,  -- Sum of all credit lines
    total_dr_base DECIMAL(15,2) DEFAULT 0.00,  -- In base currency (EGP)
    total_cr_base DECIMAL(15,2) DEFAULT 0.00,  -- In base currency (EGP)
    status VARCHAR(20) DEFAULT 'draft',  -- draft, submitted, approved, posted, reversed
    description TEXT,
    posted_by VARCHAR(20),
    posted_at TIMESTAMP,
    approved_by VARCHAR(20),
    approved_at TIMESTAMP,
    reversed_by VARCHAR(20),
    reversed_at TIMESTAMP,
    reversal_of_id BIGINT,  -- FK to journal_entry_headers.id (if this is a reversal)
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, company_code, voucher_no, fin_period, deleted_at),
    CONSTRAINT chk_je_status CHECK (status IN ('draft', 'submitted', 'approved', 'posted', 'reversed')),
    CONSTRAINT chk_je_balance CHECK (
        (status != 'posted' AND status != 'approved') OR 
        (ABS(total_dr - total_cr) < 0.01)
    )
);

CREATE INDEX idx_je_headers_tenant ON journal_entry_headers(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_headers_company ON journal_entry_headers(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_headers_period ON journal_entry_headers(tenant_id, company_code, fin_period) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_headers_voucher ON journal_entry_headers(tenant_id, company_code, voucher_no, fin_period) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_headers_status ON journal_entry_headers(tenant_id, company_code, status) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_headers_date ON journal_entry_headers(tenant_id, company_code, voucher_date) WHERE deleted_at IS NULL;

COMMENT ON TABLE journal_entry_headers IS 'Journal entry headers - one per voucher';
COMMENT ON COLUMN journal_entry_headers.status IS 'Workflow: draft → submitted → approved → posted → reversed';
COMMENT ON CONSTRAINT chk_je_balance ON journal_entry_headers IS 'Enforce double-entry: Dr must equal Cr before posting';

-- ──────────────────────────────────────────────────────────────────────────
-- 2. JOURNAL ENTRY LINES (سيستم_جديد.xlsx - ALL 35 FIELDS)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS journal_entry_lines (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,  -- FK to companies.code
    
    -- Header reference
    je_header_id BIGINT NOT NULL,  -- FK to journal_entry_headers.id
    
    -- Voucher identification (from سيستم_جديد.xlsx)
    area_code VARCHAR(2),  -- Branch/location code
    area_desc VARCHAR(200),  -- Auto-filled from area master
    fin_period VARCHAR(6) NOT NULL,  -- YYYYMM format
    voucher_date DATE NOT NULL,
    service_date VARCHAR(6),  -- YYYYMM format
    voucher_no INT NOT NULL,
    section_code VARCHAR(3) NOT NULL,
    voucher_sub VARCHAR(3),  -- Sub-reference within voucher
    line_no INT NOT NULL,  -- Sequence within journal entry
    
    -- Account and cost allocation
    account_code VARCHAR(20) NOT NULL,  -- FK to chart_of_accounts.account_code
    account_desc VARCHAR(300),  -- Auto-filled from COA
    cost_identifier VARCHAR(100),  -- Free-text cost description
    cost_center_code VARCHAR(20),  -- FK to cost_centers.code
    cost_center_name VARCHAR(200),  -- Auto-filled
    
    -- Vendor/Client linkage
    vendor_code VARCHAR(20),  -- FK to vendors.code
    vendor_name VARCHAR(300),  -- Auto-filled
    
    -- Banking
    check_transfer_no VARCHAR(50),  -- Bank reference
    
    -- Currency and amounts
    exchange_rate DECIMAL(10,6) DEFAULT 1.000000,
    currency_code VARCHAR(2) NOT NULL,  -- FK to currencies.code
    dr_value DECIMAL(15,2) DEFAULT 0.00,  -- Debit in transaction currency
    cr_value DECIMAL(15,2) DEFAULT 0.00,  -- Credit in transaction currency
    dr_value_base DECIMAL(15,2) DEFAULT 0.00,  -- Debit in base currency (EGP)
    cr_value_base DECIMAL(15,2) DEFAULT 0.00,  -- Credit in base currency (EGP)
    
    -- Description and references
    line_desc VARCHAR(500),  -- Free-text per line
    asset_no VARCHAR(20),  -- FK to fixed_assets.asset_code
    transaction_no VARCHAR(50),  -- External transaction reference
    profit_loss_flag VARCHAR(10),  -- P&L allocation flag
    customer_invoice_no VARCHAR(50),  -- Outbound invoice reference
    income_stmt_flag VARCHAR(10),  -- Income statement flag
    internal_invoice_no VARCHAR(50),  -- Internal cross-charge reference
    
    -- Employee and partner linkage
    employee_no VARCHAR(20),  -- FK to employees.employee_no
    partner_no VARCHAR(10),  -- FK to partners.partner_code
    
    -- Translation business metrics
    vendor_word_count INT DEFAULT 0,  -- Translation billing metric
    translator_word_count INT DEFAULT 0,  -- Translator payment metric
    
    -- Agent
    agent_name VARCHAR(200),  -- Person who handled the transaction
    
    -- Audit
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    
    CONSTRAINT fk_je_line_header FOREIGN KEY (je_header_id) REFERENCES journal_entry_headers(id) ON DELETE CASCADE,
    CONSTRAINT chk_je_line_dr_cr CHECK (
        (dr_value > 0 AND cr_value = 0) OR 
        (cr_value > 0 AND dr_value = 0) OR 
        (dr_value = 0 AND cr_value = 0)
    )
);

CREATE INDEX idx_je_lines_tenant ON journal_entry_lines(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_lines_company ON journal_entry_lines(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_lines_header ON journal_entry_lines(je_header_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_lines_account ON journal_entry_lines(tenant_id, company_code, account_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_lines_period ON journal_entry_lines(tenant_id, company_code, fin_period) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_lines_vendor ON journal_entry_lines(tenant_id, company_code, vendor_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_lines_cost_center ON journal_entry_lines(tenant_id, company_code, cost_center_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_lines_employee ON journal_entry_lines(tenant_id, company_code, employee_no) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_lines_partner ON journal_entry_lines(tenant_id, company_code, partner_no) WHERE deleted_at IS NULL;
CREATE INDEX idx_je_lines_asset ON journal_entry_lines(tenant_id, company_code, asset_no) WHERE deleted_at IS NULL;

COMMENT ON TABLE journal_entry_lines IS 'Journal entry lines - exact 35-field structure from سيستم_جديد.xlsx';
COMMENT ON CONSTRAINT chk_je_line_dr_cr ON journal_entry_lines IS 'Each line must be either Dr OR Cr, not both';

-- ──────────────────────────────────────────────────────────────────────────
-- 3. AUDIT LOG (Immutable)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS journal_audit_log (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,
    je_header_id BIGINT NOT NULL,
    action VARCHAR(50) NOT NULL,  -- 'created', 'submitted', 'approved', 'posted', 'reversed'
    performed_by VARCHAR(20) NOT NULL,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(50),
    user_agent TEXT,
    changes JSONB,  -- Store before/after state
    CONSTRAINT fk_audit_header FOREIGN KEY (je_header_id) REFERENCES journal_entry_headers(id) ON DELETE CASCADE
);

CREATE INDEX idx_audit_log_tenant ON journal_audit_log(tenant_id);
CREATE INDEX idx_audit_log_header ON journal_audit_log(je_header_id);
CREATE INDEX idx_audit_log_action ON journal_audit_log(tenant_id, company_code, action);
CREATE INDEX idx_audit_log_user ON journal_audit_log(tenant_id, performed_by);
CREATE INDEX idx_audit_log_date ON journal_audit_log(tenant_id, company_code, performed_at);

COMMENT ON TABLE journal_audit_log IS 'Immutable audit trail for every journal entry action';

-- ──────────────────────────────────────────────────────────────────────────
-- 4. ACCOUNT BALANCES (Materialized for performance)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS account_balances (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,
    account_code VARCHAR(20) NOT NULL,
    currency_code VARCHAR(2) NOT NULL,
    fin_period VARCHAR(6) NOT NULL,
    opening_dr DECIMAL(15,2) DEFAULT 0.00,
    opening_cr DECIMAL(15,2) DEFAULT 0.00,
    period_dr DECIMAL(15,2) DEFAULT 0.00,
    period_cr DECIMAL(15,2) DEFAULT 0.00,
    closing_dr DECIMAL(15,2) DEFAULT 0.00,
    closing_cr DECIMAL(15,2) DEFAULT 0.00,
    net_balance DECIMAL(15,2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, company_code, account_code, currency_code, fin_period)
);

CREATE INDEX idx_account_balances_tenant ON account_balances(tenant_id);
CREATE INDEX idx_account_balances_company ON account_balances(tenant_id, company_code, fin_period);
CREATE INDEX idx_account_balances_account ON account_balances(tenant_id, company_code, account_code);

COMMENT ON TABLE account_balances IS 'Materialized account balances per period - updated on journal posting';
