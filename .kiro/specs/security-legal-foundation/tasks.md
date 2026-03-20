# Implementation Plan: Security & Legal Foundation

## Overview

This implementation plan covers Phase 0: Security & Legal Foundation for NexSaaS. This is a CRITICAL blocking phase that establishes security hardening and legal compliance foundations before any customer-facing work, sales activities, or customer demos.

The implementation is organized into 4 phases over 8 days:
- Phase 0.1: Configuration Security (Days 1-2)
- Phase 0.2: Legal Documentation (Days 3-4)
- Phase 0.3: Core Security Implementation (Days 5-7)
- Phase 0.4: Security Assessment (Day 8)

Implementation Language: PHP
Testing Framework: PHPUnit with Eris (property-based testing)

## Tasks

### Phase 0.1: Configuration Security (Days 1-2)

- [-] 1. Backup repository and prepare for git history cleanup
  - Create full backup of repository before history rewrite
  - Document current repository state and commit count
  - Install git-filter-repo tool (preferred) or BFG Repo-Cleaner
  - _Requirements: 1.1, 1.2, 1.6_

- [ ] 2. Remove credential files from git history
  - [ ] 2.1 Create and execute git history cleanup script
    - Create `scripts/clean_git_history.sh` script
    - Use git-filter-repo to remove config.inc.php from all commits
    - Use git-filter-repo to remove config.db.php from all commits
    - Verify no credentials remain in any commit using git log search
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [ ] 2.2 Document cleanup process
    - Create `SECURITY_CLEANUP.md` documenting the history rewrite process
    - Include verification steps and commands used
    - Document any issues encountered and resolutions
    - _Requirements: 1.6_


- [ ] 3. Implement ConfigLoader service for environment-based configuration
  - [ ] 3.1 Create ConfigLoader class with environment variable loading
    - Create `modular_core/bootstrap/ConfigLoader.php`
    - Implement `getRequired()` method that throws exception for missing/empty variables
    - Implement `get()` method with optional default values
    - Implement `getDatabaseConfig()` method for database credentials
    - Implement `getEncryptionConfig()` method for encryption settings
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

  - [ ]* 3.2 Write property test for configuration credentials source
    - **Property 1: Configuration Credentials Source**
    - **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.7**
    - Test that all credentials come from environment variables, not hardcoded

  - [ ]* 3.3 Write property test for missing environment variable errors
    - **Property 2: Missing Environment Variable Error**
    - **Validates: Requirements 2.5, 2.6**
    - Test that missing/empty variables throw descriptive exceptions

  - [ ]* 3.4 Write unit tests for ConfigLoader
    - Test getRequired() throws exception for missing variable
    - Test getRequired() throws exception for empty variable
    - Test get() returns default when variable not set
    - Test getDatabaseConfig() loads all required fields
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

- [ ] 4. Create configuration documentation and templates
  - [ ] 4.1 Create .env.example template file
    - Create `.env.example` with all required environment variables
    - Include descriptive comments for each variable
    - Use placeholder values showing expected format
    - Ensure no real credentials are included
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

  - [ ] 4.2 Create CONFIG.md documentation
    - Create `CONFIG.md` documenting all environment variables
    - Specify which variables are required vs optional
    - Document expected format and validation rules for each variable
    - Include examples for generating secure values (encryption keys, etc.)
    - _Requirements: 3.5, 3.6, 3.7_

- [ ] 5. Update .gitignore to prevent future credential leaks
  - Add .env to .gitignore
  - Add config.inc.php to .gitignore
  - Add config.db.php to .gitignore
  - Add *.key pattern to .gitignore
  - Add *.pem pattern to .gitignore
  - Add *secret* pattern to .gitignore
  - Test that git rejects commits of ignored files
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_

- [ ] 6. Checkpoint - Configuration security validation
  - Verify git history contains no credential files
  - Verify all configuration loads from environment variables
  - Verify .env.example is complete and accurate
  - Ensure all tests pass, ask the user if questions arise.


### Phase 0.2: Legal Documentation (Days 3-4)

- [ ] 7. Research and document AGPL licensing strategy
  - [ ] 7.1 Create LICENSING_STRATEGY.md document
    - Research AGPL obligations for hosted services
    - Inventory all AGPL-licensed dependen
### Phase 0.2: Legal Documentation (Days 3-4)

