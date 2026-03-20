# Design Document: Security & Legal Foundation

## Overview

This design document specifies the implementation approach for Phase 0: Security & Legal Foundation of the NexSaaS platform. This phase establishes critical security hardening and legal compliance foundations that must be completed before any customer-facing work, sales activities, or customer demos.

The design addresses three core areas:

1. **Configuration Security Hardening**: Removing credentials from git history and implementing environment-based configuration management
2. **AGPL Licensing Strategy & Legal Documentation**: Creating comprehensive legal framework for commercial operations
3. **Core Security Hardening**: Implementing HTTPS enforcement, rate limiting, 2FA, encryption, audit logging, CSP, and vulnerability assessment

This phase is foundational and blocking for all subsequent phases. Without proper security hardening and legal documentation, the platform cannot be safely deployed to production or offered to customers.

### Design Goals

- Eliminate all credential exposure risks from version control
- Establish secure configuration management using environment variables
- Provide comprehensive legal documentation for AGPL compliance and commercial licensing
- Implement industry-standard security controls (HTTPS, rate limiting, 2FA, encryption)
- Create audit trail for security-relevant events
- Protect against common web vulnerabilities (XSS, CSRF, SQL injection)
- Establish security assessment baseline using OWASP ZAP

### Non-Goals

- Advanced threat detection or SIEM integration (future phase)
- Penetration testing beyond automated OWASP ZAP scanning
- SOC 2 or ISO 27001 compliance (future phase)
- Advanced encryption key management (HSM, KMS) - using environment-based master keys
- Multi-region compliance (CCPA, HIPAA) beyond GDPR baseline

## Architecture

### High-Level Architecture


The security and legal foundation integrates into the existing NexSaaS architecture at multiple layers:

```
┌─────────────────────────────────────────────────────────────────┐
│                         Client Layer                             │
│  (Browser with CSP enforcement, HTTPS-only communication)        │
└────────────────────────┬────────────────────────────────────────┘
                         │ HTTPS (TLS 1.2+)
                         │ Rate Limited
┌────────────────────────▼────────────────────────────────────────┐
│                    Web Server Layer                              │
│  • HTTPS Redirect Middleware                                     │
│  • HSTS Header Injection                                         │
│  • CSP Header Injection                                          │
│  • Rate Limiting Middleware (Redis-backed)                       │
└────────────────────────┬────────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────────┐
│                  Application Layer                               │
│  • Environment Config Loader (replaces hardcoded config)         │
│  • 2FA Service (TOTP generation/validation)                      │
│  • Encryption Service (AES-256-GCM)                              │
│  • Audit Logger (security event recording)                       │
│  • Input Validation & Sanitization                               │
│  • CSRF Token Management                                         │
└────────────────────────┬────────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────────┐
│                    Data Layer                                    │
│  • Encrypted sensitive fields (passwords, API keys, tokens)      │
│  • Audit log storage (tamper-evident)                            │
│  • Parameterized queries (SQL injection prevention)              │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│                    Legal Documentation                            │
│  • LICENSING_STRATEGY.md (AGPL compliance approach)              │
│  • COMMERCIAL_LICENSE.md (proprietary license terms)             │
│  • TERMS_OF_SERVICE.md (hosted service legal terms)              │
│  • PRIVACY_POLICY.md (GDPR-compliant data handling)              │
└──────────────────────────────────────────────────────────────────┘
```

### Security Layers

The design implements defense-in-depth with multiple security layers:

1. **Transport Security**: HTTPS enforcement, TLS 1.2+, HSTS headers
2. **Access Control**: Rate limiting, 2FA, session management
3. **Data Protection**: Field-level encryption, secure password hashing
4. **Audit & Monitoring**: Comprehensive security event logging
5. **Input Validation**: XSS prevention, SQL injection prevention, CSRF protection
6. **Content Security**: CSP headers to prevent XSS attacks

### Configuration Security Architecture


The configuration system transitions from file-based configuration to environment-based configuration:

**Before (Insecure)**:
```
modular_core/
  config/
    config.inc.php  ← Contains hardcoded credentials
    config.db.php   ← Contains database passwords
```

**After (Secure)**:
```
.env                     ← Local environment file (gitignored)
.env.example             ← Template with placeholders
modular_core/
  bootstrap/
    ConfigLoader.php     ← Loads from environment variables
```

All sensitive configuration is loaded from environment variables at runtime. The git history is cleaned to remove all traces of credential files.

## Components and Interfaces

### 1. Configuration Security Components

#### 1.1 Git History Cleaner

**Purpose**: Remove all traces of credential files from git history

**Implementation Approach**:
- Use `git filter-repo` (preferred) or BFG Repo-Cleaner
- Target files: `config.inc.php`, `config.db.php`
- Create backup before history rewrite
- Document process in `SECURITY_CLEANUP.md`

**Script**: `scripts/clean_git_history.sh`

```bash
#!/bin/bash
# Removes credential files from git history
# Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6

git filter-repo --path config.inc.php --invert-paths
git filter-repo --path config.db.php --invert-paths
```

#### 1.2 ConfigLoader Service

**Purpose**: Load all configuration from environment variables

**Interface**:
```php
class ConfigLoader
{
    /**
     * Load a required environment variable
     * @throws ConfigException if variable is missing or empty
     */
    public static function getRequired(string $key): string;
    
    /**
     * Load an optional environment variable with default
     */
    public static function get(string $key, ?string $default = null): ?string;
    
    /**
     * Load all database configuration
     * @return array{host: string, port: int, name: string, user: string, password: string}
     */
    public static function getDatabaseConfig(): array;
    
    /**
     * Load all encryption configuration
     * @return array{master_key: string, algorithm: string}
     */
    public static function getEncryptionConfig(): array;
}
```

**Requirements Mapping**:
- Req 2.1-2.4: Load credentials from environment variables
- Req 2.5-2.6: Throw descriptive errors for missing/empty variables
- Req 2.7: No hardcoded fallback values for credentials
- Req 2.8: Support .env file loading in development



### 2. Legal Documentation Components

#### 2.1 LICENSING_STRATEGY.md

