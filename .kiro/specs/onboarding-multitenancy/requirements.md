# Requirements Document

## Introduction

This document specifies the requirements for Phase 2: Onboarding & Multi-tenancy for NexSaaS. This phase enables self-service customer acquisition through a streamlined onboarding flow and ensures proper tenant isolation for secure multi-tenant operations. The system will provision isolated environments for each tenant, including subdomain allocation, database schema creation, and data segregation mechanisms.

## Glossary

- **Onboarding_System**: The subsystem responsible for guiding new customers through account setup and initial configuration
- **Tenant**: A customer organization using the NexSaaS platform with isolated data and resources
- **Tenant_Isolation_Layer**: The middleware and database mechanisms that ensure data separation between tenants
- **Subdomain_Provisioner**: The component that creates and configures unique subdomains for each tenant
- **Trial_Manager**: The component that manages trial periods, limitations, and conversions
- **Onboarding_Wizard**: The multi-step user interface that collects tenant configuration during signup
- **Data_Seeder**: The component that populates new tenant environments with sample data
- **Tenant_Context**: The runtime context containing the current tenant identifier for request processing
- **Storage_Isolator**: The component that manages separate file storage per tenant
- **Query_Interceptor**: The middleware that automatically injects tenant_id into database queries
- **Kubernetes_Orchestrator**: The container orchestration system managing application deployment
- **HPA**: HorizontalPodAutoscaler - Kubernetes resource for automatic scaling based on metrics
- **Helm_Chart**: A package manager template for Kubernetes deployments
- **Email_Sequencer**: The component that sends timed welcome emails to new tenants

## Requirements

### Requirement 1: Self-Service Signup and Email Verification

**User Story:** As a prospective customer, I want to sign up for NexSaaS without sales interaction, so that I can start using the platform immediately.

#### Acceptance Criteria

1. THE Onboarding_System SHALL display a landing page with a "Start Free Trial" call-to-action button
2. WHEN a user clicks "Start Free Trial", THE Onboarding_System SHALL display a signup form requesting email, password, and company name
3. WHEN a user submits the signup form with valid data, THE Onboarding_System SHALL create an unverified account and send a verification email within 30 seconds
4. THE verification email SHALL contain a unique verification link valid for 24 hours
5. WHEN a user clicks the verification link, THE Onboarding_System SHALL mark the account as verified and redirect to the onboarding wizard
6. IF the verification link is expired, THEN THE Onboarding_System SHALL display an error message and offer to resend the verification email
7. THE Onboarding_System SHALL prevent login attempts for unverified accounts and display a message prompting email verification

### Requirement 2: Multi-Step Onboarding Wizard

**User Story:** As a new customer, I want a guided setup process, so that I can configure my account properly without confusion.

#### Acceptance Criteria

1. THE Onboarding_Wizard SHALL display 5 sequential steps: Company Profile, Invite Team Members, Import Leads, Connect WhatsApp, and Choose Plan
2. WHEN a user completes Step 1, THE Onboarding_Wizard SHALL collect company logo, industry selection, and company size
3. WHEN a user completes Step 2, THE Onboarding_Wizard SHALL accept email addresses for team member invitations and send invitation emails
4. WHERE a user chooses to skip team invitations, THE Onboarding_Wizard SHALL proceed to Step 3 without requiring input
5. WHEN a user completes Step 3, THE Onboarding_Wizard SHALL accept CSV file uploads containing lead data with validation for required fields (name, email)
6. WHERE a user chooses to skip lead import, THE Onboarding_Wizard SHALL proceed to Step 4 without requiring file upload
7. WHEN a user completes Step 4, THE Onboarding_Wizard SHALL provide instructions for WhatsApp Business API connection
8. WHERE a user chooses to skip WhatsApp connection, THE Onboarding_Wizard SHALL proceed to Step 5 without requiring configuration
9. WHEN a user completes Step 5, THE Onboarding_Wizard SHALL display available plans and initiate a 14-day trial period
10. THE Onboarding_Wizard SHALL display progress indicators showing current step and completion percentage
11. THE Onboarding_Wizard SHALL allow users to navigate backward to previous steps to modify entries
12. WHEN the wizard is completed, THE Onboarding_Wizard SHALL redirect the user to their tenant dashboard

