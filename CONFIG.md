# NexSaaS CRM — Configuration Guide (CONFIG.md)
# (Requirement 3.5: Configuration Documentation)

This document defines the configuration rules for the NexSaaS Platform. All configurations MUST come from environment variables.

## ─── Required Configuration ───

| Variable | Description | Requirement | Validation |
| :--- | :--- | :--- | :--- |
| `APP_MASTER_KEY` | Symmetric key for database field encryption. | **CRITICAL** | Must be 64-character hex or 32-byte base64 (Requirement 12.6). |
| `DB_NAME` | Main PostgreSQL database name. | **REQUIRED** | Alphanumeric only. |
| `DB_USER` | PostgreSQL username. | **REQUIRED** | Valid DB user. |
| `DB_PASSWORD` | PostgreSQL password. | **REQUIRED** | Non-empty string. |

## ─── Optional / Feature Configuration ───

| Variable | Description | Default | Rule |
| :--- | :--- | :--- | :--- |
| `REDIS_HOST` | Host for rate limiting and broadcast. | `127.0.0.1` | Used only if Rate Limiting is enabled (Requirement 10.3). |
| `OPENAI_API_KEY` | Key for LLM drafting services. | `null` | Required for Content Generation (Requirement 2.2). |
| `STRIPE_SECRET_KEY`| Key for subscription billing. | `null` | Required for Billing Controller (Requirement 11.2). |

## ─── Validation Rules ───

1. **No Code Hardcoding**: All credentials MUST be loaded via the `ConfigLoader` service. (Requirement 2.1-2.7)
2. **Missing Variables**: The system will throw a `ConfigVariableMissingException` if a required variable is not set. (Requirement 2.5)
3. **Format Enforcement**: Boolean values must be `true` or `false`. Integers must be digits only.

## ─── 🔑 Security Best Practices ───

- **Never** commit your ACTUAL `.env` file. (Requirement 4.1)
- Rotate your `APP_MASTER_KEY` every 6 months using the rotation service. (Requirement 12.9)
- Use separate keys for Production and Sandbox environments. (Phase 0.1)