**Purpose**: Document AGPL compliance approach and commercial licensing strategy

**Required Sections**:
- AGPL Obligations Overview (Req 5.2)
- Commercial Approach Decision (Req 5.3)
- Source Code Availability Policy (Req 5.4)
- Commercial License Purchase Process (Req 5.5)
- AGPL Dependency Inventory (Req 5.6)
- Core Rewrite Roadmap (if applicable) (Req 5.7)

#### 2.2 COMMERCIAL_LICENSE.md

**Purpose**: Provide proprietary license terms for customers who purchase commercial licenses

**Required Sections**:
- License Grant and Permitted Uses (Req 6.2)
- Redistribution Restrictions (Req 6.3)
- Warranty Disclaimers (Req 6.4)
- Liability Limitations (Req 6.5)
- License Fee Structure (Req 6.6)
- License Term and Renewal (Req 6.7)

#### 2.3 TERMS_OF_SERVICE.md

**Purpose**: Legal terms for hosted service customers

**Required Sections**:
- Acceptable Use Policies (Req 7.2)
- Service Availability and Uptime Commitments (Req 7.3)
- Data Ownership and Retention Policies (Req 7.4)
- Payment Terms and Refund Policies (Req 7.5)
- Termination Conditions (Req 7.6)
- Dispute Resolution Procedures (Req 7.7)
- Limitation of Liability (Req 7.8)
- Governing Law and Jurisdiction (Req 7.9)

#### 2.4 PRIVACY_POLICY.md

**Purpose**: GDPR-compliant privacy policy for data handling

**Required Sections**:
- Personal Data Collection (Req 8.2)
- Data Usage Purposes (Req 8.3)
- Data Storage and Protection (Req 8.4)
- Data Retention Periods (Req 8.5)
- User Rights (Req 8.6)
- Data Deletion Process (Req 8.7)
- Third-Party Data Processors (Req 8.8)
- Cookie Usage and Tracking (Req 8.9)
- GDPR Compliance (Req 8.10)

### 3. Core Security Components

#### 3.1 HTTPS Enforcement Middleware

**Purpose**: Force all traffic to HTTPS and inject security headers

**Interface**:
```php
class HttpsMiddleware
{
    /**
     * Redirect HTTP requests to HTTPS
     * @return Response with 301 redirect or continue to next middleware
     */
    public function handle(Request $request, Closure $next): Response;
    
    /**
     * Inject HSTS header into response
     */
    private function addHstsHeader(Response $response): Response;
}
```

**Configuration**:
```
HSTS_MAX_AGE=31536000  # 1 year
HSTS_INCLUDE_SUBDOMAINS=true
TLS_MIN_VERSION=1.2
```

**Requirements Mapping**:
- Req 9.1: HTTP to HTTPS redirect with 301
- Req 9.2-9.4: HSTS header with max-age and includeSubDomains
- Req 9.5: No content over HTTP except redirect
- Req 9.6: Reject TLS < 1.2



#### 3.2 Rate Limiting Service

**Purpose**: Prevent brute force attacks and API abuse using Redis-backed rate limiting

**Interface**:
```php
class RateLimiter
{
    /**
     * Check if request should be rate limited
     * @param string $key Identifier (IP address, user ID, or API key)
     * @param int $maxAttempts Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool true if rate limit exceeded
     */
    public function isRateLimited(string $key, int $maxAttempts, int $windowSeconds): bool;
    
    /**
     * Record a request attempt
     */
    public function recordAttempt(string $key, int $windowSeconds): void;
    
    /**
     * Get seconds until rate limit resets
     */
    public function getRetryAfter(string $key, int $windowSeconds): int;
    
    /**
     * Log rate limit violation to audit log
     */
    private function logViolation(string $key, string $endpoint): void;
}
```

**Rate Limit Policies**:
- Login endpoint: 5 requests per IP per 15 minutes (Req 10.1)
- API endpoints: 100 requests per user per 1 minute (Req 10.2)
- API key endpoints: Per-key limits instead of per-IP (Req 10.7)

**Redis Key Structure**:
```
rate_limit:login:{ip_address}
rate_limit:api:{user_id}
rate_limit:api_key:{api_key_hash}
```

**Requirements Mapping**:
- Req 10.1: Login rate limiting (5 per 15 min)
- Req 10.2: API rate limiting (100 per 1 min)
- Req 10.3: Redis for distributed tracking
- Req 10.4: Retry-After header
- Req 10.5: Counter reset after window
- Req 10.6: Log violations to audit log
- Req 10.7: Per-key limits for API keys

#### 3.3 Two-Factor Authentication Service

**Purpose**: Provide TOTP-based 2FA for enhanced account security

**Interface**:
```php
class TwoFactorAuthService
{
    /**
     * Generate TOTP secret for user
     * @return string Base32-encoded secret
     */
    public function generateSecret(): string;
    
    /**
     * Generate QR code URL for authenticator app setup
     */
    public function generateQrCodeUrl(string $secret, string $userEmail): string;
    
    /**
     * Generate backup codes for account recovery
     * @return array Array of 10 single-use backup codes
     */
    public function generateBackupCodes(): array;
    
    /**
     * Verify TOTP code
     * @param string $secret User's TOTP secret
     * @param string $code 6-digit code from authenticator app
     * @return bool true if code is valid
     */
    public function verifyCode(string $secret, string $code): bool;
    
    /**
     * Verify and consume backup code
     * @return bool true if backup code is valid and unused
     */
    public function verifyBackupCode(int $userId, string $code): bool;
    
    /**
     * Enable 2FA for user
     */
    public function enable(int $userId, string $secret, array $backupCodes): void;
    
    /**
     * Disable 2FA for user (requires re-authentication)
     */
    public function disable(int $userId): void;
}
```

**Database Schema**:
```sql
CREATE TABLE user_2fa (
    user_id BIGINT PRIMARY KEY REFERENCES users(id),
    secret VARCHAR(32) NOT NULL,  -- Base32-encoded TOTP secret (encrypted)
    enabled BOOLEAN DEFAULT false,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE TABLE user_2fa_backup_codes (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    code_hash VARCHAR(64) NOT NULL,  -- SHA-256 hash of backup code
    used BOOLEAN DEFAULT false,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL
);
```

