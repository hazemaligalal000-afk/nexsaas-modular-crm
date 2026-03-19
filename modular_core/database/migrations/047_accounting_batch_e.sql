-- Migration 047: Bank and Cash Management (Batch E)
-- Task 33.1: CREATE petty_cash_funds, cash_calls, bank_transactions table migrations

CREATE TABLE petty_cash_funds (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    fund_name           VARCHAR(100) NOT NULL,
    custodian_user_id   BIGINT NOT NULL,
    currency            VARCHAR(3) NOT NULL DEFAULT 'EGP',
    fund_limit          DECIMAL(15, 2) NOT NULL,
    current_balance     DECIMAL(15, 2) NOT NULL DEFAULT 0,
    coa_account_code    VARCHAR(50),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE cash_calls (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    call_number         VARCHAR(50) NOT NULL,
    partner_code        VARCHAR(50) NOT NULL,
    call_date           DATE NOT NULL,
    due_date            DATE NOT NULL,
    currency            VARCHAR(3) NOT NULL DEFAULT 'USD',
    amount              DECIMAL(15, 2) NOT NULL,
    amount_received     DECIMAL(15, 2) NOT NULL DEFAULT 0,
    status              VARCHAR(20) DEFAULT 'issued', -- issued, partially_received, fulfilled
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE bank_transactions (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    bank_account_id     BIGINT NOT NULL REFERENCES bank_accounts(id),
    transaction_date    DATE NOT NULL,
    transaction_type    VARCHAR(20) NOT NULL, -- deposit, withdrawal, transfer, fee, interest
    amount              DECIMAL(15, 2) NOT NULL,
    currency            VARCHAR(3) NOT NULL,
    description         TEXT,
    reference_number    VARCHAR(100),
    is_reconciled       BOOLEAN DEFAULT FALSE,
    reconciliation_id   BIGINT,
    journal_entry_id    BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE bank_reconciliations (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    bank_account_id     BIGINT NOT NULL REFERENCES bank_accounts(id),
    statement_date      DATE NOT NULL,
    statement_balance   DECIMAL(15, 2) NOT NULL,
    book_balance        DECIMAL(15, 2) NOT NULL,
    uncleared_deposits  DECIMAL(15, 2) NOT NULL DEFAULT 0,
    uncleared_payments  DECIMAL(15, 2) NOT NULL DEFAULT 0,
    reconciled_difference DECIMAL(15, 2) NOT NULL,
    status              VARCHAR(20) DEFAULT 'draft', -- draft, matched, posted
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE petty_cash_funds IS 'Master table for petty cash issuance and custodian assignments (Req 49.2)';
COMMENT ON TABLE cash_calls IS 'Tracks cash calls requested from partners per company code (Req 49.3)';
COMMENT ON TABLE bank_transactions IS 'Detailed ledger mapping directly to specific bank accounts including wallets (Req 49.1)';
COMMENT ON TABLE bank_reconciliations IS 'Records the outcome of CSV bank statement imports and matching variance (Req 49.5, 49.6)';
