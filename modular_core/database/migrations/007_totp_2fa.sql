-- Migration 007: TOTP 2FA columns and backup codes table
-- Requirements: 4.6, 33.1, 33.2, 33.3, 33.4, 33.5
--
-- Adds totp_enabled and totp_verified_at to users table.
-- (totp_secret already exists from migration 004)
-- Creates user_backup_codes table for single-use backup codes.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS totp_enabled     BOOLEAN   NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS totp_verified_at TIMESTAMPTZ;

-- Backup codes: one row per code, soft-deleted on use
CREATE TABLE IF NOT EXISTS user_backup_codes (
    id           BIGSERIAL    PRIMARY KEY,
    company_code VARCHAR(2)   NOT NULL,
    tenant_id    UUID         NOT NULL,
    user_id      BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash    VARCHAR(255) NOT NULL,   -- bcrypt hash of the raw 10-hex-char code
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at   TIMESTAMPTZ             -- NULL = active; set to NOW() when used
);

CREATE INDEX IF NOT EXISTS idx_backup_codes_user
    ON user_backup_codes(tenant_id, user_id)
    WHERE deleted_at IS NULL;