**Requirements Mapping**:
- Req 11.1: TOTP-based authentication
- Req 11.2: QR code generation
- Req 11.3: Backup code generation
- Req 11.4: Require TOTP after password
- Req 11.5: Reject invalid TOTP codes
- Req 11.6: Allow 2FA disable after re-auth
- Req 11.7: Account recovery with backup codes
- Req 11.8: Invalidate backup codes after use



#### 3.4 Encryption Service

**Purpose**: Encrypt sensitive database fields using AES-256-GCM

**Interface**:
```php
class EncryptionService
{
    /**
     * Encrypt a value
     * @param string $plaintext Value to encrypt
     * @return string Base64-encoded encrypted value with IV
     */
    public function encrypt(string $plaintext): string;
    
    /**
     * Decrypt a value
     * @param string $ciphertext Base64-encoded encrypted value
     * @return string Decrypted plaintext
     * @throws DecryptionException if decryption fails
     */
    public function decrypt(string $ciphertext): string;
    
    /**
     * Hash a password using bcrypt
     * @param string $password Plain text password
     * @return string Bcrypt hash
     */
    public function hashPassword(string $password): string;
    
    /**
     * Verify a password against a hash
     */
    public function verifyPassword(string $password, string $hash): bool;
    
    /**
     * Rotate encryption key (re-encrypt all data with new key)
     */
    public function rotateKey(string $newMasterKey): void;
}
```

**Encryption Format**:
```
Base64(IV || Ciphertext || Tag)
```
- IV: 12 bytes (96 bits) - unique per encryption
- Ciphertext: Variable length
- Tag: 16 bytes (128 bits) - GCM authentication tag

**Environment Configuration**:
```
ENCRYPTION_MASTER_KEY=base64:...  # 32-byte key, base64-encoded
ENCRYPTION_ALGORITHM=aes-256-gcm
```

**Fields to Encrypt**:
- User passwords (bcrypt hash, not AES)
- API keys
- OAuth tokens
- Payment card data (if stored)
- TOTP secrets
- Any field marked as sensitive in schema

**Requirements Mapping**:
- Req 12.1: Encrypt password fields (bcrypt)
- Req 12.2: Encrypt API key fields
- Req 12.3: Encrypt OAuth token fields
- Req 12.4: Encrypt payment card data
- Req 12.5: Use AES-256-GCM
- Req 12.6: Master key from environment
- Req 12.7: Unique IV per encryption
- Req 12.8: Transparent decryption
- Req 12.9: Key rotation support

#### 3.5 Audit Logging Service

**Purpose**: Record security-relevant events for compliance and forensics

**Interface**:
```php
class AuditLogger
{
    /**
     * Log a security event
     * @param string $eventType Event type constant
     * @param int|null $userId User ID (null for anonymous events)
     * @param string|null $ipAddress Client IP address
     * @param array $metadata Additional event-specific data
     */
    public function log(
        string $eventType,
        ?int $userId,
        ?string $ipAddress,
        array $metadata = []
    ): void;
    
    /**
     * Query audit logs
     * @param array $filters Filters (user_id, event_type, date_range, etc.)
     * @return array Array of audit log entries
     */
    public function query(array $filters): array;
    
    /**
     * Verify log integrity (tamper detection)
     */
    public function verifyIntegrity(int $logId): bool;
}
```

**Event Types**:
```php
const EVENT_LOGIN_SUCCESS = 'login.success';
const EVENT_LOGIN_FAILURE = 'login.failure';
const EVENT_PASSWORD_CHANGE = 'password.change';
const EVENT_2FA_ENABLED = '2fa.enabled';
const EVENT_2FA_DISABLED = '2fa.disabled';
const EVENT_API_KEY_CREATED = 'api_key.created';
const EVENT_API_KEY_REVOKED = 'api_key.revoked';
const EVENT_SENSITIVE_DATA_ACCESS = 'data.access';
const EVENT_PERMISSION_GRANTED = 'permission.granted';
const EVENT_PERMISSION_REVOKED = 'permission.revoked';
const EVENT_RATE_LIMIT_EXCEEDED = 'rate_limit.exceeded';
```

**Database Schema**:
```sql
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    user_id BIGINT NULL REFERENCES users(id),
    ip_address INET NULL,
    metadata JSONB NOT NULL DEFAULT '{}',
    integrity_hash VARCHAR(64) NOT NULL,  -- SHA-256 of (id || event_type || user_id || timestamp || prev_hash)
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_event_type ON audit_logs(event_type);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);
```

**Tamper-Evident Design**:
Each log entry includes an integrity hash that chains to the previous entry, making tampering detectable.

**Requirements Mapping**:
- Req 13.1: Log successful logins
- Req 13.2: Log failed login attempts
- Req 13.3: Log password changes
- Req 13.4: Log 2FA enable/disable
- Req 13.5: Log API key creation/revocation
- Req 13.6: Log sensitive data access
- Req 13.7: Log permission changes
- Req 13.8: Tamper-evident format
- Req 13.9: 90-day retention
- Req 13.10: Query interface



#### 3.6 Content Security Policy Middleware

**Purpose**: Inject CSP headers to prevent XSS attacks

**Interface**:
```php
class CspMiddleware
{
    /**
     * Inject CSP header into response
     */
    public function handle(Request $request, Closure $next): Response;
    
    /**
     * Build CSP header value
     */
    private function buildCspHeader(): string;
    
    /**
     * Log CSP violation reports
     */
    public function handleViolationReport(Request $request): Response;
}
```

**CSP Policy**:
```
Content-Security-Policy:
  default-src 'self';
  script-src 'self' https://cdn.jsdelivr.net;
  style-src 'self' 'unsafe-inline';
  img-src 'self' data:;
  connect-src 'self' https://api.nexsaas.com;
  frame-ancestors 'none';
  base-uri 'self';
  form-action 'self';
```

**CSP Violation Reporting**:
```
Content-Security-Policy-Report-Only: ...; report-uri /api/csp-report
```

