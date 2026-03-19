-- Migration 043: AI Engine Semantic Search
-- Requirements: 38.1, 38.2
-- Task 53.1: Create record_embeddings table migration with pgvector extension

CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE record_embeddings (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    
    -- Reference to source record
    record_table        VARCHAR(100) NOT NULL, -- contacts|leads|deals|accounts|invoices|projects
    record_id           BIGINT NOT NULL,
    
    -- 768-dim vector (Standard for Sentence-Transformers/BERT)
    embedding           vector(768) NOT NULL,
    
    -- Metadata for filtering
    content_hash        VARCHAR(64),
    
    -- Universal columns
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Index for similarity search
CREATE INDEX idx_record_embeddings_vector ON record_embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);

-- Filtering index
CREATE INDEX idx_record_embeddings_ref ON record_embeddings(tenant_id, record_table, record_id);

COMMENT ON TABLE record_embeddings IS 'AI-powered semantic embeddings for global search (Req 38.1, 38.2)';
