-- Migration 037: Inventory and Warehouse Management
-- Requirements: 21.1, 21.2, 21.3, 21.5, 21.7
-- Task 22.1: Create inventory_items, warehouses, stock_ledger, inventory_stock table migrations

-- categories table for items
CREATE TABLE inventory_categories (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    name                VARCHAR(100) NOT NULL,
    parent_id           BIGINT REFERENCES inventory_categories(id),
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, name)
);

-- inventory_items table
CREATE TABLE inventory_items (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    sku                 VARCHAR(50) NOT NULL,
    name                VARCHAR(255) NOT NULL,
    description         TEXT,
    category_id         BIGINT REFERENCES inventory_categories(id),
    
    uom                 VARCHAR(20) NOT NULL,  -- unit of measure: piece|kg|liter|etc
    
    -- Item type
    item_type           VARCHAR(20) NOT NULL DEFAULT 'stock',  -- stock|service|fixed_asset|consumable
    
    -- Product details
    brand               VARCHAR(100),
    model               VARCHAR(100),
    
    -- Stock settings
    reorder_point       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    min_stock           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    max_stock           DECIMAL(15,4),
    
    -- Financials
    base_cost           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    base_price          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    
    -- Accounting
    income_account_code VARCHAR(20),
    expense_account_code VARCHAR(20),
    inventory_account_code VARCHAR(20),
    
    -- Item tracking attributes
    requires_serial_no  BOOLEAN NOT NULL DEFAULT false,
    requires_batch_no   BOOLEAN NOT NULL DEFAULT false,
    has_expiry          BOOLEAN NOT NULL DEFAULT false,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, sku)
);

CREATE INDEX idx_items_tenant_company ON inventory_items(tenant_id, company_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_items_sku ON inventory_items(sku) WHERE deleted_at IS NULL;

-- warehouses table
CREATE TABLE warehouses (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    name                VARCHAR(100) NOT NULL,
    location            VARCHAR(255),
    manager_id          BIGINT REFERENCES users(id),
    
    is_active           BOOLEAN NOT NULL DEFAULT true,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, name)
);

-- inventory_stock table (quantities per warehouse)
CREATE TABLE inventory_stock (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    item_id             BIGINT NOT NULL REFERENCES inventory_items(id) ON DELETE CASCADE,
    warehouse_id        BIGINT NOT NULL REFERENCES warehouses(id) ON DELETE CASCADE,
    
    on_hand_qty         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    reserved_qty        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    available_qty       DECIMAL(15,4) GENERATED ALWAYS AS (on_hand_qty - reserved_qty) STORED,
    
    -- Valuation tracking
    weighted_avg_cost   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_value         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, item_id, warehouse_id)
);

-- stock_ledger table (immutable movement log)
CREATE TABLE stock_ledger (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    item_id             BIGINT NOT NULL REFERENCES inventory_items(id),
    warehouse_id        BIGINT NOT NULL REFERENCES warehouses(id),
    
    movement_type       VARCHAR(20) NOT NULL,  -- purchase|sale|adjustment|transfer_in|transfer_out|return|stocktake
    movement_date       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    qty_change          DECIMAL(15,4) NOT NULL,
    qty_after           DECIMAL(15,4) NOT NULL,
    
    unit_cost           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_cost          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Reference documents
    ref_table           VARCHAR(100),  -- supplier_invoices|invoice_lines|etc
    ref_id              BIGINT,
    
    -- Tracking details
    batch_no            VARCHAR(50),
    serial_no           VARCHAR(100),
    
    -- Journal link (if applicable)
    journal_entry_id    BIGINT,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
    -- NO updated_at, deleted_at — immutable
);

CREATE INDEX idx_stock_ledger_item ON stock_ledger(item_id, warehouse_id);
CREATE INDEX idx_stock_ledger_date ON stock_ledger(movement_date);

-- Comments
COMMENT ON TABLE inventory_items IS 'Master list of products and services (Req 21.1)';
COMMENT ON TABLE warehouses IS 'Storage locations for stock items (Req 21.2)';
COMMENT ON TABLE inventory_stock IS 'Real-time stock quantities and valuation per warehouse (Req 21.3)';
COMMENT ON TABLE stock_ledger IS 'Immutable log of every stock movement for audit trail (Req 21.3, 21.7)';