**Requirements Mapping**:
- Req 14.1: CSP header in all HTML responses
- Req 14.2: default-src 'self'
- Req 14.3: script-src 'self' + trusted CDNs
- Req 14.4: style-src 'self' 'unsafe-inline' (if needed)
- Req 14.5: img-src 'self' data:
- Req 14.6: connect-src 'self' + API domain
- Req 14.7: frame-ancestors 'none'
- Req 14.8: base-uri 'self'
- Req 14.9: form-action 'self'
- Req 14.10: Log CSP violations

#### 3.7 Input Validation and Sanitization

**Purpose**: Prevent XSS, SQL injection, and other injection attacks

**Interface**:
```php
class InputValidator
{
    /**
     * Sanitize HTML input (strip dangerous tags/attributes)
     */
    public function sanitizeHtml(string $input): string;
    
    /**
     * Validate and sanitize email address
     */
    public function validateEmail(string $email): ?string;
    
    /**
     * Validate and sanitize URL
     */
    public function validateUrl(string $url): ?string;
    
    /**
     * Escape output for HTML context
     */
    public function escapeHtml(string $output): string;
    
    /**
     * Escape output for JavaScript context
     */
    public function escapeJs(string $output): string;
}
```

**SQL Injection Prevention**:
- All database queries use parameterized queries (already implemented in BaseModel)
- No string concatenation for SQL queries
- Use ADOdb's prepared statement support

**CSRF Protection**:
```php
class CsrfProtection
{
    /**
     * Generate CSRF token for session
     */
    public function generateToken(): string;
    
    /**
     * Verify CSRF token from request
     */
    public function verifyToken(string $token): bool;
}
```

**Requirements Mapping**:
- Req 15.6: SQL injection prevention (parameterized queries)
- Req 15.7: XSS prevention (output encoding)
- Req 15.8: CSRF protection (tokens)
- Req 15.9: Input validation and sanitization
- Req 15.10: Secure session management

#### 3.8 Session Security

**Purpose**: Secure session cookie configuration

**Session Cookie Configuration**:
```php
session_set_cookie_params([
    'lifetime' => 0,           // Session cookie (expires on browser close)
    'path' => '/',
    'domain' => '.nexsaas.com',
    'secure' => true,          // HTTPS only
    'httponly' => true,        // Not accessible via JavaScript
    'samesite' => 'Strict'     // CSRF protection
]);
```

**Requirements Mapping**:
- Req 15.10: httpOnly and secure flags on cookies

## Data Models

### Configuration Data Model

**Environment Variables** (loaded at runtime):

```
# Database Configuration
DB_HOST=localhost
DB_PORT=5432
DB_NAME=nexsaas
DB_USER=nexsaas_user
DB_PASSWORD=<secure_password>

# Redis Configuration
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=<secure_password>

# Encryption Configuration
ENCRYPTION_MASTER_KEY=base64:<32_byte_key_base64_encoded>
ENCRYPTION_ALGORITHM=aes-256-gcm

# JWT Configuration
JWT_PRIVATE_KEY_PATH=/path/to/jwt_private.pem
JWT_PUBLIC_KEY_PATH=/path/to/jwt_public.pem
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=604800

# HTTPS Configuration
HSTS_MAX_AGE=31536000
HSTS_INCLUDE_SUBDOMAINS=true
TLS_MIN_VERSION=1.2

# Rate Limiting Configuration
RATE_LIMIT_LOGIN_MAX=5
RATE_LIMIT_LOGIN_WINDOW=900
RATE_LIMIT_API_MAX=100
RATE_LIMIT_API_WINDOW=60

# Third-Party API Keys
ANTHROPIC_API_KEY=<api_key>
STRIPE_SECRET_KEY=<api_key>
STRIPE_WEBHOOK_SECRET=<webhook_secret>
```



### 2FA Data Model

```sql
-- User 2FA configuration
CREATE TABLE user_2fa (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    secret VARCHAR(255) NOT NULL,  -- Encrypted TOTP secret
    enabled BOOLEAN DEFAULT false,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Backup codes for account recovery
CREATE TABLE user_2fa_backup_codes (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash VARCHAR(64) NOT NULL,  -- SHA-256 hash of backup code
    used BOOLEAN DEFAULT false,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_user_2fa_backup_codes_user_id ON user_2fa_backup_codes(user_id);
CREATE INDEX idx_user_2fa_backup_codes_used ON user_2fa_backup_codes(used);
```

### Audit Log Data Model

```sql
-- Security audit logs
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,  -- Multi-tenant isolation
    event_type VARCHAR(50) NOT NULL,
    user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    ip_address INET NULL,
    user_agent TEXT NULL,
    metadata JSONB NOT NULL DEFAULT '{}',
    integrity_hash VARCHAR(64) NOT NULL,  -- Tamper detection
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_audit_logs_tenant_id ON audit_logs(tenant_id);
CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_event_type ON audit_logs(event_type);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);
CREATE INDEX idx_audit_logs_ip_address ON audit_logs(ip_address);

-- Partition by month for performance
CREATE TABLE audit_logs_2025_01 PARTITION OF audit_logs
    FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');
```

### Rate Limiting Data Model (Redis)

**Redis Keys**:
```
rate_limit:login:{ip_address}        → Counter (TTL: 900 seconds)
rate_limit:api:{user_id}             → Counter (TTL: 60 seconds)
rate_limit:api_key:{api_key_hash}    → Counter (TTL: 60 seconds)
```

**Redis Commands**:
```redis
# Record attempt
INCR rate_limit:login:192.168.1.1
EXPIRE rate_limit:login:192.168.1.1 900

# Check limit
GET rate_limit:login:192.168.1.1
TTL rate_limit:login:192.168.1.1
```

### Encrypted Fields Data Model

**Fields requiring encryption**:

