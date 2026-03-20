# Implementation Plan: Onboarding & Multi-Tenancy

## Overview

This implementation plan covers Phase 2 of NexSaaS: self-service customer onboarding and multi-tenant architecture. The system will enable customers to sign up, complete a 5-step wizard, and receive an isolated environment with automatic subdomain provisioning, database schema creation, and sample data seeding. The multi-tenancy layer ensures complete data isolation through schema-per-tenant architecture, query interception, file storage segregation, and cache key prefixing. The deployment uses Kubernetes with Helm charts, horizontal pod autoscaling, and persistent volumes.

Implementation will be in PHP, integrating with the existing modular_core architecture.

## Tasks

### Component 1: Onboarding Foundation

- [ ] 1. Set up onboarding module structure and database tables
  - Create modular_core/modules/Platform/Onboarding directory structure
  - Create migration for tenants, email_verifications, team_invitations, onboarding_checklists, and welcome_email_schedule tables
  - Define routes for signup, verification, and wizard endpoints
  - _Requirements: 1.1, 1.2, 2.1_

- [ ] 2. Implement signup and email verification
  - [ ] 2.1 Create SignupController with registration endpoint
    - Implement email/password validation
    - Hash passwords with bcrypt
    - Create unverified user account
    - _Requirements: 1.2, 1.3_
  
  - [ ] 2.2 Create EmailVerificationService
    - Generate secure 64-character tokens
    - Store tokens with 24-hour expiration
    - Send verification emails via queue
    - _Requirements: 1.3, 1.4_
  
  - [ ] 2.3 Create email verification endpoint
    - Validate token and expiration
    - Mark account as verified
    - Redirect to onboarding wizard
    - _Requirements: 1.5, 1.6_
  
  - [ ]* 2.4 Write property tests for email verification
    - **Property 1: Email Verification Token Uniqueness**
    - **Property 2: Email Verification Expiration**
    - **Validates: Requirements 1.3, 1.4, 1.6**

- [ ] 3. Implement onboarding wizard UI and backend
  - [ ] 3.1 Create OnboardingWizardController with 5-step flow
    - Implement step navigation logic
    - Store wizard state in session
    - Validate step completion
    - _Requirements: 2.1, 2.10, 2.11_
  
  - [ ] 3.2 Implement Step 1: Company Profile
    - Accept company logo upload
    - Industry dropdown selection
    - Company size selection
    - _Requirements: 2.2_
  
  - [ ] 3.3 Implement Step 2: Team Invitations (optional)
    - Email address input with validation
    - Allow skipping
    - _Requirements: 2.3, 2.4_
  
  - [ ] 3.4 Implement Step 3: CSV Lead Import (optional)
    - File upload with validation
    - Allow skipping
    - _Requirements: 2.5, 2.6_
  
  - [ ] 3.5 Implement Step 4: WhatsApp Connection (optional)
    - Display connection instructions
    - Allow skipping
    - _Requirements: 2.7, 2.8_
  
  - [ ] 3.6 Implement Step 5: Plan Selection
    - Display available plans
    - Initiate 14-day trial
    - Redirect to dashboard on completion
    - _Requirements: 2.9, 2.12_
  
  - [ ]* 3.7 Write property tests for wizard flow
    - **Property 13: Wizard Step Sequence**
    - **Property 14: Wizard Progress Calculation**
    - **Validates: Requirements 2.1, 2.10**


- [ ] 4. Checkpoint - Verify onboarding foundation
  - Ensure all tests pass, ask the user if questions arise.

### Component 2: Subdomain & Schema Provisioning

- [ ] 5. Implement subdomain provisioning service
  - [ ] 5.1 Create SubdomainProvisioner class
    - Implement subdomain sanitization (remove special chars, lowercase)
    - Check uniqueness and append numeric suffix if needed
    - Store subdomain in tenants table
    - _Requirements: 3.1, 3.2, 3.3, 3.6_
  
  - [ ] 5.2 Integrate DNS configuration
    - Create DNS records via API (Route53, Cloudflare, or similar)
    - Implement retry logic with 60-second timeout
    - Verify DNS resolution before marking complete
    - _Requirements: 3.4, 3.7_
  
  - [ ] 5.3 Integrate SSL certificate provisioning
    - Use Let's Encrypt or cert-manager for SSL
    - Handle certificate generation and renewal
    - Rollback DNS on SSL failure
    - _Requirements: 3.5_
  
  - [ ]* 5.4 Write property tests for subdomain provisioning
    - **Property 3: Subdomain Sanitization Idempotence**
    - **Property 4: Subdomain Uniqueness**
    - **Property 5: DNS Resolution Consistency**
    - **Validates: Requirements 3.2, 3.3, 3.7**

