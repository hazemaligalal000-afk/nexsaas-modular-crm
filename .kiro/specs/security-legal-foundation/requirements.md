# Requirements Document

## Introduction

This document defines the requirements for Phase 0: Security & Legal Foundation for NexSaaS. This is the CRITICAL first phase that must be completed before any customer-facing work, sales activities, or customer demos. The goal is to establish security hardening and legal compliance foundations to ensure the platform is production-ready and legally compliant for commercial use.

This phase addresses three critical areas:
1. Configuration security hardening to prevent credential leaks
2. AGPL licensing strategy and legal documentation for commercial operations
3. Core security hardening to protect against common vulnerabilities

Estimated completion time: 8 days

## Glossary

- **NexSaaS_Platform**: The complete NexSaaS application system including backend, frontend, and database
- **Config_Manager**: The system component responsible for loading and managing configuration settings
- **Git_History**: The version control history containing all past commits and file changes
- **Environment_Variables**: Operating system or container-level configuration values stored outside the codebase
- **Credential**: Any sensitive authentication information including passwords, API keys, tokens, or secrets
- **AGPL**: GNU Affero General Public License version 3, a copyleft license requiring source code disclosure
- **Commercial_License**: A proprietary license allowing use without AGPL obligations
- **Rate_Limiter**: A system component that restricts the frequency of requests to prevent abuse
- **TOTP**: Time-based One-Time Password, a 2FA authentication method
- **Audit_Logger**: A system component that records security-relevant events for compliance and forensics
- **CSP**: Content Security Policy, an HTTP header that prevents XSS attacks
- **OWASP_ZAP**: Open Web Application Security Project Zed Attack Proxy, a security testing tool
- **Redis**: An in-memory data store used for caching and rate limiting
- **HTTPS**: HTTP Secure, encrypted HTTP communication using TLS/SSL
- **Encryption_Service**: A system component that encrypts and decrypts sensitive data
- **Login_Endpoint**: The API endpoint that processes user authentication requests
- **API_Endpoint**: Any HTTP endpoint that provides programmatic access to system functionality

## Requirements

### Requirement 1: Remove Credentials from Git History

**User Story:** As a security engineer, I want all sensitive configuration files removed from git history, so that credentials cannot be extracted by attackers or unauthorized parties.

#### Acceptance Criteria

1. THE NexSaaS_Platform SHALL identify all commits containing config.inc.php in Git_History
2. THE NexSaaS_Platform SHALL identify all commits containing config.db.php in Git_History
3. THE NexSaaS_Platform SHALL remove config.inc.php from Git_History using git filter-repo or BFG Repo-Cleaner
4. THE NexSaaS_Platform SHALL remove config.db.php from Git_History using git filter-repo or BFG Repo-Cleaner
5. WHEN Git_History rewrite is complete, THE NexSaaS_Platform SHALL verify that no Credential remains in any commit
6. THE NexSaaS_Platform SHALL document the history rewrite process in a SECURITY_CLEANUP.md file

### Requirement 2: Implement Environment Variable Configuration

**User Story:** As a developer, I want configuration loaded from environment variables, so that sensitive credentials are never stored in the codebase.

#### Acceptance Criteria

1. THE Config_Manager SHALL load all database credentials from Environment_Variables
2. THE Config_Manager SHALL load all API keys from Environment_Variables
3. THE Config_Manager SHALL load all encryption keys from Environment_Variables
4. THE Config_Manager SHALL load all third-party service credentials from Environment_Variables
5. WHEN an Environment_Variable is missing, THE Config_Manager SHALL throw a descriptive error with the variable name
6. WHEN an Environment_Variable is empty, THE Config_Manager SHALL throw a descriptive error
7. THE Config_Manager SHALL NOT use hardcoded fallback values for Credential variables
8. THE Config_Manager SHALL support loading Environment_Variables from a .env file in development environments

### Requirement 3: Create Configuration Documentation

**User Story:** As a developer, I want comprehensive configuration documentation, so that I can set up the application correctly without exposing credentials.

#### Acceptance Criteria

