-- Combined Migration 044: Accounting Modules (Bank, Cost Centers, Fixed Assets, Partner, Tax)
-- Requirements: 47.1, 48.1, 49.1, 50.1, 51.1, 53.1
-- Tasks: 31-41

CREATE TABLE exchange_rates (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    currency_code       VARCHAR(3) NOT NULL,
    rate_date           DATE NOT NULL,
    rate_to_egp         DECIMAL(15, 6) NOT NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(tenant_id, company_code, currency_code, rate_date)
);

CREATE TABLE bank_accounts (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    account_name        VARCHAR(100) NOT NULL,
    account_number      VARCHAR(50),
    bank_name           VARCHAR(100),
    currency            VARCHAR(3) NOT NULL DEFAULT 'EGP',
    coa_account_code    VARCHAR(50),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE cost_centers (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    parent_id           BIGINT REFERENCES cost_centers(id),
    code                VARCHAR(20) NOT NULL,
    name                VARCHAR(100) NOT NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE fixed_assets (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    asset_name          VARCHAR(100) NOT NULL,
    category            VARCHAR(50) NOT NULL,
    purchase_price      DECIMAL(15, 2) NOT NULL,
    salvage_value       DECIMAL(15, 2) DEFAULT 0,
    useful_life_months  INT NOT NULL,
    purchase_date       DATE NOT NULL,
    status              VARCHAR(20) DEFAULT 'active', -- active, retired, sold
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE partners (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    partner_code        VARCHAR(20) NOT NULL,
    partner_name        VARCHAR(100) NOT NULL,
    share_pct           DECIMAL(5, 2) NOT NULL,
    withdrawal_approval_threshold DECIMAL(15, 2),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE exchange_rates IS 'Stores daily exchange rates vs EGP';
COMMENT ON TABLE bank_accounts IS 'Master table for all bank accounts, petty cash, and wallets';
COMMENT ON TABLE cost_centers IS 'Hierarchy of cost centers for project/departmental accounting';
COMMENT ON TABLE fixed_assets IS 'Fixed asset register for automated depreciation';
COMMENT ON TABLE partners IS 'Partner tracking for automated profit distribution';