- [ ] 7. Research and document AGPL licensing strategy
  - [ ] 7.1 Create LICENSING_STRATEGY.md document
    - Research AGPL obligations for hosted services
    - Inventory all AGPL-licensed dependencies in the codebase
    - Decide on commercial approach (dual licensing vs core rewrite)
    - Document AGPL obligations overview
    - Document commercial approach decision and rationale
    - Document source code availability policy
    - Document commercial license purchase process
    - Document core rewrite roadmap if applicable
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7_

- [ ] 8. Create commercial license document
  - [ ] 8.1 Write COMMERCIAL_LICENSE.md
    - Define license grant and permitted uses
    - Specify restrictions on redistribution
    - Include warranty disclaimers
    - Include liability limitations
    - Specify license fee structure or reference pricing
    - Specify license term and renewal conditions
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7_

- [ ] 9. Create terms of service document
  - [ ] 9.1 Write TERMS_OF_SERVICE.md
    - Define acceptable use policies
    - Define service availability and uptime commitments
    - Define data ownership and retention policies
    - Define payment terms and refund policies
    - Define termination conditions
    - Define dispute resolution procedures
    - Include limitation of liability clauses
    - Specify governing law and jurisdiction
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9_

- [ ] 10. Create privacy policy document
  - [ ] 10.1 Write PRIVACY_POLICY.md
    - Identify what personal data is collected
    - Explain how personal data is used
    - Explain how personal data is stored and protected
    - Explain data retention periods
    - Explain user rights regarding their data (access, deletion, portability)
    - Explain process for data deletion requests
    - Identify any third-party data processors
    - Explain cookie usage and tracking
    - Ensure GDPR compliance for EU users
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9, 8.10_

- [ ] 11. Checkpoint - Legal documentation review
  - Review all legal documents for completeness
  - Verify all required sections are present
  - Recommend legal counsel review before production use
  - Ensure all tests pass, ask the user if questions arise.


### Phase 0.3: Core Security Implementation (Days 5-7)

- [ ] 12. Implement HTTPS enforcement and transport security
  - [ ] 12.1 Create HttpsMiddleware for HTTPS redirect and HSTS
    - Create `modular_core/middleware/HttpsMiddleware.php`
    - Implement HTTP to HTTPS redirect with 301 status code
    - Inject Strict-Transport-Security header with max-age=31536000
    - Include includeSubDomains directive in HSTS header
    - Configure TLS minimum version to 1.2
    - Ensure no content served over HTTP except redirect
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_

  - [ ]* 12.2 Write property test for HTTP to HTTPS redirect
    - **Property 3: HTTP to HTTPS Redirect**
    - **Validates: Requirements 9.1, 9.5**
    - Test that all HTTP requests redirect to HTTPS with 301

  - [ ]* 12.3 Write property test for HSTS header configuration
    - **Property 4: HSTS Header Presence and Configuration**
    - **Validates: Requirements 9.2, 9.3, 9.4**
    - Test that HSTS header is present with correct max-age and includeSubDomains

  - [ ]* 12.4 Write unit tests for HttpsMiddleware
    - Test HTTP request redirects to HTTPS
    - Test HTTPS response includes HSTS header
    - Test HSTS header has correct max-age
    - Test HSTS header includes includeSubDomains
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

- [ ] 13. Implement rate limiting service
  - [ ] 13.1 Create RateLimiter service with Redis backend
    - Create `modular_core/services/RateLimiter.php`
    - Implement isRateLimited() method checking Redis counters
    - Implement recordAttempt() method incrementing Redis counters
    - Implement getRetryAfter() method calculating reset time
    - Implement logViolation() method for audit logging
    - Configure login endpoint rate limit (5 per 15 min per IP)
    - Configure API endpoint rate limit (100 per 1 min per user)
    - Support per-API-key rate limits instead of per-IP when key provided
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7_

  - [ ] 13.2 Create rate limiting middleware
    - Create `modular_core/middleware/RateLimitMiddleware.php`
    - Apply rate limiting to login endpoint
    - Apply rate limiting to API endpoints
    - Return HTTP 429 when rate limit exceeded
    - Include Retry-After header in 429 responses
    - _Requirements: 10.1, 10.2, 10.4_

  - [ ]* 13.3 Write property test for rate limit retry-after header
    - **Property 5: Rate Limit Retry-After Header**
    - **Validates: Requirements 10.4**
    - Test that rate limit responses include Retry-After header

  - [ ]* 13.4 Write property test for rate limit violation logging
    - **Property 6: Rate Limit Violation Logging**
    - **Validates: Requirements 10.6**
    - Test that rate limit violations are logged to audit log

  - [ ]* 13.5 Write unit tests for RateLimiter
    - Test login rate limit rejects after 5 attempts
    - Test API rate limit rejects after 100 requests
    - Test rate limit uses Redis for tracking
    - Test rate limit includes Retry-After header
    - Test rate limit resets after time window
    - Test per-key limits for API keys
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.7_


