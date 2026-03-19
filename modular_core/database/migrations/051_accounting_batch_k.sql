-- Migration 051: Tax and Compliance (Batch K)
-- Task 39.1 - 39.4: CREATE compliance_documents, tax_filing_calendar, vat_ledger

CREATE TABLE compliance_documents (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    document_type       VARCHAR(50) NOT NULL, -- tax_card, commercial_registry
    document_number     VARCHAR(100) NOT NULL,
    issue_date          DATE NOT NULL,
    expiry_date         DATE NOT NULL,
    status              VARCHAR(20) DEFAULT 'active', -- active, expired, suspended
    file_path           TEXT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE tax_filing_calendar (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    tax_type            VARCHAR(50) NOT NULL, -- vat, withholding, corporate_income, social_insurance
    fin_period          VARCHAR(6) NOT NULL,
    filing_due_date     DATE NOT NULL,
    filing_status       VARCHAR(20) DEFAULT 'pending', -- pending, filed, paid, overdue
    filed_on            DATE,
    amount_paid         DECIMAL(15, 2),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE vat_ledger (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    document_type       VARCHAR(20) NOT NULL, -- ar_invoice, ap_bill
    document_id         BIGINT NOT NULL,
    fin_period          VARCHAR(6) NOT NULL,
    base_amount         DECIMAL(15, 2) NOT NULL,
    vat_rate            DECIMAL(5, 2) NOT NULL,
    vat_amount          DECIMAL(15, 2) NOT NULL,
    vat_type            VARCHAR(10) NOT NULL, -- input (AP), output (AR)
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE compliance_documents IS 'Tracks statutory document expiries for Owner/Admin alerts (Req 55.6)';
COMMENT ON TABLE tax_filing_calendar IS 'Egyptian compliance timeline tracking per company code (Req 55.7)';
COMMENT ON TABLE vat_ledger IS 'Line by line VAT tracking required for Egyptian VAT report Form 10 (Req 55.4)';