1. THE NexSaaS_Platform SHALL provide a .env.example file containing all required Environment_Variables
2. THE .env.example file SHALL include descriptive comments for each Environment_Variable
3. THE .env.example file SHALL use placeholder values that clearly indicate the expected format
4. THE .env.example file SHALL NOT contain any real Credential values
5. THE NexSaaS_Platform SHALL provide a CONFIG.md file documenting all Environment_Variables
6. THE CONFIG.md file SHALL specify which Environment_Variables are required versus optional
7. THE CONFIG.md file SHALL document the expected format and validation rules for each Environment_Variable

### Requirement 4: Prevent Future Credential Leaks

**User Story:** As a security engineer, I want git configured to prevent credential commits, so that developers cannot accidentally commit sensitive files.

#### Acceptance Criteria

1. THE NexSaaS_Platform SHALL include .env in .gitignore
2. THE NexSaaS_Platform SHALL include config.inc.php in .gitignore
3. THE NexSaaS_Platform SHALL include config.db.php in .gitignore
4. THE NexSaaS_Platform SHALL include any file matching *.key in .gitignore
5. THE NexSaaS_Platform SHALL include any file matching *.pem in .gitignore
6. THE NexSaaS_Platform SHALL include any file matching *secret* in .gitignore
7. WHEN a developer attempts to commit a file matching .gitignore patterns, THE Git system SHALL reject the commit

### Requirement 5: Document AGPL Licensing Strategy

**User Story:** As a business owner, I want a clear licensing strategy documented, so that I understand my legal obligations and commercial options.

#### Acceptance Criteria

1. THE NexSaaS_Platform SHALL provide a LICENSING_STRATEGY.md file
2. THE LICENSING_STRATEGY.md file SHALL explain AGPL obligations for hosted services
3. THE LICENSING_STRATEGY.md file SHALL document the chosen commercial approach
4. THE LICENSING_STRATEGY.md file SHALL clarify whether source code will be made available to customers
5. THE LICENSING_STRATEGY.md file SHALL document the process for purchasing a Commercial_License
6. THE LICENSING_STRATEGY.md file SHALL identify all AGPL-licensed dependencies
7. THE LICENSING_STRATEGY.md file SHALL document any planned core rewrites to avoid AGPL obligations

### Requirement 6: Create Commercial License Document

**User Story:** As a business owner, I want a commercial license document, so that customers can use the software without AGPL obligations.

#### Acceptance Criteria

1. THE NexSaaS_Platform SHALL provide a COMMERCIAL_LICENSE.md file
2. THE COMMERCIAL_LICENSE.md file SHALL specify the license grant and permitted uses
3. THE COMMERCIAL_LICENSE.md file SHALL specify restrictions on redistribution
4. THE COMMERCIAL_LICENSE.md file SHALL specify warranty disclaimers
5. THE COMMERCIAL_LICENSE.md file SHALL specify liability limitations
6. THE COMMERCIAL_LICENSE.md file SHALL specify the license fee structure or reference pricing
7. THE COMMERCIAL_LICENSE.md file SHALL specify the license term and renewal conditions

### Requirement 7: Create Terms of Service

**User Story:** As a business owner, I want terms of service, so that I have legal protection when providing the hosted service to customers.

#### Acceptance Criteria

1. THE NexSaaS_Platform SHALL provide a TERMS_OF_SERVICE.md file
2. THE TERMS_OF_SERVICE.md file SHALL define acceptable use policies
3. THE TERMS_OF_SERVICE.md file SHALL define service availability and uptime commitments
4. THE TERMS_OF_SERVICE.md file SHALL define data ownership and retention policies
5. THE TERMS_OF_SERVICE.md file SHALL define payment terms and refund policies
6. THE TERMS_OF_SERVICE.md file SHALL define termination conditions
7. THE TERMS_OF_SERVICE.md file SHALL define dispute resolution procedures
8. THE TERMS_OF_SERVICE.md file SHALL include limitation of liability clauses
9. THE TERMS_OF_SERVICE.md file SHALL specify the governing law and jurisdiction

