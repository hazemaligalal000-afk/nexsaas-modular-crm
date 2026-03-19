-- Migration 036: Expense Management and Accounts Payable
-- Requirements: 20.1, 20.2, 20.3, 20.4, 20.5, 20.6, 20.7
-- Task 21.1: Create expense_claims, purchase_orders, goods_receipts, supplier_invoices table migrations

-- expense_claims table
CREATE TABLE expense_claims (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    -- Claim identification
    claim_no            VARCHAR(50) NOT NULL,
    employee_id         BIGINT NOT NULL REFERENCES users(id),
    claim_date          DATE NOT NULL,
    
    -- Financial details
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    exchange_rate       DECIMAL(10,6) NOT NULL DEFAULT 1.000000,
    total_amount        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_amount_base   DECIMAL(15,2) NOT NULL DEFAULT 0.00,  -- Amount in EGP
    
    -- Status and approval
    status              VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft|submitted|approved|paid|rejected
    approver_id         BIGINT REFERENCES users(id),
    approved_at         TIMESTAMPTZ,
    rejection_reason    TEXT,
    
    -- Journal entry link
    journal_entry_id    BIGINT,
    
    -- Attachments and notes
    receipt_count       SMALLINT NOT NULL DEFAULT 0,
    notes               TEXT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, claim_no)
);

CREATE INDEX idx_expense_claims_tenant_company ON expense_claims(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_expense_claims_employee ON expense_claims(employee_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_expense_claims_status ON expense_claims(status) WHERE deleted_at IS NULL;

-- expense_claim_lines table
CREATE TABLE expense_claim_lines (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    claim_id            BIGINT NOT NULL REFERENCES expense_claims(id) ON DELETE CASCADE,
    line_no             SMALLINT NOT NULL,
    
    item_date           DATE NOT NULL,
    category            VARCHAR(100) NOT NULL,  -- travel|meals|supplies|etc
    description         TEXT NOT NULL,
    amount              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_amount          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Optional links
    account_code        VARCHAR(20),  -- Expense account
    cost_center_code    VARCHAR(20),
    project_id          BIGINT,
    
    -- Attachment
    receipt_path        VARCHAR(500),
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

CREATE INDEX idx_expense_line_claim ON expense_claim_lines(claim_id) WHERE deleted_at IS NULL;

-- purchase_orders table
CREATE TABLE purchase_orders (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    -- PO identification
    po_no               VARCHAR(50) NOT NULL,
    po_date             DATE NOT NULL,
    expected_delivery_date DATE,
    
    -- Supplier information
    vendor_id           BIGINT REFERENCES accounts(id),  -- Sharing accounts table for suppliers
    vendor_name         VARCHAR(255) NOT NULL,
    vendor_contact      VARCHAR(255),
    
    -- Financial details
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    exchange_rate       DECIMAL(10,6) NOT NULL DEFAULT 1.000000,
    subtotal            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_amount          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_amount        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Status
    status              VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft|submitted|approved|ordered|partially_received|fully_received|cancelled
    
    -- Links
    requisition_id      BIGINT,
    
    -- Notes
    notes               TEXT,
    terms               TEXT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, po_no)
);

CREATE INDEX idx_po_tenant_company ON purchase_orders(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_po_vendor ON purchase_orders(vendor_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_po_status ON purchase_orders(status) WHERE deleted_at IS NULL;

-- purchase_order_lines table
CREATE TABLE purchase_order_lines (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    po_id               BIGINT NOT NULL REFERENCES purchase_orders(id) ON DELETE CASCADE,
    line_no             SMALLINT NOT NULL,
    
    -- Item details
    item_id             BIGINT,  -- Link to inventory_items
    description         TEXT NOT NULL,
    quantity            DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    unit_price          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Received tracking
    received_qty        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    backorder_qty       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    
    -- Line totals
    line_total          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Accounting
    account_code        VARCHAR(20),
    cost_center_code    VARCHAR(20),
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- goods_receipts table (3-way matching - part 2)
CREATE TABLE goods_receipts (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    gr_no               VARCHAR(50) NOT NULL,
    gr_date             DATE NOT NULL,
    
    po_id               BIGINT REFERENCES purchase_orders(id),
    vendor_id           BIGINT REFERENCES accounts(id),
    
    delivery_note_ref   VARCHAR(100),
    warehouse_id        BIGINT,
    
    received_by         BIGINT REFERENCES users(id),
    status              VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft|finalized|cancelled
    
    notes               TEXT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, gr_no)
);

-- goods_receipt_lines table
CREATE TABLE goods_receipt_lines (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    gr_id               BIGINT NOT NULL REFERENCES goods_receipts(id) ON DELETE CASCADE,
    po_line_id          BIGINT REFERENCES purchase_order_lines(id),
    line_no             SMALLINT NOT NULL,
    
    item_id             BIGINT,
    description         TEXT NOT NULL,
    received_qty        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    
    -- Batch/Serial info
    batch_no            VARCHAR(50),
    expiry_date         DATE,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- supplier_invoices table (3-way matching - part 3)
CREATE TABLE supplier_invoices (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    -- Invoice identification
    invoice_no          VARCHAR(50) NOT NULL,  -- Supplier's invoice number
    internal_ref        VARCHAR(50) NOT NULL,  -- Our internal reference
    invoice_date        DATE NOT NULL,
    due_date            DATE NOT NULL,
    
    vendor_id           BIGINT NOT NULL REFERENCES accounts(id),
    po_id               BIGINT REFERENCES purchase_orders(id),
    
    -- Financial details
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    exchange_rate       DECIMAL(10,6) NOT NULL DEFAULT 1.000000,
    total_amount        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Matching status
    is_matched          BOOLEAN NOT NULL DEFAULT false,
    discrepancy_amount  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discrepancy_pct     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    
    -- Payment status
    payment_status      VARCHAR(20) NOT NULL DEFAULT 'unpaid', -- unpaid|partially_paid|paid
    paid_amount         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Journal link
    journal_entry_id    BIGINT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, internal_ref),
    UNIQUE(tenant_id, vendor_id, invoice_no) -- Supplier invoice no should be unique per vendor
);

-- supplier_invoice_lines table
CREATE TABLE supplier_invoice_lines (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    invoice_id          BIGINT NOT NULL REFERENCES supplier_invoices(id) ON DELETE CASCADE,
    gr_line_id          BIGINT REFERENCES goods_receipt_lines(id),
    line_no             SMALLINT NOT NULL,
    
    description         TEXT NOT NULL,
    quantity            DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    unit_price          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    account_code        VARCHAR(20),  -- AP account
    cost_center_code    VARCHAR(20),
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- Comments
COMMENT ON TABLE expense_claims IS 'Employee expense claims for reimbursement (Req 20.1, 20.2)';
COMMENT ON TABLE purchase_orders IS 'External orders issued to suppliers (Req 20.4, 20.5)';
COMMENT ON TABLE goods_receipts IS 'Records of goods received from suppliers (Req 20.5, 20.6)';
COMMENT ON TABLE supplier_invoices IS 'Invoices received from suppliers (Req 20.5, 20.7)';
