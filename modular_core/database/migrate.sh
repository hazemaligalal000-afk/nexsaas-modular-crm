#!/usr/bin/env bash
# NexSaaS database migration runner
# Usage: ./modular_core/database/migrate.sh [--host HOST] [--db DB] [--user USER]
set -euo pipefail

DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-nexsaas}"
DB_USER="${DB_USER:-nexsaas}"
MIGRATIONS_DIR="$(dirname "$0")/migrations"

echo "Running migrations against ${DB_HOST}:${DB_PORT}/${DB_NAME} as ${DB_USER}"

# Create migrations tracking table if it doesn't exist
psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" <<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    id          SERIAL PRIMARY KEY,
    filename    VARCHAR(255) UNIQUE NOT NULL,
    applied_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
SQL

# Apply each migration file in order, skipping already-applied ones
for migration in "$MIGRATIONS_DIR"/*.sql; do
    filename=$(basename "$migration")
    already_applied=$(psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" \
        -tAc "SELECT COUNT(*) FROM schema_migrations WHERE filename = '$filename'")

    if [ "$already_applied" -eq "0" ]; then
        echo "  Applying: $filename"
        psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f "$migration"
        psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" \
            -c "INSERT INTO schema_migrations (filename) VALUES ('$filename')"
        echo "  Applied:  $filename"
    else
        echo "  Skipped:  $filename (already applied)"
    fi
done

echo "Migrations complete."