- [ ] 14. Implement two-factor authentication (2FA)
  - [ ] 14.1 Create database migrations for 2FA tables
    - Create migration for user_2fa table
    - Create migration for user_2fa_backup_codes table
    - Add indexes for performance (user_id, used status)
    - _Requirements: 11.1, 11.3, 11.7_

  - [ ] 14.2 Create TwoFactorAuthService for TOTP management
    - Create `modular_core/services/TwoFactorAuthService.php`
    - Implement generateSecret() method for TOTP secret generation
    - Implement generateQrCodeUrl() method for authenticator app setup
    - Implement generateBackupCodes() method (10 single-use codes)
    - Implement verifyCode() method for TOTP validation
    - Implement verifyBackupCode() method with single-use enforcement
    - Implement enable() method to activate 2FA for user
    - Implement disable() method requiring re-authentication
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8_

  - [ ] 14.3 Create 2FA API endpoints
    - Create POST /api/auth/2fa/enable endpoint
    - Create POST /api/auth/2fa/disable endpoint
    - Create POST /api/auth/2fa/verify endpoint
    - Update login flow to require TOTP after password for 2FA users
    - _Requirements: 11.4, 11.6_

  - [ ]* 14.4 Write property test for 2FA requirement
    - **Property 7: 2FA Required for Enabled Users**
    - **Validates: Requirements 11.4, 11.5**
    - Test that 2FA users must provide valid TOTP after password

  - [ ]* 14.5 Write property test for backup code single use
    - **Property 8: Backup Code Single Use**
    - **Validates: Requirements 11.8**
    - Test that backup codes can only be used once

  - [ ]* 14.6 Write unit tests for TwoFactorAuthService
    - Test generateSecret() returns base32 string
    - Test verifyCode() accepts valid TOTP
    - Test verifyCode() rejects invalid TOTP
    - Test backup code can only be used once
    - Test 2FA can be disabled after re-auth
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8_

- [ ] 15. Checkpoint - Transport security and authentication validation
  - Verify HTTPS enforcement is working
  - Verify rate limiting prevents brute force attacks
  - Verify 2FA setup and authentication flow works
  - Test with authenticator app (Google Authenticator, Authy)
  - Ensure all tests pass, ask the user if questions arise.


- [ ] 16. Implement encryption service for sensitive data
  - [ ] 16.1 Create EncryptionService for AES-256-GCM encryption
    - Create `modular_core/services/EncryptionService.php`
    - Implement encrypt() method using AES-256-GCM with unique IVs
    - Implement decrypt() method with error handling
    - Implement hashPassword() method using bcrypt
    - Implement verifyPassword() method for password verification
    - Implement rotateKey() method for key rotation support
    - Load master key from ENCRYPTION_MASTER_KEY environment variable
    - Store encrypted data as Base64(IV || Ciphertext || Tag)
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7, 12.8, 12.9_

  - [ ] 16.2 Encrypt existing sensitive fields in database
    - Identify all sensitive fields requiring encryption
    - Create migration to encrypt existing password fields (bcrypt)
    - Create migration to encrypt existing API key fields
    - Create migration to encrypt existing OAuth token fields
    - Create migration to encrypt payment card data if stored
    - _Requirements: 12.1, 12.2, 12.3, 12.4_

  - [ ]* 16.3 Write property test for sensitive field encryption
    - **Property 9: Sensitive Field Encryption**
    - **Validates: Requirements 12.1, 12.2, 12.3, 12.4**
    - Test that sensitive fields are encrypted, not plaintext

  - [ ]* 16.4 Write property test for unique initialization vectors
    - **Property 10: Unique Initialization Vectors**
    - **Validates: Requirements 12.7**
    - Test that encrypting same plaintext produces different ciphertexts

  - [ ]* 16.5 Write property test for encryption round trip
    - **Property 11: Encryption Round Trip**
    - **Validates: Requirements 12.8**
    - Test that encrypt then decrypt returns original value

  - [ ]* 16.6 Write unit tests for EncryptionService
    - Test encrypt/decrypt round trip
    - Test decrypt fails with wrong key
    - Test unique IV generated for each encryption
    - Test password hashing uses bcrypt
    - Test key rotation re-encrypts data
    - _Requirements: 12.5, 12.6, 12.7, 12.8, 12.9_