- [ ] 6. Implement tenant schema creation
  - [ ] 6.1 Create TenantSchemaManager class
    - Generate schema name as tenant_{id}
    - Execute CREATE SCHEMA statement
    - Store schema name in tenants table
    - _Requirements: 4.1, 4.4_
  
  - [ ] 6.2 Run migrations in tenant schema
    - Read all migration files from database/migrations
    - Execute each migration in tenant schema context
    - Complete within 120 seconds
    - Handle rollback on failure
    - _Requirements: 4.2, 4.5_
  
  - [ ] 6.3 Create restricted database user per tenant
    - Generate username as tenant_{id}_user
    - Create user with secure password
    - Grant permissions only to tenant schema
    - _Requirements: 4.3_
  
  - [ ] 6.4 Implement schema isolation verification
    - Execute test query in tenant schema
    - Verify no cross-tenant data access
    - _Requirements: 4.6_
  
  - [ ]* 6.5 Write property tests for schema creation
    - **Property 6: Schema Name Format**
    - **Property 7: Schema Isolation**
    - **Property 8: Migration Completeness**
    - **Validates: Requirements 4.1, 4.2, 4.6**

- [ ] 7. Implement data seeding service
  - [ ] 7.1 Create DataSeeder class
    - Switch to tenant schema context
    - Create 2 sample users (admin, sales rep)
    - Create 10 sample leads with demo flag
    - Create 5 sample contacts with demo flag
    - Create 3 sample deals with demo flag
    - Complete within 30 seconds
    - _Requirements: 5.1, 5.2, 5.3, 5.4_
  
  - [ ]* 7.2 Write property tests for data seeding
    - **Property 9: Sample Data Counts**
    - **Property 10: Sample Data Flag**
    - **Validates: Requirements 5.1, 5.3**

- [ ] 8. Checkpoint - Verify provisioning services
  - Ensure all tests pass, ask the user if questions arise.


### Component 3: Multi-Tenancy Core

- [ ] 9. Implement tenant context middleware
  - [ ] 9.1 Create TenantMiddleware class
    - Extract subdomain from Host header
    - Lookup tenant by subdomain
    - Return 404 for invalid/inactive tenants
    - Check trial expiration and redirect to upgrade
    - Set tenant context in request
    - _Requirements: 7.1, 7.2, 7.3_
  
  - [ ] 9.2 Implement database schema switching
    - Switch database connection to tenant schema
    - Store tenant context for request lifecycle
    - _Requirements: 7.4_
  
  - [ ]* 9.3 Write property tests for tenant middleware
    - **Property 21: Tenant Context Extraction**
    - **Property 22: Invalid Tenant Rejection**
    - **Validates: Requirements 7.1, 7.3**

- [ ] 10. Implement query interceptor for tenant isolation
  - [ ] 10.1 Create QueryInterceptor class
    - Detect query type (SELECT, INSERT, UPDATE, DELETE)
    - Inject WHERE tenant_id = {current_tenant_id} for SELECT/UPDATE/DELETE
    - Auto-set tenant_id for INSERT queries
    - Prevent manual tenant_id override
    - Throw error for schema modification queries
    - _Requirements: 7.5, 7.6, 7.7_
  
  - [ ]* 10.2 Write property tests for query interception
    - **Property 23: Query Interception for SELECT**
    - **Property 24: Query Interception for INSERT**
    - **Property 25: Query Interception for UPDATE**
    - **Property 26: Query Interception for DELETE**
    - **Property 27: Tenant ID Override Prevention**
    - **Validates: Requirements 7.5, 7.6, 7.7**