### Requirement 3: Automatic Subdomain Provisioning

**User Story:** As a new customer, I want a unique branded URL for my organization, so that my team can access our dedicated instance.

#### Acceptance Criteria

1. WHEN a tenant account is created, THE Subdomain_Provisioner SHALL generate a subdomain based on the company name in the format {companyname}.nexsaas.com
2. THE Subdomain_Provisioner SHALL sanitize company names by removing special characters and converting to lowercase
3. IF a subdomain already exists, THEN THE Subdomain_Provisioner SHALL append a numeric suffix and retry until a unique subdomain is found
4. THE Subdomain_Provisioner SHALL configure DNS records for the new subdomain within 60 seconds
5. THE Subdomain_Provisioner SHALL configure SSL certificates for the subdomain using Let's Encrypt or equivalent
6. WHEN subdomain provisioning completes, THE Subdomain_Provisioner SHALL store the subdomain mapping in the tenant configuration table
7. THE Subdomain_Provisioner SHALL validate that the subdomain resolves correctly before marking provisioning as complete

### Requirement 4: Isolated Database Schema Creation

**User Story:** As a platform administrator, I want each tenant to have an isolated database schema, so that tenant data remains completely separated.

#### Acceptance Criteria

1. WHEN a new tenant is created, THE Tenant_Isolation_Layer SHALL create a dedicated database schema named tenant_{tenant_id}
2. THE Tenant_Isolation_Layer SHALL execute all required migration scripts to create tables within the new schema within 120 seconds
3. THE Tenant_Isolation_Layer SHALL create database users with permissions restricted to only their tenant schema
4. THE Tenant_Isolation_Layer SHALL store the schema name and connection details in the tenant configuration table
5. IF schema creation fails, THEN THE Tenant_Isolation_Layer SHALL rollback the tenant creation process and log the error
6. THE Tenant_Isolation_Layer SHALL verify schema isolation by executing a test query that confirms no cross-tenant data access

### Requirement 5: Sample Data Seeding

**User Story:** As a new customer, I want my account pre-populated with example data, so that I can explore features without manual data entry.

#### Acceptance Criteria

1. WHEN a new tenant schema is created, THE Data_Seeder SHALL populate the schema with sample data including 10 leads, 5 contacts, 3 deals, and 2 users
2. THE Data_Seeder SHALL create sample data that demonstrates key features such as pipeline stages, activities, and notes
3. THE Data_Seeder SHALL mark all sample data with a flag indicating it is demo data that can be bulk-deleted
4. THE Data_Seeder SHALL complete seeding within 30 seconds of schema creation
5. IF seeding fails, THEN THE Data_Seeder SHALL log the error but allow tenant creation to proceed
6. THE Onboarding_System SHALL display an onboarding checklist in the dashboard showing 5 tasks: "Explore Sample Leads", "Create Your First Deal", "Invite Team Members", "Import Real Data", "Connect WhatsApp"

### Requirement 6: Welcome Email Sequence

**User Story:** As a new customer, I want to receive helpful emails during my first week, so that I can learn how to use the platform effectively.

#### Acceptance Criteria

1. WHEN a tenant completes onboarding, THE Email_Sequencer SHALL schedule 3 welcome emails to be sent on Day 0, Day 3, and Day 7
2. THE Day 0 email SHALL contain login credentials, subdomain URL, and quick start guide links
3. THE Day 3 email SHALL contain tips for importing data and inviting team members
4. THE Day 7 email SHALL contain information about upgrading from trial to paid plan
5. IF a tenant upgrades to a paid plan, THEN THE Email_Sequencer SHALL cancel remaining scheduled welcome emails
6. THE Email_Sequencer SHALL track email opens and clicks for onboarding analytics

### Requirement 7: Tenant Context Middleware