```sql
-- Users table
ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NOT NULL;  -- bcrypt

-- API keys table
CREATE TABLE api_keys (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    key_hash VARCHAR(64) NOT NULL,  -- SHA-256 hash for lookup
    key_encrypted TEXT NOT NULL,    -- AES-256-GCM encrypted key
    name VARCHAR(100) NOT NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    revoked_at TIMESTAMP NULL
);

-- OAuth tokens table
CREATE TABLE oauth_tokens (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID NOT NULL,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider VARCHAR(50) NOT NULL,
    access_token_encrypted TEXT NOT NULL,   -- AES-256-GCM encrypted
    refresh_token_encrypted TEXT NULL,      -- AES-256-GCM encrypted
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
```

## Implementation Approach

### Phase 0.1: Configuration Security (Days 1-2)

**Day 1: Git History Cleanup**
1. Create backup of repository
2. Install git-filter-repo tool
3. Run history rewrite script to remove config.inc.php and config.db.php
4. Verify no credentials remain in history
5. Document process in SECURITY_CLEANUP.md
6. Force push to remote (coordinate with team)

**Day 2: Environment Configuration**
1. Create ConfigLoader service
2. Update .env.example with all required variables
3. Create CONFIG.md documentation
4. Update .gitignore to prevent future credential leaks
5. Migrate existing configuration to environment variables
6. Test configuration loading in dev/staging environments

### Phase 0.2: Legal Documentation (Days 3-4)

**Day 3: Licensing Strategy**
1. Research AGPL obligations for hosted services
2. Inventory all AGPL dependencies
3. Decide on commercial approach (dual licensing vs. core rewrite)
4. Write LICENSING_STRATEGY.md
5. Write COMMERCIAL_LICENSE.md template

**Day 4: Terms and Privacy**
1. Write TERMS_OF_SERVICE.md (consult legal counsel)
2. Write PRIVACY_POLICY.md (GDPR compliance)
3. Review all legal documents with legal counsel
4. Publish legal documents to website



### Phase 0.3: Core Security Implementation (Days 5-7)

**Day 5: Transport Security & Rate Limiting**
1. Implement HttpsMiddleware (HTTPS redirect + HSTS)
2. Configure TLS 1.2+ minimum version
3. Implement RateLimiter service with Redis backend
4. Add rate limiting middleware to login endpoint
5. Add rate limiting middleware to API endpoints
6. Test rate limiting with load testing tool

**Day 6: 2FA & Encryption**
1. Create database migrations for user_2fa tables
2. Implement TwoFactorAuthService (TOTP generation/validation)
3. Implement EncryptionService (AES-256-GCM)
4. Add 2FA enable/disable endpoints
5. Update login flow to support 2FA
6. Encrypt existing sensitive fields in database
7. Test 2FA flow with authenticator app

**Day 7: Audit Logging & Input Validation**
1. Create database migration for audit_logs table
2. Implement AuditLogger service
3. Add audit logging to all security-relevant events
4. Implement InputValidator service
5. Implement CsrfProtection service
6. Add CSRF tokens to all forms
7. Review all user input points for validation
8. Implement CspMiddleware
9. Test CSP policy with browser dev tools

### Phase 0.4: Security Assessment (Day 8)

**Day 8: Vulnerability Assessment**
1. Install OWASP ZAP
2. Configure ZAP for NexSaaS application
3. Run automated security scan
4. Review and triage findings
5. Fix all critical and high severity vulnerabilities
6. Document findings in SECURITY_ASSESSMENT.md
7. Re-scan to verify fixes
8. Create remediation plan for medium/low severity issues

### Testing Strategy

The security and legal foundation requires both unit testing and property-based testing to ensure correctness and security.

#### Unit Testing Approach

Unit tests will focus on:
- Specific security scenarios and edge cases
- Integration between security components
- Error handling for security failures
- Legal document completeness

**Example Unit Tests**:
```php
// ConfigLoader tests
testGetRequiredThrowsExceptionWhenVariableMissing()
testGetRequiredThrowsExceptionWhenVariableEmpty()
testGetReturnsDefaultWhenVariableNotSet()
testDatabaseConfigLoadsAllRequiredFields()

// RateLimiter tests
testLoginRateLimitRejectsAfter5Attempts()
testRateLimitResetsAfterTimeWindow()
testRetryAfterHeaderIncludedInResponse()
testRateLimitViolationIsLogged()

// TwoFactorAuthService tests
testGenerateSecretReturnsBase32String()
testVerifyCodeAcceptsValidTotp()
testVerifyCodeRejectsInvalidTotp()
testBackupCodeCanOnlyBeUsedOnce()

// EncryptionService tests
testEncryptDecryptRoundTrip()
testDecryptFailsWithWrongKey()
testUniqueIvGeneratedForEachEncryption()
testPasswordHashingUsesBcrypt()

// AuditLogger tests
testLoginSuccessIsLogged()
testLoginFailureIsLogged()
testIntegrityHashDetectsTampering()
testQueryReturnsFilteredResults()

// CspMiddleware tests
testCspHeaderIncludedInHtmlResponses()
testCspViolationIsLogged()

// InputValidator tests
testSanitizeHtmlRemovesDangerousTags()
testEscapeHtmlPreventesXss()
testValidateEmailRejectsInvalidFormat()
```

#### Property-Based Testing Approach

Property-based tests will verify universal security properties across all inputs. Each test will run a minimum of 100 iterations with randomized inputs.

**Property Test Configuration**:
- Library: PHPUnit with Eris (property-based testing for PHP)
- Iterations: 100 minimum per property
- Tagging: Each test references its design document property



## Error Handling

### Configuration Errors

**Missing Environment Variable**:
```php
throw new ConfigException(
    "Required environment variable '{$key}' is not set. " .
    "Please set {$key} in your .env file or environment."
);
```

**Empty Environment Variable**:
```php
throw new ConfigException(
    "Required environment variable '{$key}' is set but empty. " .
    "Please provide a valid value for {$key}."
);
```

### Encryption Errors

**Decryption Failure**:
```php
throw new DecryptionException(
    "Failed to decrypt data. The data may be corrupted or encrypted with a different key."
);
```

**Invalid Master Key**:
```php
throw new ConfigException(
    "ENCRYPTION_MASTER_KEY must be a base64-encoded 32-byte key. " .
    "Generate with: openssl rand -base64 32"
);
```