- [ ] 11. Implement file storage isolation
  - [ ] 11.1 Create StorageIsolator class
    - Generate UUID-based filenames
    - Build isolated path: /storage/tenant_{id}/{type}/{filename}
    - Store files with tenant verification
    - _Requirements: 8.1, 8.2_
  
  - [ ] 11.2 Implement file retrieval with authorization
    - Extract tenant ID from file path
    - Verify requesting tenant matches file owner
    - Return 403 for cross-tenant access attempts
    - _Requirements: 8.3, 8.4_
  
  - [ ] 11.3 Apply isolation to temporary and cache directories
    - Use same tenant-prefixed paths for temp files
    - Support cloud storage with bucket prefixes
    - _Requirements: 8.5, 8.6_
  
  - [ ]* 11.4 Write property tests for file storage isolation
    - **Property 28: File Path Isolation**
    - **Property 29: File Name Uniqueness**
    - **Property 30: File Access Authorization**
    - **Validates: Requirements 8.1, 8.2, 8.3, 8.4**

- [ ] 12. Implement Redis cache key isolation
  - [ ] 12.1 Create CacheIsolator class
    - Prefix all cache keys with tenant:{id}:
    - Implement get/set/delete with automatic prefixing
    - Configure key expiration policies per tenant
    - _Requirements: 9.1, 9.2, 9.3_
  
  - [ ] 12.2 Implement cache cleanup on tenant deletion
    - Purge all keys matching tenant prefix
    - Complete within 60 seconds
    - _Requirements: 9.4_
  
  - [ ]* 12.3 Write property tests for cache isolation
    - **Property 31: Redis Key Prefixing**
    - **Property 32: Redis Key Isolation**
    - **Property 33: Redis Key Cleanup on Tenant Deletion**
    - **Validates: Requirements 9.1, 9.2, 9.4**

- [ ] 13. Implement automated tenant isolation test suite
  - [ ] 13.1 Create TenantIsolationTestSuite class
    - Create 2 test tenants with sample data
    - Test database query isolation
    - Test file access isolation
    - Test cache key isolation
    - Test API authorization isolation
    - Execute in under 60 seconds
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_
  
  - [ ] 13.2 Integrate isolation tests into CI/CD pipeline
    - Add test suite to GitHub Actions workflow
    - Block deployments on test failure
    - _Requirements: 10.7_
  
  - [ ]* 13.3 Write property tests for isolation test suite
    - **Property 34: Cross-Tenant Data Query Isolation**
    - **Property 35: Cross-Tenant File Access Denial**
    - **Property 36: Cross-Tenant Cache Isolation**
    - **Property 37: Cross-Tenant API Authorization**
    - **Property 38: Isolation Test Suite Execution Time**
    - **Property 39: Isolation Test Coverage**
    - **Property 40: CI/CD Isolation Test Enforcement**
    - **Validates: Requirements 10.1-10.7**

- [ ] 14. Checkpoint - Verify multi-tenancy core
  - Ensure all tests pass, ask the user if questions arise.


### Component 4: Onboarding Enhancements

- [ ] 15. Implement CSV lead import service
  - [ ] 15.1 Create CSVImportService class
    - Validate file size (max 10MB)
    - Parse CSV with max 10,000 rows
    - Validate required columns (name, email)
    - Validate email format (RFC 5322)
    - Import leads with error tracking
    - Return import summary with success/failure counts
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5, 17.6_
  
  - [ ] 15.2 Support optional CSV columns
    - Import phone, company, status fields if present
    - Map to corresponding lead attributes
    - _Requirements: 17.7_
  
  - [ ]* 15.3 Write property tests for CSV import
    - **Property 15: CSV Row Limit**
    - **Property 16: CSV Email Validation**
    - **Validates: Requirements 17.2, 17.3**

- [ ] 16. Implement team invitation system
  - [ ] 16.1 Create TeamInvitationService class
    - Validate email format and prevent duplicates
    - Generate unique invitation tokens (7-day expiration)
    - Send invitation emails via queue
    - _Requirements: 18.1, 18.2_
  
  - [ ] 16.2 Create invitation acceptance endpoint
    - Validate invitation token and expiration
    - Display signup form pre-filled with email
    - Add user to tenant with "User" role
    - _Requirements: 18.3, 18.4_
  
  - [ ] 16.3 Implement invitation management
    - Allow resending pending invitations
    - Mark expired invitations
    - Allow sending new invitations after expiration
    - _Requirements: 18.5, 18.6_
  
  - [ ]* 16.4 Write property tests for team invitations
    - **Property 17: Team Invitation Token Uniqueness**
    - **Property 18: Invitation Expiration**
    - **Validates: Requirements 18.2**

