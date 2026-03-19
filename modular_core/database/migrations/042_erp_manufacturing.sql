-- Migration 042: Manufacturing and Bill of Materials (ERP)
-- Requirements: 26.1, 26.2, 26.3, 26.4, 26.5, 26.6, 26.7
-- Task 27.1: Create boms, bom_lines, work_orders table migrations

-- boms (Bill of Materials) table 
CREATE TABLE boms (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    parent_item_id      BIGINT NOT NULL REFERENCES inventory_items(id),  -- Finished Good
    
    -- BOM identification
    name                VARCHAR(255) NOT NULL,
    version             VARCHAR(20) NOT NULL DEFAULT 'v1',
    is_active           BOOLEAN NOT NULL DEFAULT true,
    
    -- Production settings
    min_production_qty  DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    production_lead_time_days INT DEFAULT 0,
    
    -- Costs
    total_est_cost      DECIMAL(15,2) DEFAULT 0.00,
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'EGP',
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, parent_item_id, version)
);

-- bom_lines table (multi-level support if linked to another BOM-item)
CREATE TABLE bom_lines (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    bom_id              BIGINT NOT NULL REFERENCES boms(id) ON DELETE CASCADE,
    line_no             SMALLINT NOT NULL,
    
    -- Component info
    item_id             BIGINT NOT NULL REFERENCES inventory_items(id),
    quantity            DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    uom                 VARCHAR(20) NOT NULL,
    
    -- Costing
    est_unit_cost       DECIMAL(15,2) DEFAULT 0.00,
    est_total_cost      DECIMAL(15,2) DEFAULT 0.00,
    
    -- Scrap factor
    scrap_pct           DECIMAL(5,2) DEFAULT 0.00,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- work_orders (Manufacturing order) table
CREATE TABLE work_orders (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    -- Order identification
    wo_no               VARCHAR(50) NOT NULL,
    wo_date             DATE NOT NULL,
    
    -- What to produce
    item_id             BIGINT NOT NULL REFERENCES inventory_items(id),
    bom_id              BIGINT NOT NULL REFERENCES boms(id),
    quantity            DECIMAL(15,4) NOT NULL,
    
    -- Schedule
    start_date          DATE NOT NULL,
    end_date            DATE NOT NULL,
    actual_end_date     DATE,
    
    -- Status
    status              VARCHAR(20) NOT NULL DEFAULT 'planned', -- planned|approved|in_progress|partially_completed|completed|cancelled
    
    -- Costing
    actual_cost         DECIMAL(15,2) DEFAULT 0.00,
    
    -- Source
    ref_table           VARCHAR(100), -- purchase_orders|pipeline_deals|etc
    ref_id              BIGINT,
    
    -- Location
    warehouse_id        BIGINT REFERENCES warehouses(id),
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    
    UNIQUE(tenant_id, company_code, wo_no)
);

-- work_order_components table (Tracked components reserved or consumed)
CREATE TABLE work_order_components (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    work_order_id       BIGINT NOT NULL REFERENCES work_orders(id) ON DELETE CASCADE,
    item_id             BIGINT NOT NULL REFERENCES inventory_items(id),
    
    planned_qty         DECIMAL(15,4) NOT NULL,
    consumed_qty        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    
    -- Universal columns
    created_by          BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ
);

-- Comments
COMMENT ON TABLE boms IS 'Bill of Materials defining recipes for producing finished products (Req 26.1, 26.2)';
COMMENT ON TABLE bom_lines IS 'Raw materials and components required per BOM (Req 26.2, 26.3)';
COMMENT ON TABLE work_orders IS 'Specific manufacturing batches with scheduling and status tracking (Req 26.5)';
COMMENT ON TABLE work_order_components IS 'Tracking of actual material consumption for costing (Req 26.6)';
