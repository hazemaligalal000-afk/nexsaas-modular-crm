-- ══════════════════════════════════════════════════════════════════════════
-- Migration 031: NexSaaS Accounting Foundation
-- Companies, Currencies, Voucher/Section Codes, Partners, Financial Periods
-- Based on: Company_Code.xlsx, Currency_Code.xlsx, Vocher___Section_Code.xlsx,
--           Partners_Code.xlsx
-- ══════════════════════════════════════════════════════════════════════════

-- ──────────────────────────────────────────────────────────────────────────
-- 1. COMPANIES MASTER (Company_Code.xlsx)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS companies (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    code VARCHAR(2) NOT NULL,  -- '01' to '06'
    name_en VARCHAR(200) NOT NULL,
    name_ar VARCHAR(200),
    activity VARCHAR(200),
    tax_card_no VARCHAR(50),
    tax_office VARCHAR(200),
    tax_card_expiry DATE,
    commercial_reg_no VARCHAR(50),
    commercial_reg_expiry DATE,
    vat_registered BOOLEAN DEFAULT FALSE,
    e_invoice_active BOOLEAN DEFAULT FALSE,
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, code, deleted_at)
);

CREATE INDEX idx_companies_tenant ON companies(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_companies_code ON companies(tenant_id, code) WHERE deleted_at IS NULL;

COMMENT ON TABLE companies IS 'Multi-company group master - each tenant can have up to 6 companies';
COMMENT ON COLUMN companies.code IS 'Two-digit company code 01-06';
COMMENT ON COLUMN companies.e_invoice_active IS 'Egyptian Tax Authority E-Invoice integration flag';

-- ──────────────────────────────────────────────────────────────────────────
-- 2. CURRENCIES MASTER (Currency_Code.xlsx)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS currencies (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    code VARCHAR(2) NOT NULL,  -- '01' to '06'
    iso_code VARCHAR(3) NOT NULL,  -- EGP, USD, AED, SAR, EUR, GBP
    name_en VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100),
    country_en VARCHAR(200),
    country_ar VARCHAR(200),
    symbol VARCHAR(10),
    is_base_currency BOOLEAN DEFAULT FALSE,
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, code, deleted_at),
    UNIQUE(tenant_id, iso_code, deleted_at)
);

CREATE INDEX idx_currencies_tenant ON currencies(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_currencies_iso ON currencies(tenant_id, iso_code) WHERE deleted_at IS NULL;

COMMENT ON TABLE currencies IS 'System operates in 6 currencies simultaneously';
COMMENT ON COLUMN currencies.is_base_currency IS 'Base currency for conversion (typically EGP)';

-- ──────────────────────────────────────────────────────────────────────────
-- 3. EXCHANGE RATES
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS exchange_rates (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    currency_code VARCHAR(2) NOT NULL,  -- FK to currencies.code
    rate_date DATE NOT NULL,
    rate_to_base DECIMAL(10,6) NOT NULL,  -- Rate to convert to base currency (EGP)
    source VARCHAR(50),  -- 'manual', 'central_bank_api', etc.
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, currency_code, rate_date, deleted_at)
);

CREATE INDEX idx_exchange_rates_tenant ON exchange_rates(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_exchange_rates_date ON exchange_rates(tenant_id, currency_code, rate_date) WHERE deleted_at IS NULL;

COMMENT ON TABLE exchange_rates IS 'Daily exchange rates for multi-currency transactions';
COMMENT ON COLUMN exchange_rates.rate_to_base IS 'Conversion rate to base currency (EGP) - stored as DECIMAL(10,6)';

-- ──────────────────────────────────────────────────────────────────────────
-- 4. VOUCHER & SECTION CODES (Vocher___Section_Code.xlsx)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS voucher_sections (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    voucher_code VARCHAR(3) NOT NULL,  -- '1'-'6', '999'
    section_code VARCHAR(3) NOT NULL,  -- '01', '02', '991'-'996'
    description_en VARCHAR(200) NOT NULL,
    description_ar VARCHAR(200),
    currency_code VARCHAR(2),  -- FK to currencies.code (NULL for voucher 999)
    section_type VARCHAR(20),  -- 'income', 'expense', 'settlement'
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, voucher_code, section_code, deleted_at)
);