- [ ] 17. Implement trial period management
  - [ ] 17.1 Create TrialManager class
    - Set trial end date to 14 days on tenant creation
    - Display trial banner with days remaining
    - Restrict access on trial expiration
    - _Requirements: 16.1, 16.2, 16.3_
  
  - [ ] 17.2 Implement trial reminder emails
    - Schedule reminders on Day 10, Day 13, Day 14
    - Send via email queue
    - _Requirements: 16.4_
  
  - [ ] 17.3 Implement trial upgrade functionality
    - Change tenant status from 'trial' to 'active'
    - Remove trial restrictions
    - Cancel remaining reminder emails
    - _Requirements: 16.5_
  
  - [ ] 17.4 Implement trial extension (admin only)
    - Allow up to 7 additional days
    - Require admin approval
    - _Requirements: 16.6_
  
  - [ ]* 17.5 Write property tests for trial management
    - **Property 62: Trial Period Duration**
    - **Property 63: Trial Banner Display**
    - **Property 64: Trial Expiration Access Restriction**
    - **Property 65: Trial Reminder Emails**
    - **Property 66: Trial Upgrade Status Change**
    - **Validates: Requirements 16.1-16.5**

- [ ] 18. Implement welcome email sequence
  - [ ] 18.1 Create WelcomeEmailSequencer class
    - Schedule 3 emails on Day 0, Day 3, Day 7
    - Store schedule in welcome_email_schedule table
    - _Requirements: 6.1_
  
  - [ ] 18.2 Create email templates
    - Day 0: Login credentials, subdomain URL, quick start guide
    - Day 3: Tips for importing data and inviting team
    - Day 7: Information about upgrading to paid plan
    - _Requirements: 6.2, 6.3, 6.4_
  
  - [ ] 18.3 Implement email cancellation on upgrade
    - Cancel unsent emails when tenant upgrades
    - _Requirements: 6.5_
  
  - [ ] 18.4 Track email engagement
    - Track email opens and clicks
    - Store analytics in welcome_email_schedule table
    - _Requirements: 6.6_
  
  - [ ]* 18.5 Write property tests for welcome emails
    - **Property 11: Welcome Email Scheduling**
    - **Property 12: Welcome Email Cancellation on Upgrade**
    - **Validates: Requirements 6.1, 6.5**

- [ ] 19. Implement onboarding checklist tracking
  - [ ] 19.1 Create OnboardingChecklistService class
    - Display 5 tasks in dashboard widget
    - Track task completion based on user actions
    - Calculate completion percentage
    - _Requirements: 19.1, 19.2, 19.3_
  
  - [ ] 19.2 Implement checklist completion detection
    - "Explore Sample Leads" - mark complete on first lead view
    - "Create Your First Deal" - mark complete on first deal creation
    - "Invite Team Members" - mark complete on first invitation sent
    - "Import Real Data" - mark complete on first CSV import
    - "Connect WhatsApp" - mark complete on WhatsApp connection
    - _Requirements: 5.6, 19.2_
  
  - [ ] 19.3 Implement checklist UI controls
    - Display congratulations message on 100% completion
    - Allow manual dismissal of checklist widget
    - Track completion metrics for analytics
    - _Requirements: 19.4, 19.5, 19.6_
  
  - [ ]* 19.4 Write property tests for checklist tracking
    - **Property 19: Checklist Task Uniqueness**
    - **Property 20: Checklist Completion Percentage**
    - **Validates: Requirements 19.1, 19.3**

- [ ] 20. Checkpoint - Verify onboarding enhancements
  - Ensure all tests pass, ask the user if questions arise.


### Component 5: Kubernetes Deployment

- [ ] 21. Create Helm chart structure and base templates
  - [ ] 21.1 Initialize Helm chart directory
    - Create helm/nexsaas directory structure
    - Create Chart.yaml with metadata
    - Create values.yaml with default configuration
    - _Requirements: 11.1, 11.2_
  
  - [ ] 21.2 Create deployment templates
    - Create templates/php-fpm-deployment.yaml
    - Create templates/nginx-deployment.yaml
    - Create templates/mysql-deployment.yaml
    - Create templates/redis-deployment.yaml
    - Create templates/ai-engine-deployment.yaml
    - _Requirements: 11.1_
  
  - [ ]* 21.3 Write property tests for Helm chart
    - **Property 41: Helm Chart Component Completeness**
    - **Property 42: Helm Chart Configurability**
    - **Validates: Requirements 11.1, 11.2**

