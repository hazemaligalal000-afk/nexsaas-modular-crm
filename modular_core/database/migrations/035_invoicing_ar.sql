-- Migration 035: Invoicing and Accounts Receivable
-- Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 19.6, 19.7, 19.8
-- Task 20.1: Create invoices, invoice_lines, payments table migrations

-- invoices table
CREATE TABLE invoices (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    -- Invoice identification
    invoice_no          VARCHAR(50) NOT NULL,
    invoice_prefix      VARCHAR(20) NOT NULL DEFAULT 'INV',
    invoice_sequence    INT NOT NULL,
    
    -- Customer information
    account_id          BIGINT REFERENCES accounts(id),
    customer_name       VARCHAR(255) NOT NULL,
    customer_email      VARCHAR(255),
    billing_address     JSONB,
    
    -- Financial details
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    exchange_rate       DECIMAL(10,6) NOT NULL DEFAULT 1.000000,
    
    -- Line item totals
    subtotal            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_amount          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_amount     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_amount        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Payment tracking
    paid_amount         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    outstanding_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Dates
    invoice_date        DATE NOT NULL,
    due_date            DATE NOT NULL,
    
    -- Status tracking
    status              VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft|finalized|sent|partially_paid|paid|overdue|cancelled
    is_overdue          BOOLEAN NOT NULL DEFAULT false,
    
    -- PDF and email tracking
    pdf_path            VARCHAR(500),
    sent_at             TIMESTAMPTZ,
    sent_to             VARCHAR(255),
    
    -- Recurring invoice support
    is_recurring        BOOLEAN NOT NULL DEFAULT false,
    recurring_schedule_id BIGINT,
    
    -- Journal entry link
    journal_entry_id    BIGINT,
    
    -- Notes
    notes               TEXT,
    terms_and_conditions TEXT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, invoice_no)
);

CREATE INDEX idx_invoices_tenant_company ON invoices(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_invoices_account ON invoices(account_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_invoices_status ON invoices(status) WHERE deleted_at IS NULL;
CREATE INDEX idx_invoices_due_date ON invoices(due_date) WHERE deleted_at IS NULL AND status NOT IN ('paid', 'cancelled');
CREATE INDEX idx_invoices_overdue ON invoices(is_overdue) WHERE deleted_at IS NULL AND is_overdue = true;

-- invoice_lines table
CREATE TABLE invoice_lines (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    invoice_id          BIGINT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    line_no             SMALLINT NOT NULL,
    
    -- Item details
    description         TEXT NOT NULL,
    quantity            DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    unit_price          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Pricing
    line_subtotal       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_pct        DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    discount_amount     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_rate            DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tax_amount          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Optional links
    inventory_item_id   BIGINT,
    account_code        VARCHAR(20),  -- Revenue account
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

CREATE INDEX idx_invoice_lines_invoice ON invoice_lines(invoice_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_invoice_lines_tenant_company ON invoice_lines(tenant_id, company_code) WHERE deleted_at IS NULL;

-- payments table
CREATE TABLE payments (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    -- Payment identification
    payment_no          VARCHAR(50) NOT NULL,
    payment_reference   VARCHAR(100),
    
    -- Payment details
    payment_date        DATE NOT NULL,
    amount              DECIMAL(15,2) NOT NULL,
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    exchange_rate       DECIMAL(10,6) NOT NULL DEFAULT 1.000000,
    amount_base         DECIMAL(15,2) NOT NULL,  -- Amount in EGP
    
    -- Payment method
    payment_method      VARCHAR(50) NOT NULL,  -- cash|bank_transfer|credit_card|stripe|check
    
    -- Bank/card details
    bank_account_id     BIGINT,
    check_number        VARCHAR(50),
    card_last_four      VARCHAR(4),
    
    -- Stripe integration
    stripe_payment_intent_id VARCHAR(100),
    stripe_charge_id    VARCHAR(100),
    
    -- Customer information
    account_id          BIGINT REFERENCES accounts(id),
    customer_name       VARCHAR(255),
    
    -- Allocation tracking
    allocated_amount    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    unallocated_amount  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Status
    status              VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending|cleared|reconciled|failed
    
    -- Journal entry link
    journal_entry_id    BIGINT,
    
    -- Notes
    notes               TEXT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, payment_no)
);

CREATE INDEX idx_payments_tenant_company ON payments(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_payments_account ON payments(account_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_payments_date ON payments(payment_date) WHERE deleted_at IS NULL;
CREATE INDEX idx_payments_stripe_intent ON payments(stripe_payment_intent_id) WHERE deleted_at IS NULL;

-- payment_allocations table (links payments to invoices)
CREATE TABLE payment_allocations (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    payment_id          BIGINT NOT NULL REFERENCES payments(id) ON DELETE CASCADE,
    invoice_id          BIGINT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    
    allocated_amount    DECIMAL(15,2) NOT NULL,
    allocation_date     DATE NOT NULL,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

CREATE INDEX idx_payment_allocations_payment ON payment_allocations(payment_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_payment_allocations_invoice ON payment_allocations(invoice_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_payment_allocations_tenant_company ON payment_allocations(tenant_id, company_code) WHERE deleted_at IS NULL;

-- recurring_invoice_schedules table
CREATE TABLE recurring_invoice_schedules (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    -- Template invoice
    template_name       VARCHAR(255) NOT NULL,
    account_id          BIGINT REFERENCES accounts(id),
    
    -- Schedule configuration
    frequency           VARCHAR(20) NOT NULL,  -- daily|weekly|monthly|quarterly|yearly
    interval            SMALLINT NOT NULL DEFAULT 1,  -- Every N periods
    start_date          DATE NOT NULL,
    end_date            DATE,
    next_run_date       DATE NOT NULL,
    
    -- Invoice template data
    invoice_prefix      VARCHAR(20) NOT NULL DEFAULT 'INV',
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    payment_terms_days  SMALLINT NOT NULL DEFAULT 30,
    
    -- Line items (stored as JSON)
    line_items          JSONB NOT NULL,
    
    -- Status
    is_active           BOOLEAN NOT NULL DEFAULT true,
    last_generated_at   TIMESTAMPTZ,
    last_invoice_id     BIGINT,
    
    -- Notes
    notes               TEXT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

CREATE INDEX idx_recurring_schedules_tenant_company ON recurring_invoice_schedules(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_recurring_schedules_next_run ON recurring_invoice_schedules(next_run_date) WHERE deleted_at IS NULL AND is_active = true;
CREATE INDEX idx_recurring_schedules_account ON recurring_invoice_schedules(account_id) WHERE deleted_at IS NULL;

-- Comments
COMMENT ON TABLE invoices IS 'Customer invoices for accounts receivable (Req 19.1, 19.2)';
COMMENT ON TABLE invoice_lines IS 'Line items for invoices with quantities, prices, taxes, and discounts (Req 19.1)';
COMMENT ON TABLE payments IS 'Customer payments against invoices (Req 19.4, 19.7, 19.8)';
COMMENT ON TABLE payment_allocations IS 'Allocation of payments to specific invoices for partial payment support (Req 19.4)';
COMMENT ON TABLE recurring_invoice_schedules IS 'Recurring invoice schedules for automatic invoice generation (Req 19.6)';
