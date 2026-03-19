-- Migration 001: Enable required PostgreSQL extensions
-- Run before any table creation

CREATE EXTENSION IF NOT EXISTS "pgcrypto";    -- gen_random_uuid()
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";   -- uuid_generate_v4() (legacy compat)
CREATE EXTENSION IF NOT EXISTS "vector";      -- pgvector for AI embeddings
CREATE EXTENSION IF NOT EXISTS "pg_trgm";     -- trigram indexes for fuzzy search
CREATE EXTENSION IF NOT EXISTS "unaccent";    -- accent-insensitive full-text search