- [ ] 22. Create ConfigMaps and Secrets
  - [ ] 22.1 Create ConfigMap template
    - Define templates/configmap.yaml
    - Include non-sensitive application configuration
    - Make values overridable from values.yaml
    - _Requirements: 11.3_
  
  - [ ] 22.2 Create Secret template
    - Define templates/secret.yaml
    - Include database passwords, API keys, JWT secrets
    - Support external secret references
    - _Requirements: 11.3, 15.1, 15.2, 15.4_
  
  - [ ]* 22.3 Write property tests for configuration
    - **Property 43: ConfigMap and Secret Separation**
    - **Property 60: Secret Storage Security**
    - **Validates: Requirements 11.3, 15.1, 15.2**

- [ ] 23. Create Service and Ingress resources
  - [ ] 23.1 Create Service templates
    - Create templates/php-fpm-service.yaml
    - Create templates/nginx-service.yaml
    - Create templates/mysql-service.yaml
    - Create templates/redis-service.yaml
    - Create templates/ai-engine-service.yaml
    - Ensure selectors match deployment labels
    - _Requirements: 11.4_
  
  - [ ] 23.2 Create Ingress template
    - Define templates/ingress.yaml
    - Configure TLS termination with certificate reference
    - Support wildcard subdomain routing (*.nexsaas.com)
    - _Requirements: 11.5_
  
  - [ ]* 23.3 Write property tests for networking
    - **Property 44: Service Resource Definitions**
    - **Property 45: Ingress TLS Configuration**
    - **Validates: Requirements 11.4, 11.5**

- [ ] 24. Implement database migration init containers
  - [ ] 24.1 Create init container spec
    - Add initContainer to PHP-FPM deployment
    - Run database migrations before main container starts
    - Use same image as main container
    - _Requirements: 11.6_
  
  - [ ]* 24.2 Write property tests for init containers
    - **Property 46: Init Container for Migrations**
    - **Validates: Requirements 11.6**

- [ ] 25. Implement Horizontal Pod Autoscaler
  - [ ] 25.1 Create HPA template
    - Define templates/hpa.yaml
    - Set minReplicas=2, maxReplicas=10
    - Set targetCPUUtilization=70%
    - Support custom metrics (request queue length > 100)
    - _Requirements: 12.1, 12.2, 12.3, 12.4_
  
  - [ ] 25.2 Configure resource requests and limits
    - Define CPU and memory requests for all pods
    - Define CPU and memory limits for all pods
    - Enable HPA calculations
    - _Requirements: 12.5_
  
  - [ ]* 25.3 Write property tests for autoscaling
    - **Property 48: HPA Replica Bounds**
    - **Property 49: HPA CPU Threshold**
    - **Property 50: HPA Scale-Up Responsiveness**
    - **Property 51: Resource Requests and Limits**
    - **Validates: Requirements 12.1, 12.2, 12.5, 12.6**

- [ ] 26. Implement persistent storage
  - [ ] 26.1 Create PersistentVolumeClaim template
    - Define templates/pvc.yaml
    - Request 100Gi storage with ReadWriteMany access mode
    - Support dynamic provisioning via StorageClass
    - Configure backup annotations (daily, 30-day retention)
    - _Requirements: 13.1, 13.3, 13.4_
  
  - [ ] 26.2 Mount PVC in PHP-FPM deployment
    - Add volume mount at /var/www/html/storage
    - Ensure persistence across pod restarts
    - _Requirements: 13.2_
  
  - [ ] 26.3 Configure storage monitoring
    - Emit alerts when PVC reaches 90% capacity
    - _Requirements: 13.5_
  
  - [ ]* 26.4 Write property tests for persistent storage
    - **Property 52: PVC Storage Capacity**
    - **Property 53: PVC Mount Path**
    - **Property 54: Dynamic Storage Provisioning**
    - **Property 55: Storage Backup Configuration**
    - **Validates: Requirements 13.1, 13.2, 13.3, 13.4**