- [ ] 17. Implement audit logging service
  - [ ] 17.1 Create database migration for audit_logs table
    - Create audit_logs table with partitioning by month
    - Add indexes for tenant_id, user_id, event_type, created_at, ip_address
    - Include integrity_hash field for tamper detection
    - _Requirements: 13.8, 13.9_

  - [ ] 17.2 Create AuditLogger service
    - Create `modular_core/services/AuditLogger.php`
    - Implement log() method recording security events
    - Implement query() method for searching audit logs
    - Implement verifyIntegrity() method for tamper detection
    - Calculate integrity_hash as SHA-256(id || event_type || user_id || timestamp || prev_hash)
    - Define event type constants for all security events
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 13.10_

  - [ ] 17.3 Integrate audit logging into security events
    - Log successful logins
    - Log failed login attempts
    - Log password changes
    - Log 2FA enable/disable events
    - Log API key creation/revocation
    - Log sensitive data access
    - Log permission changes
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7_

  - [ ]* 17.4 Write property test for security event audit logging
    - **Property 12: Security Event Audit Logging**
    - **Validates: Requirements 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7**
    - Test that all security events are logged with required metadata

  - [ ]* 17.5 Write property test for audit log tamper detection
    - **Property 13: Audit Log Tamper Detection**
    - **Validates: Requirements 13.8**
    - Test that modified audit log entries fail integrity verification

  - [ ]* 17.6 Write unit tests for AuditLogger
    - Test login success is logged
    - Test login failure is logged
    - Test integrity hash detects tampering
    - Test query returns filtered results
    - Test 90-day retention policy
    - _Requirements: 13.1, 13.2, 13.8, 13.9, 13.10_


- [ ] 18. Implement Content Security Policy (CSP)
  - [ ] 18.1 Create CspMiddleware for CSP header injection
    - Create `modular_core/middleware/CspMiddleware.php`
    - Implement buildCspHeader() method with policy directives
    - Set default-src to 'self'
    - Set script-src to 'self' and trusted CDN domains
    - Set style-src to 'self' and 'unsafe-inline' if needed
    - Set img-src to 'self' and data:
    - Set connect-src to 'self' and API domain
    - Set frame-ancestors to 'none'
    - Set base-uri to 'self'
    - Set form-action to 'self'
    - Inject CSP header in all HTML responses
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7, 14.8, 14.9_

  - [ ] 18.2 Create CSP violation reporting endpoint
    - Create POST /api/csp-report endpoint
    - Implement handleViolationReport() method
    - Log CSP violations to audit log
    - _Requirements: 14.10_

  - [ ]* 18.3 Write property test for CSP header in HTML responses
    - **Property 14: CSP Header in HTML Responses**
    - **Validates: Requirements 14.1**
    - Test that all HTML responses include CSP header

  - [ ]* 18.4 Write property test for CSP violation logging
    - **Property 15: CSP Violation Logging**
    - **Validates: Requirements 14.10**
    - Test that CSP violations are logged to audit log

  - [ ]* 18.5 Write unit tests for CspMiddleware
    - Test CSP header included in HTML responses
    - Test CSP default-src is 'self'
    - Test CSP script-src configuration
    - Test CSP violation is logged
    - _Requirements: 14.1, 14.2, 14.3, 14.10_