**User Story:** As a platform administrator, I want all application requests to be automatically scoped to the correct tenant, so that developers cannot accidentally access wrong tenant data.

#### Acceptance Criteria

1. WHEN a request is received, THE Tenant_Isolation_Layer SHALL extract the tenant identifier from the subdomain
2. THE Tenant_Isolation_Layer SHALL validate that the tenant identifier exists and is active
3. IF the tenant identifier is invalid or inactive, THEN THE Tenant_Isolation_Layer SHALL return a 404 error
4. THE Tenant_Isolation_Layer SHALL store the tenant identifier in the Tenant_Context for the duration of the request
5. THE Query_Interceptor SHALL automatically inject WHERE tenant_id = {current_tenant_id} into all SELECT, UPDATE, and DELETE queries
6. THE Query_Interceptor SHALL automatically set tenant_id = {current_tenant_id} for all INSERT queries
7. THE Tenant_Isolation_Layer SHALL prevent manual override of tenant_id in application code through query parameter validation

### Requirement 8: File Storage Isolation

**User Story:** As a platform administrator, I want each tenant's uploaded files stored separately, so that file access is properly isolated.

#### Acceptance Criteria

1. WHEN a file is uploaded, THE Storage_Isolator SHALL store the file in a directory path structured as /storage/tenant_{tenant_id}/{file_type}/{filename}
2. THE Storage_Isolator SHALL generate unique filenames using UUID to prevent collisions
3. WHEN a file is requested, THE Storage_Isolator SHALL verify the requesting tenant matches the file's tenant_id before serving
4. IF a tenant requests a file belonging to another tenant, THEN THE Storage_Isolator SHALL return a 403 Forbidden error
5. THE Storage_Isolator SHALL apply the same isolation rules to temporary files and cache directories
6. WHERE cloud storage is used, THE Storage_Isolator SHALL use bucket prefixes or separate buckets per tenant

### Requirement 9: Redis Cache Key Isolation

**User Story:** As a platform administrator, I want Redis cache keys prefixed by tenant, so that cached data cannot leak between tenants.

#### Acceptance Criteria

1. WHEN data is cached, THE Tenant_Isolation_Layer SHALL prefix all Redis keys with tenant:{tenant_id}:
2. WHEN cached data is retrieved, THE Tenant_Isolation_Layer SHALL only access keys matching the current tenant prefix
3. THE Tenant_Isolation_Layer SHALL configure Redis key expiration policies per tenant to prevent unbounded growth
4. WHEN a tenant is deleted, THE Tenant_Isolation_Layer SHALL purge all Redis keys matching the tenant prefix within 60 seconds

### Requirement 10: Automated Tenant Isolation Testing

**User Story:** As a platform administrator, I want automated tests that verify tenant isolation, so that I can detect data leakage bugs before production.

#### Acceptance Criteria

1. THE Tenant_Isolation_Layer SHALL provide an automated test suite that creates 2 test tenants with sample data
2. THE test suite SHALL verify that queries from Tenant A return zero results for Tenant B's data
3. THE test suite SHALL verify that file access requests from Tenant A are denied for Tenant B's files
4. THE test suite SHALL verify that Redis cache keys from Tenant A are not accessible to Tenant B
5. THE test suite SHALL verify that API requests with Tenant A's authentication cannot access Tenant B's resources
6. THE test suite SHALL execute in under 60 seconds and report pass/fail status for each isolation check
7. THE test suite SHALL be integrated into the CI/CD pipeline and block deployments if isolation tests fail

### Requirement 11: Kubernetes Helm Chart Deployment

**User Story:** As a DevOps engineer, I want a Helm chart for deploying NexSaaS, so that I can deploy to any Kubernetes cluster consistently.

#### Acceptance Criteria