- [ ] 27. Implement health checks and readiness probes
  - [ ] 27.1 Create health check endpoints
    - Implement /health endpoint returning 200 if app can process requests
    - Implement /ready endpoint returning 200 if app initialized and DB connected
    - _Requirements: 14.5, 14.6_
  
  - [ ] 27.2 Configure liveness probes
    - Add livenessProbe to PHP-FPM deployment
    - Check /health endpoint every 30 seconds
    - Restart pod after 3 consecutive failures
    - _Requirements: 14.1, 14.2_
  
  - [ ] 27.3 Configure readiness probes
    - Add readinessProbe to PHP-FPM deployment
    - Check /ready endpoint every 10 seconds
    - Exclude pod from load balancing while failing
    - _Requirements: 14.3, 14.4_
  
  - [ ]* 27.4 Write property tests for health checks
    - **Property 56: Liveness Probe Configuration**
    - **Property 57: Readiness Probe Configuration**
    - **Property 58: Health Endpoint Functionality**
    - **Property 59: Readiness Endpoint Functionality**
    - **Validates: Requirements 14.1-14.6**

- [ ] 28. Implement RBAC and secret rotation
  - [ ] 28.1 Configure Kubernetes RBAC policies
    - Restrict secret access to necessary service accounts
    - Define role bindings for deployments
    - _Requirements: 15.5_
  
  - [ ] 28.2 Implement secret rotation mechanism
    - Trigger rolling updates on secret changes
    - Complete pod restarts within 5 minutes
    - Support Vault integration for dynamic secrets
    - _Requirements: 15.3, 15.6_
  
  - [ ]* 28.3 Write property tests for secrets management
    - **Property 61: Secret Rotation Trigger**
    - **Validates: Requirements 15.6**

- [ ] 29. Set up local Kubernetes testing environment
  - [ ] 29.1 Create minikube deployment documentation
    - Document single-command deployment to minikube
    - Configure reduced resource limits (512MB RAM per pod)
    - Use hostPath volumes for local storage
    - Expose services via NodePort
    - _Requirements: 20.1, 20.3, 20.4, 20.5_
  
  - [ ] 29.2 Create kind deployment documentation
    - Document deployment to kind as alternative
    - Configure for local development
    - _Requirements: 20.2_
  
  - [ ] 29.3 Create local test data seeding scripts
    - Provide scripts to seed test data in local deployments
    - _Requirements: 20.6_
  
  - [ ]* 29.4 Write property tests for local deployment
    - **Property 47: Local Deployment Compatibility**
    - **Validates: Requirements 11.7, 20.1, 20.2**

- [ ] 30. Integration and wiring
  - [ ] 30.1 Wire onboarding flow end-to-end
    - Connect signup → verification → wizard → provisioning → dashboard
    - Ensure all services communicate correctly
    - Test complete onboarding flow
    - _Requirements: All onboarding requirements_
  
  - [ ] 30.2 Wire multi-tenancy isolation layers
    - Integrate middleware → query interceptor → storage isolator → cache isolator
    - Verify isolation across all layers
    - Test cross-tenant access prevention
    - _Requirements: All multi-tenancy requirements_
  
  - [ ] 30.3 Deploy to Kubernetes and verify
    - Deploy Helm chart to test cluster
    - Verify all pods running and healthy
    - Test autoscaling behavior
    - Verify persistent storage
    - _Requirements: All Kubernetes requirements_
  
  - [ ]* 30.4 Write integration tests
    - Test complete onboarding flow
    - Test multi-tenancy isolation
    - Test Kubernetes deployment
    - **Validates: All requirements**

- [ ] 31. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional property-based tests and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Implementation uses PHP integrating with existing modular_core architecture
- Kubernetes deployment supports both cloud and local development environments
- All 66 correctness properties from the design are covered by property test tasks

## Timeline Estimate

- Component 1 (Onboarding Foundation): 3 days
- Component 2 (Subdomain & Schema Provisioning): 3 days
- Component 3 (Multi-Tenancy Core): 4 days
- Component 4 (Onboarding Enhancements): 3 days
- Component 5 (Kubernetes Deployment): 3 days

Total: 16 days (matches requirement timeline)

