-- Migration 006: Add auth columns to users table
-- Requirements: 4.1, 4.2, 4.3, 4.4, 4.5
--
-- Adds last_login_at, failed_login_count, locked_until to support
-- JWT RS256 auth with bcrypt cost 12 and account lockout on repeated
-- failed attempts. password_hash already exists from migration 004.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS last_login_at       TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS failed_login_count  INT         NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS locked_until        TIMESTAMPTZ;

-- Index to speed up lockout checks
CREATE INDEX IF NOT EXISTS idx_users_locked_until
    ON users(locked_until)
    WHERE locked_until IS NOT NULL;