1. THE Kubernetes_Orchestrator SHALL provide a Helm chart that deploys all application components including PHP-FPM, Nginx, MySQL, Redis, and AI Engine
2. THE Helm chart SHALL support configurable values for replica counts, resource limits, and environment variables
3. THE Helm chart SHALL include ConfigMaps for application configuration and Secrets for sensitive credentials
4. THE Helm chart SHALL define Service resources for internal communication between components
5. THE Helm chart SHALL define an Ingress resource for external HTTPS access with TLS termination
6. THE Helm chart SHALL include init containers for database migrations before application startup
7. THE Helm chart SHALL be installable on minikube or kind for local development testing

### Requirement 12: Horizontal Pod Autoscaling

**User Story:** As a DevOps engineer, I want automatic scaling of application pods, so that the system handles traffic spikes without manual intervention.

#### Acceptance Criteria

1. THE Kubernetes_Orchestrator SHALL define an HPA resource for PHP-FPM pods with minimum 2 replicas and maximum 10 replicas
2. THE HPA SHALL scale up when average CPU utilization exceeds 70 percent across all pods
3. THE HPA SHALL scale down when average CPU utilization falls below 30 percent for 5 minutes
4. WHERE custom metrics are available, THE HPA SHALL scale based on request queue length exceeding 100 requests
5. THE Kubernetes_Orchestrator SHALL configure resource requests and limits for CPU and memory on all pods to enable HPA calculations
6. THE HPA SHALL complete scale-up operations within 60 seconds of threshold breach

### Requirement 13: Persistent Volume Claims for Tenant Storage

**User Story:** As a DevOps engineer, I want persistent storage for tenant files, so that uploaded files survive pod restarts.

#### Acceptance Criteria

1. THE Kubernetes_Orchestrator SHALL define a PersistentVolumeClaim requesting 100GB of storage with ReadWriteMany access mode
2. THE PHP-FPM deployment SHALL mount the PersistentVolume at /var/www/html/storage
3. WHERE cloud providers are used, THE Kubernetes_Orchestrator SHALL support dynamic provisioning using StorageClass
4. THE Kubernetes_Orchestrator SHALL configure backup policies for the PersistentVolume with daily snapshots retained for 30 days
5. IF the PersistentVolume becomes full, THEN THE Kubernetes_Orchestrator SHALL emit alerts to the monitoring system

### Requirement 14: Health Checks and Readiness Probes

**User Story:** As a DevOps engineer, I want Kubernetes health checks, so that unhealthy pods are automatically restarted.

#### Acceptance Criteria

1. THE PHP-FPM deployment SHALL define a liveness probe that checks /health endpoint every 30 seconds
2. IF the liveness probe fails 3 consecutive times, THEN THE Kubernetes_Orchestrator SHALL restart the pod
3. THE PHP-FPM deployment SHALL define a readiness probe that checks /ready endpoint every 10 seconds
4. WHILE the readiness probe fails, THE Kubernetes_Orchestrator SHALL exclude the pod from service load balancing
5. THE /health endpoint SHALL return HTTP 200 if the application can process requests
6. THE /ready endpoint SHALL return HTTP 200 if the application has completed initialization and database connections are established

### Requirement 15: Secrets Management

**User Story:** As a DevOps engineer, I want secure credential management, so that sensitive data is not exposed in configuration files.

#### Acceptance Criteria

1. THE Kubernetes_Orchestrator SHALL store database passwords, API keys, and JWT secrets in Kubernetes Secret resources
2. THE application pods SHALL mount secrets as environment variables or files, not as plain text in ConfigMaps
3. WHERE HashiCorp Vault is available, THE Kubernetes_Orchestrator SHALL integrate with Vault for dynamic secret injection
4. THE Helm chart SHALL support external secret references to avoid storing secrets in version control
5. THE Kubernetes_Orchestrator SHALL configure RBAC policies that restrict secret access to only necessary service accounts
6. WHEN secrets are rotated, THE Kubernetes_Orchestrator SHALL trigger rolling updates of affected pods within 5 minutes

### Requirement 16: Trial Period Management

**User Story:** As a new customer, I want a 14-day free trial, so that I can evaluate the platform before purchasing.

#### Acceptance Criteria