- [ ] 19. Implement input validation and sanitization
  - [ ] 19.1 Create InputValidator service
    - Create `modular_core/services/InputValidator.php`
    - Implement sanitizeHtml() method removing dangerous tags/attributes
    - Implement validateEmail() method
    - Implement validateUrl() method
    - Implement escapeHtml() method for HTML context output encoding
    - Implement escapeJs() method for JavaScript context output encoding
    - _Requirements: 15.7, 15.9_

  - [ ] 19.2 Create CsrfProtection service
    - Create `modular_core/services/CsrfProtection.php`
    - Implement generateToken() method for session-based CSRF tokens
    - Implement verifyToken() method for request validation
    - Add CSRF tokens to all forms
    - Validate CSRF tokens on all state-changing requests (POST, PUT, DELETE)
    - _Requirements: 15.8_

  - [ ] 19.3 Review and secure all user input points
    - Verify all database queries use parameterized queries (already in BaseModel)
    - Apply output encoding to all user-generated content display
    - Apply input validation to all form submissions
    - Apply input sanitization to all API endpoints
    - _Requirements: 15.6, 15.7, 15.9_

  - [ ]* 19.4 Write property test for SQL injection prevention
    - **Property 16: SQL Injection Prevention**
    - **Validates: Requirements 15.6**
    - Test that queries use parameterized statements, not string concatenation

  - [ ]* 19.5 Write property test for XSS prevention
    - **Property 17: XSS Prevention via Output Encoding**
    - **Validates: Requirements 15.7**
    - Test that dangerous characters are encoded in HTML output

  - [ ]* 19.6 Write property test for CSRF token validation
    - **Property 18: CSRF Token Validation**
    - **Validates: Requirements 15.8**
    - Test that state-changing requests require valid CSRF tokens

  - [ ]* 19.7 Write property test for input sanitization
    - **Property 19: Input Sanitization**
    - **Validates: Requirements 15.9**
    - Test that user input is validated and sanitized

  - [ ]* 19.8 Write unit tests for InputValidator and CsrfProtection
    - Test sanitizeHtml() removes dangerous tags
    - Test escapeHtml() prevents XSS
    - Test CSRF token generation and validation
    - _Requirements: 15.7, 15.8, 15.9_


- [ ] 20. Implement secure session management
  - [ ] 20.1 Configure secure session cookies
    - Update session configuration to set httpOnly flag
    - Update session configuration to set secure flag (HTTPS only)
    - Update session configuration to set SameSite=Strict
    - Set session lifetime to 0 (expires on browser close)
    - _Requirements: 15.10_

  - [ ]* 20.2 Write property test for secure session cookie flags
    - **Property 20: Secure Session Cookie Flags**
    - **Validates: Requirements 15.10**
    - Test that session cookies have httpOnly and secure flags

  - [ ]* 20.3 Write unit tests for session security
    - Test session cookie has httpOnly flag
    - Test session cookie has secure flag
    - Test session cookie has SameSite=Strict
    - _Requirements: 15.10_

- [ ] 21. Checkpoint - Core security implementation validation
  - Verify encryption service encrypts sensitive data correctly
  - Verify audit logging captures all security events
  - Verify CSP headers prevent XSS attacks
  - Verify input validation prevents injection attacks
  - Verify CSRF protection works on all forms
  - Verify session cookies are secure
  - Ensure all tests pass, ask the user if questions arise.


### Phase 0.4: Security Assessment (Day 8)

- [ ] 22. Conduct OWASP ZAP security assessment
  - [ ] 22.1 Install and configure OWASP ZAP
    - Install OWASP ZAP security scanner
    - Configure ZAP for NexSaaS application scanning
    - Set up authentication context for authenticated scans
    - Configure scan policies and scope
    - _Requirements: 15.1_

  - [ ] 22.2 Run automated security scans
    - Run OWASP ZAP baseline scan
    - Run OWASP ZAP full scan with spider
    - Run authenticated scan for protected endpoints
    - Generate scan report with findings
    - _Requirements: 15.1_

  - [ ] 22.3 Triage and fix critical/high vulnerabilities
    - Review all scan findings
    - Prioritize critical and high severity vulnerabilities
    - Fix all critical severity vulnerabilities
    - Fix all high severity vulnerabilities
    - Document findings and remediation in SECURITY_ASSESSMENT.md
    - _Requirements: 15.2, 15.3, 15.4, 15.5_

  - [ ] 22.4 Re-scan to verify fixes
    - Run OWASP ZAP scan again after fixes
    - Verify critical and high vulnerabilities are resolved
    - Document remaining medium/low severity issues
    - Create remediation plan for remaining issues
    - _Requirements: 15.2, 15.3, 15.4, 15.5_

- [ ] 23. Create security assessment documentation
  - [ ] 23.1 Write SECURITY_ASSESSMENT.md
    - Document all identified vulnerabilities
    - Document remediation status for each vulnerability
    - Include OWASP ZAP scan reports
    - Document security testing methodology
    - Include recommendations for ongoing security monitoring
    - _Requirements: 15.4, 15.5_