### Rate Limiting Errors

**Rate Limit Exceeded**:
```http
HTTP/1.1 429 Too Many Requests
Retry-After: 300
Content-Type: application/json

{
  "success": false,
  "error": "Rate limit exceeded. Please try again in 5 minutes.",
  "data": null,
  "meta": { ... }
}
```

### 2FA Errors

**Invalid TOTP Code**:
```json
{
  "success": false,
  "error": "Invalid authentication code. Please try again.",
  "data": null
}
```

**Backup Code Already Used**:
```json
{
  "success": false,
  "error": "This backup code has already been used. Please use a different code.",
  "data": null
}
```

### Security Errors

**CSRF Token Invalid**:
```http
HTTP/1.1 403 Forbidden
Content-Type: application/json

{
  "success": false,
  "error": "CSRF token validation failed. Please refresh the page and try again.",
  "data": null
}
```

**SQL Injection Attempt Detected**:
```php
// Log the attempt
$auditLogger->log(
    AuditLogger::EVENT_SECURITY_VIOLATION,
    $userId,
    $ipAddress,
    ['type' => 'sql_injection_attempt', 'input' => $sanitizedInput]
);

// Return generic error
throw new SecurityException("Invalid input detected.");
```



## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

Based on the prework analysis, I've identified the following testable properties. After reflection, I've eliminated redundant properties and combined related ones for comprehensive coverage.

### Property Reflection

After analyzing all acceptance criteria, I identified several areas of redundancy:

1. **Configuration Loading (2.1-2.4)**: All four criteria test that credentials come from environment variables. These can be combined into a single property about credential sources.

2. **Encryption of Sensitive Fields (12.1-12.4)**: All four criteria test that sensitive fields are encrypted. These can be combined into a single property about sensitive field encryption.

3. **Audit Logging (13.1-13.7)**: All seven criteria test that security events are logged. These can be combined into a single property about security event logging.

4. **CSP Directives (14.2-14.9)**: All eight criteria test specific CSP directive values. These can be combined into a single property about CSP header completeness.

5. **HSTS Header Components (9.2-9.4)**: These three criteria all test parts of the HSTS header. They can be combined into a single property.

The following properties represent the unique, non-redundant validation requirements:

### Property 1: Configuration Credentials Source

*For any* credential configuration value (database password, API key, encryption key, or third-party service credential), the value SHALL be loaded from an environment variable and SHALL NOT be hardcoded in the application code.

**Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.7**

### Property 2: Missing Environment Variable Error

*For any* required environment variable that is missing or empty, the ConfigLoader SHALL throw a descriptive exception that includes the variable name.

**Validates: Requirements 2.5, 2.6**

### Property 3: HTTP to HTTPS Redirect

*For any* HTTP request to the application, the system SHALL respond with a 301 redirect to the HTTPS equivalent URL and SHALL NOT serve any content over HTTP.

**Validates: Requirements 9.1, 9.5**

### Property 4: HSTS Header Presence and Configuration

*For any* HTTPS response, the system SHALL include a Strict-Transport-Security header with max-age >= 31536000 seconds and the includeSubDomains directive.

**Validates: Requirements 9.2, 9.3, 9.4**

### Property 5: Rate Limit Retry-After Header

*For any* request that exceeds a rate limit, the response SHALL include a Retry-After header indicating the number of seconds until the limit resets.

**Validates: Requirements 10.4**

### Property 6: Rate Limit Violation Logging

*For any* rate limit violation, the system SHALL create an audit log entry recording the event type, IP address or user ID, and endpoint.

**Validates: Requirements 10.6**

### Property 7: 2FA Required for Enabled Users

*For any* user with 2FA enabled, a login attempt with valid password but missing or invalid TOTP code SHALL be rejected.

**Validates: Requirements 11.4, 11.5**

### Property 8: Backup Code Single Use

*For any* backup code, after it is successfully used for authentication, subsequent attempts to use the same backup code SHALL be rejected.

**Validates: Requirements 11.8**

### Property 9: Sensitive Field Encryption

*For any* sensitive field (password, API key, OAuth token, or payment card data), the value stored in the database SHALL be encrypted and SHALL NOT be stored in plaintext.

**Validates: Requirements 12.1, 12.2, 12.3, 12.4**

### Property 10: Unique Initialization Vectors

*For any* two encryption operations, even when encrypting the same plaintext value, the initialization vectors SHALL be different.

**Validates: Requirements 12.7**

### Property 11: Encryption Round Trip

*For any* plaintext value, encrypting then decrypting SHALL return the original value unchanged.

**Validates: Requirements 12.8**

### Property 12: Security Event Audit Logging

*For any* security-relevant event (login success/failure, password change, 2FA change, API key operation, sensitive data access, or permission change), the system SHALL create an audit log entry with timestamp, user ID (if applicable), IP address, and event-specific metadata.

**Validates: Requirements 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7**

### Property 13: Audit Log Tamper Detection

*For any* audit log entry, if the entry data is modified after creation, the integrity hash verification SHALL fail, indicating tampering.

**Validates: Requirements 13.8**

### Property 14: CSP Header in HTML Responses

*For any* HTML response, the system SHALL include a Content-Security-Policy header.

**Validates: Requirements 14.1**

### Property 15: CSP Violation Logging

*For any* CSP violation report received, the system SHALL create an audit log entry recording the violation details.

**Validates: Requirements 14.10**

### Property 16: SQL Injection Prevention

*For any* database query, the system SHALL use parameterized queries and SHALL NOT construct SQL statements using string concatenation with user input.

**Validates: Requirements 15.6**

### Property 17: XSS Prevention via Output Encoding