### Requirement 8: Create Privacy Policy

**User Story:** As a business owner, I want a privacy policy, so that I comply with data protection regulations and inform users about data handling.

#### Acceptance Criteria

1. THE NexSaaS_Platform SHALL provide a PRIVACY_POLICY.md file
2. THE PRIVACY_POLICY.md file SHALL identify what personal data is collected
3. THE PRIVACY_POLICY.md file SHALL explain how personal data is used
4. THE PRIVACY_POLICY.md file SHALL explain how personal data is stored and protected
5. THE PRIVACY_POLICY.md file SHALL explain data retention periods
6. THE PRIVACY_POLICY.md file SHALL explain user rights regarding their data
7. THE PRIVACY_POLICY.md file SHALL explain the process for data deletion requests
8. THE PRIVACY_POLICY.md file SHALL identify any third-party data processors
9. THE PRIVACY_POLICY.md file SHALL explain cookie usage and tracking
10. THE PRIVACY_POLICY.md file SHALL comply with GDPR requirements for EU users

### Requirement 9: Force HTTPS-Only Communication

**User Story:** As a security engineer, I want all traffic forced to HTTPS, so that credentials and sensitive data cannot be intercepted.

#### Acceptance Criteria

1. WHEN a request is received over HTTP, THE NexSaaS_Platform SHALL redirect to HTTPS with a 301 status code
2. THE NexSaaS_Platform SHALL include the Strict-Transport-Security header in all responses
3. THE Strict-Transport-Security header SHALL specify a max-age of at least 31536000 seconds
4. THE Strict-Transport-Security header SHALL include the includeSubDomains directive
5. THE NexSaaS_Platform SHALL NOT serve any content over HTTP except for the HTTPS redirect
6. THE NexSaaS_Platform SHALL reject requests that do not use TLS 1.2 or higher

### Requirement 10: Implement Rate Limiting

**User Story:** As a security engineer, I want rate limiting on authentication and API endpoints, so that brute force attacks and API abuse are prevented.

#### Acceptance Criteria

1. WHEN a Login_Endpoint receives more than 5 requests from the same IP within 15 minutes, THE Rate_Limiter SHALL reject subsequent requests with HTTP 429
2. WHEN an API_Endpoint receives more than 100 requests from the same user within 1 minute, THE Rate_Limiter SHALL reject subsequent requests with HTTP 429
3. THE Rate_Limiter SHALL use Redis for distributed rate limit tracking
4. WHEN a rate limit is exceeded, THE Rate_Limiter SHALL include a Retry-After header in the response
5. THE Rate_Limiter SHALL reset counters after the time window expires
6. THE Rate_Limiter SHALL log all rate limit violations to the Audit_Logger
7. WHERE an API key is provided, THE Rate_Limiter SHALL apply per-key limits instead of per-IP limits

### Requirement 11: Implement Two-Factor Authentication

**User Story:** As a user, I want optional two-factor authentication, so that my account has additional protection against unauthorized access.

#### Acceptance Criteria

1. WHERE a user enables 2FA, THE NexSaaS_Platform SHALL support TOTP-based authentication
2. WHEN a user enables 2FA, THE NexSaaS_Platform SHALL generate a QR code for authenticator app setup
3. WHEN a user enables 2FA, THE NexSaaS_Platform SHALL generate backup codes for account recovery
4. WHEN a user with 2FA enabled logs in, THE NexSaaS_Platform SHALL require a valid TOTP code after password verification
5. WHEN an invalid TOTP code is provided, THE NexSaaS_Platform SHALL reject the login attempt
6. THE NexSaaS_Platform SHALL allow users to disable 2FA after re-authentication
7. THE NexSaaS_Platform SHALL support account recovery using backup codes when TOTP is unavailable
8. WHEN a backup code is used, THE NexSaaS_Platform SHALL invalidate that code to prevent reuse

### Requirement 12: Encrypt Sensitive Database Fields

**User Story:** As a security engineer, I want sensitive data encrypted in the database, so that data breaches do not expose plaintext credentials or personal information.

#### Acceptance Criteria