- [ ] 24. Final security validation and testing
  - [ ] 24.1 Run complete test suite
    - Run all unit tests (100% pass rate required)
    - Run all property-based tests (20 properties × 100 iterations)
    - Verify all security tests pass
    - Generate test coverage report
    - _Requirements: All_

  - [ ] 24.2 Manual security review
    - Review all security components for completeness
    - Verify HTTPS enforcement works end-to-end
    - Verify rate limiting prevents brute force attacks
    - Verify 2FA authentication flow works correctly
    - Verify encryption protects sensitive data
    - Verify audit logging captures all security events
    - Verify CSP prevents XSS attacks
    - Verify input validation prevents injection attacks
    - _Requirements: All_

  - [ ] 24.3 Verify legal documentation completeness
    - Verify LICENSING_STRATEGY.md is complete
    - Verify COMMERCIAL_LICENSE.md is complete
    - Verify TERMS_OF_SERVICE.md is complete
    - Verify PRIVACY_POLICY.md is complete
    - Recommend legal counsel review before production
    - _Requirements: 5.1, 6.1, 7.1, 8.1_

- [ ] 25. Final checkpoint - Phase 0 completion validation
  - Verify git history contains no credential files
  - Verify all configuration loads from environment variables
  - Verify all legal documents are complete
  - Verify HTTPS enforcement is working
  - Verify rate limiting is working
  - Verify 2FA is working
  - Verify encryption is working
  - Verify audit logging is working
  - Verify CSP is working
  - Verify input validation is working
  - Verify OWASP ZAP shows zero critical/high vulnerabilities
  - Verify all tests pass (unit + property-based)
  - Document completion status and next steps
  - Ensure all tests pass, ask the user if questions arise.


## Notes

- Tasks marked with `*` are optional testing tasks and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties (20 properties × 100 iterations = 2000 test cases)
- Unit tests validate specific examples and edge cases
- This is a BLOCKING phase - must be completed before customer-facing work
- Legal documents should be reviewed by legal counsel before production use
- OWASP ZAP scans should be run regularly as part of ongoing security monitoring
- All sensitive configuration must be loaded from environment variables, never hardcoded
- Git history cleanup is irreversible - ensure backup is created first

## Success Criteria

Phase 0 is complete when:
1. Git history contains no credential files (verified with git log search)
2. All configuration loads from environment variables (no hardcoded credentials)
3. All legal documents are complete and reviewed
4. HTTPS enforcement is working (all HTTP redirects to HTTPS)
5. Rate limiting prevents brute force attacks (tested with load testing)
6. 2FA authentication flow works correctly (tested with authenticator app)
7. Encryption protects sensitive data (verified with database inspection)
8. Audit logging captures all security events (verified with log queries)
9. CSP prevents XSS attacks (tested with browser dev tools)
10. Input validation prevents injection attacks (verified with security tests)
11. OWASP ZAP scan shows zero critical/high vulnerabilities
12. All unit tests pass (100% pass rate)
13. All property-based tests pass (20 properties × 100 iterations = 2000 test cases)
14. Manual security review completed and documented

## Implementation Notes

**Configuration Security:**
- Use `git filter-repo` for history cleanup (preferred over BFG)
- Create full repository backup before history rewrite
- Coordinate force push with team to avoid conflicts
- Test configuration loading in dev/staging before production

**Legal Documentation:**
- Legal documents are templates and should be reviewed by legal counsel
- AGPL compliance is critical for hosted services
- Commercial licensing provides alternative to AGPL obligations
- Privacy policy must comply with GDPR for EU users

**Security Implementation:**
- Use PHPUnit with Eris for property-based testing
- Run OWASP ZAP scans regularly, not just once
- Encrypt sensitive fields before storing in database
- Use bcrypt for password hashing, AES-256-GCM for other data
- Audit logs should be tamper-evident with integrity hashes
- CSP policy may need adjustment based on frontend framework requirements
- Rate limiting requires Redis for distributed tracking

**Testing Strategy:**
- Property-based tests verify universal security properties
- Unit tests verify specific scenarios and edge cases
- Integration tests verify end-to-end security flows
- Manual testing required for 2FA with authenticator app
- Security scanning with OWASP ZAP for vulnerability assessment

## Next Steps After Phase 0

Once Phase 0 is complete, the platform will have:
- Secure configuration management with no credential exposure
- Comprehensive legal documentation for commercial operations
- Industry-standard security controls (HTTPS, rate limiting, 2FA, encryption)
- Audit trail for security-relevant events
- Protection against common web vulnerabilities

The platform will be ready for:
- Customer-facing work and demos
- Sales activities and customer onboarding
- Production deployment with confidence
- Subsequent development phases (UX polish, AI engine, billing, etc.)

**IMPORTANT:** This phase is BLOCKING for all subsequent phases. Do not proceed with customer-facing work until Phase 0 is complete and validated.