CREATE INDEX idx_voucher_sections_tenant ON voucher_sections(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_voucher_sections_code ON voucher_sections(tenant_id, voucher_code, section_code) WHERE deleted_at IS NULL;

COMMENT ON TABLE voucher_sections IS 'Voucher codes map to currencies; sections split Income/Expense/Settlement';
COMMENT ON COLUMN voucher_sections.voucher_code IS 'Voucher 1-6 = currencies, 999 = settlements';
COMMENT ON COLUMN voucher_sections.section_code IS '01=Income, 02=Expense, 991-996=Settlements';

-- ──────────────────────────────────────────────────────────────────────────
-- 5. PARTNERS MASTER (Partners_Code.xlsx)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS partners (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,  -- FK to companies.code
    partner_code VARCHAR(10) NOT NULL,  -- PG01, PG02, PD01, etc.
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200),
    ownership_pct DECIMAL(5,2) NOT NULL,  -- 50.00 for 50%
    email VARCHAR(200),
    phone VARCHAR(50),
    address TEXT,
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, company_code, partner_code, deleted_at),
    CONSTRAINT chk_ownership_pct CHECK (ownership_pct >= 0 AND ownership_pct <= 100)
);

CREATE INDEX idx_partners_tenant ON partners(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_partners_company ON partners(tenant_id, company_code) WHERE deleted_at IS NULL;

COMMENT ON TABLE partners IS 'Partner equity stakes per company (typically 50/50 split)';
COMMENT ON COLUMN partners.ownership_pct IS 'Ownership percentage for profit distribution';

-- ──────────────────────────────────────────────────────────────────────────
-- 6. FINANCIAL PERIODS
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS financial_periods (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    company_code VARCHAR(2) NOT NULL,  -- FK to companies.code
    period_code VARCHAR(6) NOT NULL,  -- YYYYMM format (e.g., '202507')
    period_name VARCHAR(100),  -- 'July 2025'
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'open',  -- 'open', 'closed', 'locked'
    closed_by VARCHAR(20),
    closed_at TIMESTAMP,
    created_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(tenant_id, company_code, period_code, deleted_at),
    CONSTRAINT chk_period_status CHECK (status IN ('open', 'closed', 'locked'))
);

CREATE INDEX idx_financial_periods_tenant ON financial_periods(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_financial_periods_company ON financial_periods(tenant_id, company_code, period_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_financial_periods_status ON financial_periods(tenant_id, company_code, status) WHERE deleted_at IS NULL;

COMMENT ON TABLE financial_periods IS 'Financial periods per company - controls posting permissions';
COMMENT ON COLUMN financial_periods.status IS 'open=allow posting, closed=no posting, locked=no changes';

-- ══════════════════════════════════════════════════════════════════════════
-- SEED DATA: Companies (from Company_Code.xlsx)
-- ══════════════════════════════════════════════════════════════════════════
-- Note: tenant_id will be set by application during tenant provisioning
-- This is template data for reference

COMMENT ON TABLE companies IS 'SEED DATA TEMPLATE:
01 - Globalize Group (جلوباليز جروب) - Translation - Tax: 723603790 - E-Invoice: Yes
02 - Digitalize Business Solutions (ديجيتاليز) - Call Center
03 - Brandora
04 - Project Metric
05 - Jusor (جسور) - Translation
06 - شبكات';

-- ══════════════════════════════════════════════════════════════════════════
-- SEED DATA: Currencies (from Currency_Code.xlsx)
-- ══════════════════════════════════════════════════════════════════════════
COMMENT ON TABLE currencies IS 'SEED DATA TEMPLATE:
01 - EGP - Egyptian Pound (جنية مصري) - Base Currency
02 - USD - US Dollar (دولار أمريكي)
03 - AED - Emirati Dirham (درهم إماراتي)
04 - SAR - Saudi Riyal (ريال سعودي)
05 - EUR - Euro (يورو)
06 - GBP - Sterling (إسترليني)';

-- ══════════════════════════════════════════════════════════════════════════
-- SEED DATA: Voucher Sections (from Vocher___Section_Code.xlsx)
-- ══════════════════════════════════════════════════════════════════════════
COMMENT ON TABLE voucher_sections IS 'SEED DATA TEMPLATE:
Voucher 1 (EGP): 01=Income, 02=Expense
Voucher 2 (USD): 01=Income, 02=Expense
Voucher 3 (AED): 01=Income, 02=Expense
Voucher 4 (SAR): 01=Income, 02=Expense
Voucher 5 (EUR): 01=Income, 02=Expense
Voucher 6 (GBP): 01=Income, 02=Expense
Voucher 999 (Settlements): 991=EGP, 992=USD, 993=AED, 994=SAR, 995=EUR, 996=GBP';