1. THE Encryption_Service SHALL encrypt all password fields before database storage
2. THE Encryption_Service SHALL encrypt all API key fields before database storage
3. THE Encryption_Service SHALL encrypt all OAuth token fields before database storage
4. THE Encryption_Service SHALL encrypt all payment card data before database storage
5. THE Encryption_Service SHALL use AES-256-GCM for symmetric encryption
6. THE Encryption_Service SHALL derive encryption keys from a master key stored in Environment_Variables
7. THE Encryption_Service SHALL use unique initialization vectors for each encrypted value
8. WHEN encrypted data is retrieved, THE Encryption_Service SHALL decrypt it transparently for authorized requests
9. THE Encryption_Service SHALL implement key rotation support for the master encryption key

### Requirement 13: Implement Audit Logging

**User Story:** As a compliance officer, I want comprehensive audit logs, so that I can track security-relevant events and investigate incidents.

#### Acceptance Criteria

1. WHEN a user logs in successfully, THE Audit_Logger SHALL record the event with timestamp, user ID, and IP address
2. WHEN a login attempt fails, THE Audit_Logger SHALL record the event with timestamp, username, and IP address
3. WHEN a user changes their password, THE Audit_Logger SHALL record the event with timestamp and user ID
4. WHEN a user enables or disables 2FA, THE Audit_Logger SHALL record the event with timestamp and user ID
5. WHEN an API key is created or revoked, THE Audit_Logger SHALL record the event with timestamp and user ID
6. WHEN sensitive data is accessed, THE Audit_Logger SHALL record the event with timestamp, user ID, and resource identifier
7. WHEN a permission is granted or revoked, THE Audit_Logger SHALL record the event with timestamp, user ID, and permission details
8. THE Audit_Logger SHALL store logs in a tamper-evident format
9. THE Audit_Logger SHALL retain logs for at least 90 days
10. THE Audit_Logger SHALL provide a query interface for security investigations

### Requirement 14: Implement Content Security Policy

**User Story:** As a security engineer, I want Content Security Policy headers, so that cross-site scripting attacks are mitigated.

#### Acceptance Criteria

1. THE NexSaaS_Platform SHALL include a Content-Security-Policy header in all HTML responses
2. THE CSP SHALL set default-src to 'self'
3. THE CSP SHALL set script-src to 'self' and specific trusted CDN domains
4. THE CSP SHALL set style-src to 'self' and 'unsafe-inline' only if required by the framework
5. THE CSP SHALL set img-src to 'self' and data: for inline images
6. THE CSP SHALL set connect-src to 'self' and the API domain
7. THE CSP SHALL set frame-ancestors to 'none' to prevent clickjacking
8. THE CSP SHALL set base-uri to 'self'
9. THE CSP SHALL set form-action to 'self'
10. WHEN a CSP violation occurs, THE NexSaaS_Platform SHALL log the violation for security monitoring

### Requirement 15: Security Vulnerability Assessment

**User Story:** As a security engineer, I want critical vulnerabilities identified and fixed, so that the platform is secure before customer deployment.

#### Acceptance Criteria

1. THE NexSaaS_Platform SHALL be scanned using OWASP_ZAP automated scanner
2. WHEN OWASP_ZAP identifies a critical severity vulnerability, THE development team SHALL fix it before Phase 0 completion
3. WHEN OWASP_ZAP identifies a high severity vulnerability, THE development team SHALL fix it before Phase 0 completion
4. THE NexSaaS_Platform SHALL document all identified vulnerabilities in a SECURITY_ASSESSMENT.md file
5. THE SECURITY_ASSESSMENT.md file SHALL document the remediation status for each vulnerability
6. THE NexSaaS_Platform SHALL implement SQL injection prevention using parameterized queries
7. THE NexSaaS_Platform SHALL implement XSS prevention using output encoding
8. THE NexSaaS_Platform SHALL implement CSRF protection using tokens
9. THE NexSaaS_Platform SHALL validate and sanitize all user inputs
10. THE NexSaaS_Platform SHALL implement secure session management with httpOnly and secure flags on cookies