*For any* user-generated content displayed in HTML context, the system SHALL encode dangerous characters (< > & " ') to prevent script execution.

**Validates: Requirements 15.7**

### Property 18: CSRF Token Validation

*For any* state-changing HTTP request (POST, PUT, DELETE), the system SHALL require a valid CSRF token and SHALL reject requests with missing or invalid tokens.

**Validates: Requirements 15.8**

### Property 19: Input Sanitization

*For any* user input, the system SHALL validate and sanitize the input according to its expected type and format before processing.

**Validates: Requirements 15.9**

### Property 20: Secure Session Cookie Flags

*For any* session cookie set by the system, the cookie SHALL have both the httpOnly and secure flags set to true.

**Validates: Requirements 15.10**



## Testing Strategy

The security and legal foundation requires a dual testing approach combining unit tests for specific scenarios and property-based tests for universal security properties.

### Testing Approach

**Unit Tests**: Focus on specific examples, edge cases, and integration points
- Configuration loading with specific environment variables
- Rate limiting with exact request counts
- 2FA setup and validation flows
- Legal document existence and structure
- OWASP ZAP security scan results

**Property-Based Tests**: Verify universal properties across randomized inputs
- Configuration security properties (100+ random credential types)
- Encryption properties (100+ random plaintexts)
- Rate limiting properties (100+ random request patterns)
- Audit logging properties (100+ random security events)
- Input validation properties (100+ random malicious inputs)

### Property-Based Testing Configuration

**Library**: PHPUnit with Eris (property-based testing for PHP)
- Installation: `composer require --dev giorgiosironi/eris`
- Minimum iterations: 100 per property test
- Seed: Randomized (logged for reproducibility)

**Test Tagging Format**:
```php
/**
 * @test
 * Feature: security-legal-foundation, Property 1: Configuration Credentials Source
 */
public function property_configuration_credentials_from_environment()
{
    $this->forAll(
        Generator\elements(['DB_PASSWORD', 'ANTHROPIC_API_KEY', 'ENCRYPTION_MASTER_KEY'])
    )->then(function ($credentialKey) {
        // Test that credential comes from environment, not hardcoded
        $value = ConfigLoader::getRequired($credentialKey);
        $this->assertNotEmpty($value);
        $this->assertNotEquals('default', $value);
        $this->assertNotEquals('changeme', $value);
    });
}
```

### Unit Test Coverage

**Configuration Security Tests**:
```php
testConfigLoaderThrowsExceptionForMissingVariable()
testConfigLoaderThrowsExceptionForEmptyVariable()
testConfigLoaderLoadsDatabaseConfigFromEnvironment()
testConfigLoaderSupportsEnvFileInDevelopment()
testGitignoreContainsCredentialFiles()
testEnvExampleDoesNotContainRealCredentials()
```

**Legal Documentation Tests**:
```php
testLicensingStrategyFileExists()
testCommercialLicenseFileExists()
testTermsOfServiceFileExists()
testPrivacyPolicyFileExists()
testLicensingStrategyListsAgplDependencies()
```

**HTTPS Enforcement Tests**:
```php
testHttpRequestRedirectsToHttps()
testHttpsResponseIncludesHstsHeader()
testHstsHeaderHasCorrectMaxAge()
testHstsHeaderIncludesSubdomains()
testTlsVersionMinimum()
```

**Rate Limiting Tests**:
```php
testLoginRateLimitRejectsAfter5Attempts()
testApiRateLimitRejectsAfter100Requests()
testRateLimitUsesRedisForTracking()
testRateLimitIncludesRetryAfterHeader()
testRateLimitResetsAfterTimeWindow()
testRateLimitUsesApiKeyInsteadOfIpWhenProvided()
```

**2FA Tests**:
```php
testTwoFactorAuthGeneratesSecret()
testTwoFactorAuthGeneratesQrCode()
testTwoFactorAuthGeneratesBackupCodes()
testTwoFactorAuthRequiresTotpAfterPassword()
testTwoFactorAuthRejectsInvalidTotp()
testTwoFactorAuthAllowsDisableAfterReauth()
testBackupCodeWorksForLogin()
testBackupCodeInvalidatedAfterUse()
```

**Encryption Tests**:
```php
testEncryptionUsesAes256Gcm()
testEncryptionMasterKeyFromEnvironment()
testEncryptionDecryptionRoundTrip()
testEncryptionUsesUniqueIvs()
testPasswordHashingUsesBcrypt()
testPasswordVerification()
testKeyRotationReencryptsData()
```

**Audit Logging Tests**:
```php
testLoginSuccessIsLogged()
testLoginFailureIsLogged()
testPasswordChangeIsLogged()
testTwoFactorAuthChangeIsLogged()
testApiKeyOperationIsLogged()
testSensitiveDataAccessIsLogged()
testPermissionChangeIsLogged()
testAuditLogIntegrityHashDetectsTampering()
testAuditLogRetention90Days()
testAuditLogQueryInterface()
```

**CSP Tests**:
```php
testCspHeaderIncludedInHtmlResponses()
testCspDefaultSrcSelf()
testCspScriptSrcConfiguration()
testCspStyleSrcConfiguration()
testCspImgSrcConfiguration()
testCspConnectSrcConfiguration()
testCspFrameAncestorsNone()
testCspBaseUriSelf()
testCspFormActionSelf()
testCspViolationIsLogged()
```

**Input Validation Tests**:
```php
testSqlInjectionPreventionWithParameterizedQueries()
testXssPreventionWithOutputEncoding()
testCsrfProtectionWithTokens()
testInputSanitizationRemovesDangerousTags()
testSessionCookieHasHttpOnlyFlag()
testSessionCookieHasSecureFlag()
```

**Security Assessment Tests**:
```php
testOwaspZapScanCompleted()
testNoCriticalVulnerabilitiesRemaining()
testNoHighVulnerabilitiesRemaining()
testSecurityAssessmentDocumentExists()
```

### Property-Based Test Examples

**Property 1: Configuration Credentials Source**
```php
/**
 * @test
 * Feature: security-legal-foundation, Property 1: Configuration Credentials Source
 */
public function property_configuration_credentials_from_environment()
{
    $this->forAll(
        Generator\elements([
            'DB_PASSWORD',
            'REDIS_PASSWORD',
            'ANTHROPIC_API_KEY',
            'ENCRYPTION_MASTER_KEY',
            'STRIPE_SECRET_KEY'
        ])
    )->then(function ($credentialKey) {
        // Verify credential comes from environment
        $value = getenv($credentialKey);
        $this->assertNotFalse($value, "Credential {$credentialKey} must be in environment");
        $this->assertNotEmpty($value, "Credential {$credentialKey} must not be empty");
        
        // Verify no hardcoded fallbacks
        $configValue = ConfigLoader::getRequired($credentialKey);
        $this->assertEquals($value, $configValue);
    });
}
```

**Property 11: Encryption Round Trip**
```php
/**
 * @test
 * Feature: security-legal-foundation, Property 11: Encryption Round Trip
 */
public function property_encryption_round_trip()
{
    $this->forAll(
        Generator\string()
    )->then(function ($plaintext) {
        $encryptionService = new EncryptionService();
        
        $encrypted = $encryptionService->encrypt($plaintext);
        $decrypted = $encryptionService->decrypt($encrypted);
        
        $this->assertEquals($plaintext, $decrypted);
    });
}
```

**Property 10: Unique Initialization Vectors**
```php
/**
 * @test
 * Feature: security-legal-foundation, Property 10: Unique Initialization Vectors
 */
public function property_unique_initialization_vectors()
{
    $this->forAll(
        Generator\string()
    )->then(function ($plaintext) {
        $encryptionService = new EncryptionService();
        
        $encrypted1 = $encryptionService->encrypt($plaintext);
        $encrypted2 = $encryptionService->encrypt($plaintext);
        
        // Even encrypting the same plaintext should produce different ciphertexts
        // due to unique IVs
        $this->assertNotEquals($encrypted1, $encrypted2);
    });
}
```

**Property 17: XSS Prevention via Output Encoding**
```php
/**
 * @test
 * Feature: security-legal-foundation, Property 17: XSS Prevention via Output Encoding
 */
public function property_xss_prevention_output_encoding()
{
    $this->forAll(
        Generator\string()
    )->then(function ($userInput) {
        $validator = new InputValidator();
        $encoded = $validator->escapeHtml($userInput);
        
        // Dangerous characters must be encoded
        $this->assertStringNotContainsString('<script>', $encoded);
        $this->assertStringNotContainsString('javascript:', $encoded);
        
        // If input contained dangerous chars, output must be different
        if (preg_match('/[<>&"\']/', $userInput)) {
            $this->assertNotEquals($userInput, $encoded);
        }
    });
}
```

**Property 12: Security Event Audit Logging**
```php
/**
 * @test
 * Feature: security-legal-foundation, Property 12: Security Event Audit Logging
 */
public function property_security_event_audit_logging()
{
    $this->forAll(
        Generator\elements([
            AuditLogger::EVENT_LOGIN_SUCCESS,
            AuditLogger::EVENT_LOGIN_FAILURE,
            AuditLogger::EVENT_PASSWORD_CHANGE,
            AuditLogger::EVENT_2FA_ENABLED,
            AuditLogger::EVENT_API_KEY_CREATED
        ]),
        Generator\nat(),
        Generator\string()
    )->then(function ($eventType, $userId, $ipAddress) {
        $auditLogger = new AuditLogger();
        
        $auditLogger->log($eventType, $userId, $ipAddress, []);
        
        // Verify log entry was created
        $logs = $auditLogger->query([
            'event_type' => $eventType,
            'user_id' => $userId
        ]);
        
        $this->assertNotEmpty($logs);
        $this->assertEquals($eventType, $logs[0]['event_type']);
        $this->assertEquals($userId, $logs[0]['user_id']);
        $this->assertEquals($ipAddress, $logs[0]['ip_address']);
        $this->assertNotEmpty($logs[0]['created_at']);
        $this->assertNotEmpty($logs[0]['integrity_hash']);
    });
}
```

### Integration Testing

**End-to-End Security Flow Tests**:
1. User registration with password encryption
2. Login with rate limiting enforcement
3. 2FA setup and authentication flow
4. API key creation and usage with rate limiting
5. Audit log generation for all security events
6. CSRF protection on state-changing requests
7. CSP enforcement in browser

**Security Scanning**:
1. OWASP ZAP automated scan (baseline and full scan)
2. SSL/TLS configuration testing (testssl.sh)
3. Dependency vulnerability scanning (composer audit)
4. Static code analysis for security issues (Psalm, PHPStan)

### Test Execution

**Development**:
```bash
# Run all tests
composer test

# Run only property-based tests
composer test -- --group property

# Run security tests
composer test -- --group security

# Run with coverage
composer test -- --coverage-html coverage/
```

**CI/CD Pipeline**:
```yaml
- name: Run Security Tests
  run: |
    composer test -- --group security
    
- name: Run Property Tests
  run: |
    composer test -- --group property --testdox
    
- name: OWASP ZAP Scan
  run: |
    docker run -v $(pwd):/zap/wrk/:rw \
      -t owasp/zap2docker-stable zap-baseline.py \
      -t https://staging.nexsaas.com \
      -r zap-report.html
```

### Success Criteria

Phase 0 is complete when:
1. All unit tests pass (100% of security tests)
2. All property-based tests pass (20 properties × 100 iterations = 2000 test cases)
3. OWASP ZAP scan shows zero critical/high vulnerabilities
4. All legal documents are reviewed and approved by legal counsel
5. Git history contains no credential files
6. All configuration loaded from environment variables
7. Manual security review completed

---

## Summary

This design document provides a comprehensive approach to establishing security and legal foundations for the NexSaaS platform. The implementation follows industry best practices for:

- **Configuration Security**: Environment-based configuration with git history cleanup
- **Legal Compliance**: AGPL licensing strategy with commercial licensing option
- **Transport Security**: HTTPS enforcement with HSTS and TLS 1.2+
- **Access Control**: Rate limiting and 2FA for enhanced security
- **Data Protection**: AES-256-GCM encryption for sensitive fields
- **Audit & Compliance**: Comprehensive security event logging
- **Attack Prevention**: CSP, input validation, CSRF protection, SQL injection prevention

The dual testing approach (unit tests + property-based tests) ensures both specific scenarios and universal security properties are validated. The 8-day implementation timeline provides a realistic schedule for completing this critical foundation phase before any customer-facing work begins.