1. WHEN a tenant completes onboarding, THE Trial_Manager SHALL set the trial end date to 14 days from the current date
2. WHILE a tenant is in trial status, THE Trial_Manager SHALL display a banner showing days remaining in trial
3. WHEN the trial period expires, THE Trial_Manager SHALL restrict access to the platform and display an upgrade prompt
4. THE Trial_Manager SHALL send email reminders on Day 10, Day 13, and Day 14 of the trial period
5. WHEN a tenant upgrades to a paid plan, THE Trial_Manager SHALL immediately remove trial restrictions and update the tenant status
6. THE Trial_Manager SHALL allow trial extensions of up to 7 additional days when approved by an administrator

### Requirement 17: CSV Lead Import with Validation

**User Story:** As a new customer, I want to import my existing leads from a CSV file, so that I can migrate data quickly.

#### Acceptance Criteria

1. WHEN a CSV file is uploaded in Step 3 of the onboarding wizard, THE Onboarding_System SHALL validate that required columns (name, email) are present
2. THE Onboarding_System SHALL parse CSV files up to 10MB in size containing up to 10,000 rows
3. THE Onboarding_System SHALL validate email addresses using RFC 5322 format validation
4. IF validation errors are found, THEN THE Onboarding_System SHALL display error messages indicating row numbers and specific issues
5. WHEN validation passes, THE Onboarding_System SHALL import leads into the tenant database within 30 seconds
6. THE Onboarding_System SHALL display an import summary showing total rows processed, successful imports, and failed rows
7. WHERE optional columns are present (phone, company, status), THE Onboarding_System SHALL import those fields into corresponding lead attributes

### Requirement 18: Team Member Invitation System

**User Story:** As a new customer, I want to invite my team during onboarding, so that we can collaborate immediately.

#### Acceptance Criteria

1. WHEN a user enters email addresses in Step 2, THE Onboarding_System SHALL validate email format and prevent duplicate invitations
2. THE Onboarding_System SHALL send invitation emails containing a unique invitation link valid for 7 days
3. WHEN an invited user clicks the invitation link, THE Onboarding_System SHALL display a signup form pre-filled with their email
4. WHEN an invited user completes signup, THE Onboarding_System SHALL add them to the tenant with a default "User" role
5. THE Onboarding_System SHALL allow the account owner to resend invitations for pending invites
6. IF an invitation expires, THEN THE Onboarding_System SHALL mark it as expired and allow the owner to send a new invitation

### Requirement 19: Onboarding Checklist Tracking

**User Story:** As a new customer, I want to see my onboarding progress, so that I know what steps remain to fully configure my account.

#### Acceptance Criteria

1. WHEN a tenant first logs in, THE Onboarding_System SHALL display a checklist widget in the dashboard with 5 tasks
2. THE Onboarding_System SHALL mark tasks as complete when corresponding actions are performed (e.g., "Create Your First Deal" completes when a deal is created)
3. THE Onboarding_System SHALL calculate and display completion percentage based on completed tasks
4. WHEN all checklist tasks are completed, THE Onboarding_System SHALL display a congratulations message and hide the checklist widget
5. THE Onboarding_System SHALL allow users to manually dismiss the checklist widget if they choose not to complete it
6. THE Onboarding_System SHALL track checklist completion metrics for analytics purposes

### Requirement 20: Local Kubernetes Testing Environment

**User Story:** As a developer, I want to test Kubernetes deployments locally, so that I can verify changes before pushing to production.

#### Acceptance Criteria

1. THE Kubernetes_Orchestrator SHALL provide documentation for deploying to minikube with a single command
2. THE Kubernetes_Orchestrator SHALL provide documentation for deploying to kind (Kubernetes in Docker) as an alternative
3. THE local deployment SHALL use reduced resource limits suitable for development machines (512MB RAM per pod)
4. THE local deployment SHALL use hostPath volumes instead of cloud provider storage classes
5. THE local deployment SHALL expose services using NodePort or port-forwarding for local access
6. THE Kubernetes_Orchestrator SHALL provide scripts to seed local deployments with test data for development
