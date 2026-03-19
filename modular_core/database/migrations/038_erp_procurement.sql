-- Migration 038: Procurement Management
-- Requirements: 22.1, 22.2, 22.3, 22.4, 22.5, 22.6
-- Task 23.1: Create purchase_requisitions, rfqs, supplier_catalog table migrations

-- purchase_requisitions table
CREATE TABLE purchase_requisitions (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    -- Requisition identification
    pr_no               VARCHAR(50) NOT NULL,
    pr_date             DATE NOT NULL,
    priority            VARCHAR(10) NOT NULL DEFAULT 'medium',  -- low|medium|high|critical
    
    -- Department and employee
    department_id       BIGINT,
    requested_by        BIGINT NOT NULL REFERENCES users(id),
    
    -- Purpose and project
    purpose             TEXT,
    project_id          BIGINT,
    
    -- Workflow status
    status              VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft|submitted|approved|ordered|partially_ordered|cancelled|rejected
    
    -- Approval info
    approver_id         BIGINT REFERENCES users(id),
    approved_at         TIMESTAMPTZ,
    rejection_reason    TEXT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, pr_no)
);

-- purchase_requisition_lines table
CREATE TABLE purchase_requisition_lines (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    pr_id               BIGINT NOT NULL REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    line_no             SMALLINT NOT NULL,
    
    item_id             BIGINT,  -- Link to inventory_items
    description         TEXT NOT NULL,
    quantity            DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    
    -- Estimated cost
    est_unit_price      DECIMAL(15,2),
    est_total_price     DECIMAL(15,2),
    
    -- Order tracking
    ordered_qty         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    po_line_id          BIGINT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- rfqs (Request For Quotations) table
CREATE TABLE rfqs (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    rfq_no              VARCHAR(50) NOT NULL,
    rfq_date            DATE NOT NULL,
    closing_date        DATE NOT NULL,
    
    -- From PR
    pr_id               BIGINT REFERENCES purchase_requisitions(id),
    
    -- Status
    status              VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft|issued|received|awarded|closed|cancelled
    
    notes               TEXT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, rfq_no)
);

-- rfq_vendors table (links RFQ to potential vendors)
CREATE TABLE rfq_vendors (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    rfq_id              BIGINT NOT NULL REFERENCES rfqs(id) ON DELETE CASCADE,
    vendor_id           BIGINT NOT NULL REFERENCES accounts(id),
    
    -- Bid Info
    bid_received        BOOLEAN NOT NULL DEFAULT false,
    bid_date            DATE,
    bid_amount          DECIMAL(15,2),
    currency_code       VARCHAR(3),
    
    -- Awarded status
    is_awarded          BOOLEAN NOT NULL DEFAULT false,
    award_notes         TEXT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, rfq_id, vendor_id)
);

-- supplier_catalog table
CREATE TABLE supplier_catalog (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    vendor_id           BIGINT NOT NULL REFERENCES accounts(id),
    item_id             BIGINT NOT NULL REFERENCES inventory_items(id),
    
    -- Supplier's item identification
    vendor_sku          VARCHAR(50),
    vendor_item_name    VARCHAR(255),
    
    -- Pricing
    cost_price          DECIMAL(15,2) NOT NULL,
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    
    -- Terms
    min_order_qty       DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    lead_time_days      SMALLINT,
    
    -- History
    last_price_update   DATE,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, vendor_id, item_id)
);

-- Comments
COMMENT ON TABLE purchase_requisitions IS 'Internal requests for purchasing goods or services (Req 22.1, 22.3)';
COMMENT ON TABLE rfqs IS 'Requests for quotations issued to multiple suppliers (Req 22.5)';
COMMENT ON TABLE supplier_catalog IS 'Master pricing and catalogs provided by vendors (Req 22.2)';
