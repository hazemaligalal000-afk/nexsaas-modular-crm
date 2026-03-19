-- Migration 046: Accounts Receivable, Accounts Payable, and Accruals (Batch D)
-- Task 32.1: CREATE ar_invoices, ap_bills, payments, payment_allocations, accruals table migrations

CREATE TABLE ap_bills (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    vendor_code         VARCHAR(50) NOT NULL,
    bill_number         VARCHAR(50) NOT NULL,
    bill_date           DATE NOT NULL,
    due_date            DATE NOT NULL,
    currency            VARCHAR(3) NOT NULL DEFAULT 'EGP',
    subtotal            DECIMAL(15, 2) NOT NULL,
    tax_amount          DECIMAL(15, 2) DEFAULT 0,
    withholding_tax     DECIMAL(15, 2) DEFAULT 0,
    retention_amount    DECIMAL(15, 2) DEFAULT 0,
    total_amount        DECIMAL(15, 2) NOT NULL,
    amount_paid         DECIMAL(15, 2) DEFAULT 0,
    status              VARCHAR(20) DEFAULT 'draft', -- draft, open, partially_paid, paid, cancelled
    fin_period          VARCHAR(6) NOT NULL, -- YYYYMM
    contract_reference  VARCHAR(100),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Note: ar_invoices might exist from 035_invoicing_ar.sql, but we ensure it meets Batch D requirements.
CREATE TABLE IF NOT EXISTS ar_invoices (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    customer_code       VARCHAR(50) NOT NULL,
    invoice_number      VARCHAR(50) NOT NULL,
    invoice_date        DATE NOT NULL,
    due_date            DATE NOT NULL,
    currency            VARCHAR(3) NOT NULL DEFAULT 'EGP',
    subtotal            DECIMAL(15, 2) NOT NULL,
    tax_amount          DECIMAL(15, 2) DEFAULT 0,
    total_amount        DECIMAL(15, 2) NOT NULL,
    amount_paid         DECIMAL(15, 2) DEFAULT 0,
    status              VARCHAR(20) DEFAULT 'draft', -- draft, open, partially_paid, paid, cancelled
    fin_period          VARCHAR(6) NOT NULL,
    eta_uuid            VARCHAR(255), -- For Egyptian Tax Authority E-Invoice
    eta_status          VARCHAR(50),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE payments (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    payment_type        VARCHAR(10) NOT NULL, -- AR (Receipt) or AP (Disbursement)
    partner_code        VARCHAR(50) NOT NULL, -- vendor_code or customer_code
    payment_date        DATE NOT NULL,
    payment_method      VARCHAR(50) NOT NULL, -- bank_transfer, cash, check
    bank_account_id     BIGINT,
    currency            VARCHAR(3) NOT NULL DEFAULT 'EGP',
    amount              DECIMAL(15, 2) NOT NULL,
    unallocated_amount  DECIMAL(15, 2) NOT NULL,
    reference_number    VARCHAR(100),
    status              VARCHAR(20) DEFAULT 'posted',
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE payment_allocations (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    payment_id          BIGINT NOT NULL REFERENCES payments(id),
    document_type       VARCHAR(20) NOT NULL, -- ar_invoice, ap_bill
    document_id         BIGINT NOT NULL,
    allocated_amount    DECIMAL(15, 2) NOT NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE accruals (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    accrual_type        VARCHAR(20) NOT NULL, -- revenue, expense
    description         VARCHAR(255) NOT NULL,
    amount              DECIMAL(15, 2) NOT NULL,
    fin_period          VARCHAR(6) NOT NULL,
    journal_entry_id    BIGINT, -- The original entry
    reversal_entry_id   BIGINT, -- The auto-reversal entry in the next period
    status              VARCHAR(20) DEFAULT 'active', -- active, reversed
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE ap_bills IS 'Accounts Payable vendor bills including withholding tax and retention tracking (Batch D)';
COMMENT ON TABLE ar_invoices IS 'Accounts Receivable customer invoices supporting ETA E-Invoicing';
COMMENT ON TABLE payments IS 'Centralized ledger for customer receipts and vendor disbursements';
COMMENT ON TABLE payment_allocations IS 'Mapping table dividing a single payment across multiple invoices or bills';
COMMENT ON TABLE accruals IS 'Period-end accrual entries that require auto-reversal at the start of the next period';
