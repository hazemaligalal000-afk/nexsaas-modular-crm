# Design Document: Onboarding & Multi-Tenancy

## Overview

This design document specifies the technical implementation for Phase 2 of NexSaaS, covering self-service customer onboarding and multi-tenant architecture. The system enables customers to sign up, configure their account through a guided wizard, and receive an isolated environment with automatic subdomain provisioning, database schema creation, and sample data seeding. The multi-tenancy layer ensures complete data isolation through schema-per-tenant architecture, query interception, file storage segregation, and cache key prefixing. The deployment architecture uses Kubernetes with Helm charts, horizontal pod autoscaling, and persistent volumes.

## Architecture Components

### 1. Onboarding System
- **SignupController**: Handles user registration and email verification
- **OnboardingWizardController**: Manages 5-step wizard flow
- **EmailVerificationService**: Generates and validates verification tokens
- **WelcomeEmailSequencer**: Schedules and sends timed welcome emails
- **OnboardingChecklistService**: Tracks completion of onboarding tasks

### 2. Multi-Tenancy Core
- **TenantMiddleware**: Extracts tenant context from subdomain
- **QueryInterceptor**: Auto-injects tenant_id into database queries
- **TenantSchemaManager**: Creates and manages isolated database schemas
- **StorageIsolator**: Enforces file storage separation
- **CacheIsolator**: Prefixes Redis keys by tenant

### 3. Provisioning Services
- **SubdomainProvisioner**: Creates unique subdomains with DNS and SSL
- **SchemaCreator**: Executes migrations in tenant-specific schemas
- **DataSeeder**: Populates new tenants with sample data
- **TrialManager**: Manages trial periods and restrictions

### 4. Import & Invitation
- **CSVImportService**: Validates and imports lead data
- **TeamInvitationService**: Sends and manages team member invites

### 5. Kubernetes Infrastructure
- **Helm Charts**: Deployment templates for all services
- **HPA Configuration**: Auto-scaling rules
- **PVC Management**: Persistent storage provisioning
- **Health Check Endpoints**: Liveness and readiness probes

## Data Model

### Tenants Table
```sql
CREATE TABLE tenants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    subdomain VARCHAR(100) UNIQUE NOT NULL,
    schema_name VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('trial', 'active', 'suspended', 'cancelled') DEFAULT 'trial',
    trial_end_date DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```



### Email Verifications Table
```sql
CREATE TABLE email_verifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### Team Invitations Table
```sql
CREATE TABLE team_invitations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

### Onboarding Checklists Table
```sql
CREATE TABLE onboarding_checklists (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    task_key VARCHAR(100) NOT NULL,
    completed_at DATETIME,
    dismissed BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (tenant_id, task_key),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

### Welcome Email Schedule Table
```sql
CREATE TABLE welcome_email_schedule (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    email_type ENUM('day0', 'day3', 'day7') NOT NULL,
    scheduled_at DATETIME NOT NULL,
    sent_at DATETIME,
    opened_at DATETIME,
    clicked_at DATETIME,
    cancelled BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

## Implementation Approach

### Pseudocode Style
This design uses **Structured Pseudocode** to describe algorithms and logic flows in a language-agnostic manner.



## Core Algorithms

### Algorithm 1: Signup and Email Verification

```
FUNCTION handleSignup(email, password, companyName):
    // Validate input
    IF NOT isValidEmail(email) THEN
        RETURN error("Invalid email format")
    END IF
    
    IF passwordStrength(password) < MINIMUM_STRENGTH THEN
        RETURN error("Password too weak")
    END IF
    
    // Check for existing account
    IF emailExists(email) THEN
        RETURN error("Email already registered")
    END IF
    
    // Create unverified account
    hashedPassword = hashPassword(password)
    userId = createUser(email, hashedPassword, companyName, verified=FALSE)
    
    // Generate verification token
    token = generateSecureToken(64)
    expiresAt = currentTime() + 24_HOURS
    saveVerificationToken(email, token, expiresAt)
    
    // Send verification email
    verificationLink = buildURL("/verify-email", token)
    sendEmail(email, "Verify Your Email", verificationLink)
    
    RETURN success("Verification email sent")
END FUNCTION

FUNCTION handleEmailVerification(token):
    verification = findVerificationByToken(token)
    
    IF verification IS NULL THEN
        RETURN error("Invalid verification link")
    END IF
    
    IF verification.expiresAt < currentTime() THEN
        RETURN error("Verification link expired")
    END IF
    
    IF verification.verifiedAt IS NOT NULL THEN
        RETURN error("Email already verified")
    END IF
    
    // Mark as verified
    updateVerification(verification.id, verifiedAt=currentTime())
    updateUser(verification.email, verified=TRUE)
    
    RETURN success("Email verified successfully")
END FUNCTION
```

### Algorithm 2: Subdomain Provisioning

```
FUNCTION provisionSubdomain(companyName, tenantId):
    // Sanitize company name
    subdomain = sanitizeSubdomain(companyName)
    
    // Ensure uniqueness
    attempt = 0
    WHILE subdomainExists(subdomain) DO
        attempt = attempt + 1
        subdomain = sanitizeSubdomain(companyName) + attempt
    END WHILE
    
    // Configure DNS
    dnsSuccess = createDNSRecord(subdomain + ".nexsaas.com", TARGET_IP)
    IF NOT dnsSuccess THEN
        RETURN error("DNS configuration failed")
    END IF
    
    // Provision SSL certificate
    sslSuccess = provisionSSLCertificate(subdomain + ".nexsaas.com")
    IF NOT sslSuccess THEN
        rollbackDNS(subdomain)
        RETURN error("SSL provisioning failed")
    END IF
    
    // Verify resolution
    maxRetries = 10
    FOR i = 1 TO maxRetries DO
        IF dnsResolves(subdomain + ".nexsaas.com") THEN
            updateTenant(tenantId, subdomain=subdomain)
            RETURN success(subdomain)
        END IF
        sleep(6_SECONDS)
    END FOR
    
    RETURN error("DNS resolution timeout")
END FUNCTION
```



### Algorithm 3: Tenant Schema Creation

```
FUNCTION createTenantSchema(tenantId):
    schemaName = "tenant_" + tenantId
    
    BEGIN TRANSACTION
    
    TRY
        // Create schema
        executeSQL("CREATE SCHEMA " + schemaName)
        
        // Run migrations
        migrationFiles = getMigrationFiles()
        FOR EACH file IN migrationFiles DO
            sql = readFile(file)
            executeSQL("USE " + schemaName + "; " + sql)
        END FOR
        
        // Create restricted user
        username = "tenant_" + tenantId + "_user"
        password = generateSecurePassword()
        executeSQL("CREATE USER '" + username + "'@'%' IDENTIFIED BY '" + password + "'")
        executeSQL("GRANT ALL ON " + schemaName + ".* TO '" + username + "'@'%'")
        
        // Verify isolation
        testQuery = "SELECT COUNT(*) FROM " + schemaName + ".users"
        result = executeSQL(testQuery)
        IF result IS NULL THEN
            THROW error("Schema verification failed")
        END IF
        
        // Store configuration
        updateTenant(tenantId, schemaName=schemaName, dbUser=username, dbPassword=password)
        
        COMMIT TRANSACTION
        RETURN success(schemaName)
        
    CATCH error
        ROLLBACK TRANSACTION
        RETURN error("Schema creation failed: " + error.message)
    END TRY
END FUNCTION
```

### Algorithm 4: Query Interception for Tenant Isolation

```
FUNCTION interceptQuery(query, tenantContext):
    tenantId = tenantContext.getTenantId()
    
    IF tenantId IS NULL THEN
        THROW error("No tenant context available")
    END IF
    
    queryType = detectQueryType(query)
    
    CASE queryType OF
        "SELECT", "UPDATE", "DELETE":
            // Inject WHERE clause
            IF NOT containsWhereClause(query) THEN
                query = query + " WHERE tenant_id = " + tenantId
            ELSE
                query = injectIntoWhereClause(query, "tenant_id = " + tenantId)
            END IF
            
        "INSERT":
            // Auto-set tenant_id
            IF NOT containsColumn(query, "tenant_id") THEN
                query = addColumnToInsert(query, "tenant_id", tenantId)
            ELSE
                // Verify tenant_id matches context
                providedTenantId = extractTenantIdFromInsert(query)
                IF providedTenantId != tenantId THEN
                    THROW error("Tenant ID mismatch")
                END IF
            END IF
            
        "CREATE", "DROP", "ALTER":
            // Schema operations not allowed at runtime
            THROW error("Schema modification not permitted")
    END CASE
    
    RETURN query
END FUNCTION
```



### Algorithm 5: File Storage Isolation

```
FUNCTION storeFile(file, fileType, tenantContext):
    tenantId = tenantContext.getTenantId()
    
    IF tenantId IS NULL THEN
        THROW error("No tenant context available")
    END IF
    
    // Generate unique filename
    uuid = generateUUID()
    extension = getFileExtension(file.name)
    filename = uuid + "." + extension
    
    // Build isolated path
    path = "/storage/tenant_" + tenantId + "/" + fileType + "/" + filename
    
    // Store file
    success = writeFile(path, file.content)
    IF NOT success THEN
        RETURN error("File storage failed")
    END IF
    
    // Return reference
    RETURN {
        path: path,
        filename: filename,
        tenantId: tenantId
    }
END FUNCTION

FUNCTION retrieveFile(path, tenantContext):
    tenantId = tenantContext.getTenantId()
    
    // Extract tenant from path
    pathTenantId = extractTenantIdFromPath(path)
    
    IF pathTenantId != tenantId THEN
        THROW error("Access denied: file belongs to different tenant")
    END IF
    
    // Serve file
    RETURN readFile(path)
END FUNCTION
```

### Algorithm 6: CSV Lead Import

```
FUNCTION importLeadsFromCSV(file, tenantId):
    // Validate file size
    IF file.size > 10_MB THEN
        RETURN error("File too large (max 10MB)")
    END IF
    
    // Parse CSV
    rows = parseCSV(file.content)
    
    IF rows.length > 10000 THEN
        RETURN error("Too many rows (max 10,000)")
    END IF
    
    // Validate headers
    requiredColumns = ["name", "email"]
    headers = rows[0]
    FOR EACH column IN requiredColumns DO
        IF column NOT IN headers THEN
            RETURN error("Missing required column: " + column)
        END IF
    END FOR
    
    // Process rows
    successCount = 0
    errorCount = 0
    errors = []
    
    FOR i = 1 TO rows.length - 1 DO
        row = rows[i]
        
        // Validate email
        IF NOT isValidEmail(row.email) THEN
            errors.append({row: i, error: "Invalid email"})
            errorCount = errorCount + 1
            CONTINUE
        END IF
        
        // Import lead
        TRY
            createLead(tenantId, row.name, row.email, row.phone, row.company, row.status)
            successCount = successCount + 1
        CATCH error
            errors.append({row: i, error: error.message})
            errorCount = errorCount + 1
        END TRY
    END FOR
    
    RETURN {
        success: successCount,
        failed: errorCount,
        errors: errors
    }
END FUNCTION
```



### Algorithm 7: Data Seeding

```
FUNCTION seedTenantData(tenantId, schemaName):
    BEGIN TRANSACTION
    
    TRY
        // Switch to tenant schema
        executeSQL("USE " + schemaName)
        
        // Create sample users
        user1 = createUser(tenantId, "demo@example.com", "Demo User", role="admin", isDemoData=TRUE)
        user2 = createUser(tenantId, "sales@example.com", "Sales Rep", role="user", isDemoData=TRUE)
        
        // Create sample leads
        FOR i = 1 TO 10 DO
            createLead(
                tenantId,
                name="Sample Lead " + i,
                email="lead" + i + "@example.com",
                phone="+1555000" + padLeft(i, 4, "0"),
                company="Company " + i,
                status="new",
                isDemoData=TRUE
            )
        END FOR
        
        // Create sample contacts
        FOR i = 1 TO 5 DO
            createContact(
                tenantId,
                name="Sample Contact " + i,
                email="contact" + i + "@example.com",
                phone="+1555100" + padLeft(i, 4, "0"),
                company="Company " + i,
                isDemoData=TRUE
            )
        END FOR
        
        // Create sample deals
        stages = ["qualification", "proposal", "negotiation"]
        FOR i = 1 TO 3 DO
            createDeal(
                tenantId,
                title="Sample Deal " + i,
                value=10000 * i,
                stage=stages[i - 1],
                contactId=i,
                isDemoData=TRUE
            )
        END FOR
        
        COMMIT TRANSACTION
        RETURN success("Sample data seeded")
        
    CATCH error
        ROLLBACK TRANSACTION
        RETURN error("Seeding failed: " + error.message)
    END TRY
END FUNCTION
```

### Algorithm 8: Tenant Context Middleware

```
FUNCTION tenantMiddleware(request, response, next):
    // Extract subdomain
    host = request.getHeader("Host")
    subdomain = extractSubdomain(host)
    
    IF subdomain IS NULL OR subdomain == "www" OR subdomain == "app" THEN
        RETURN response.error(404, "Tenant not found")
    END IF
    
    // Lookup tenant
    tenant = findTenantBySubdomain(subdomain)
    
    IF tenant IS NULL THEN
        RETURN response.error(404, "Tenant not found")
    END IF
    
    IF tenant.status == "suspended" OR tenant.status == "cancelled" THEN
        RETURN response.error(403, "Account suspended")
    END IF
    
    // Check trial expiration
    IF tenant.status == "trial" AND tenant.trialEndDate < currentTime() THEN
        RETURN response.redirect("/upgrade")
    END IF
    
    // Set tenant context
    request.setTenantContext({
        tenantId: tenant.id,
        schemaName: tenant.schemaName,
        subdomain: tenant.subdomain,
        status: tenant.status
    })
    
    // Switch database connection to tenant schema
    switchDatabaseSchema(tenant.schemaName)
    
    // Continue to next middleware
    next()
END FUNCTION
```



## Correctness Properties

### Onboarding Properties (Properties 1-20)

**Property 1: Email Verification Token Uniqueness**
- **Type**: Universal
- **Validates**: Requirement 1.3, 1.4
- **Statement**: For all verification tokens T1 and T2, if T1 ≠ T2, then T1 and T2 map to different verification records
- **Test Strategy**: Generate 1000 tokens, verify all unique

**Property 2: Email Verification Expiration**
- **Type**: Universal
- **Validates**: Requirement 1.4, 1.6
- **Statement**: For all verification tokens T, if currentTime > T.expiresAt, then verification(T) returns error
- **Test Strategy**: Create token with past expiration, verify rejection

**Property 3: Subdomain Sanitization Idempotence**
- **Type**: Universal
- **Validates**: Requirement 3.2
- **Statement**: For all company names C, sanitizeSubdomain(sanitizeSubdomain(C)) = sanitizeSubdomain(C)
- **Test Strategy**: Apply sanitization twice, verify identical results

**Property 4: Subdomain Uniqueness**
- **Type**: Universal
- **Validates**: Requirement 3.3
- **Statement**: For all tenants T1 and T2, if T1 ≠ T2, then T1.subdomain ≠ T2.subdomain
- **Test Strategy**: Create 100 tenants, verify all subdomains unique

**Property 5: DNS Resolution Consistency**
- **Type**: Universal
- **Validates**: Requirement 3.7
- **Statement**: For all subdomains S, if provisioningComplete(S) = true, then dnsResolves(S) = true
- **Test Strategy**: Provision subdomain, verify DNS resolution

**Property 6: Schema Name Format**
- **Type**: Universal
- **Validates**: Requirement 4.1
- **Statement**: For all tenants T, T.schemaName matches pattern "tenant_[0-9]+"
- **Test Strategy**: Create tenants, verify schema name format

**Property 7: Schema Isolation**
- **Type**: Universal
- **Validates**: Requirement 4.6
- **Statement**: For all tenants T1 and T2 where T1 ≠ T2, query(T1.schema, "SELECT * FROM users") ∩ query(T2.schema, "SELECT * FROM users") = ∅
- **Test Strategy**: Create 2 tenants with data, verify no cross-access

**Property 8: Migration Completeness**
- **Type**: Universal
- **Validates**: Requirement 4.2
- **Statement**: For all tenant schemas S, tableCount(S) = expectedTableCount
- **Test Strategy**: Create schema, count tables, verify against expected

**Property 9: Sample Data Counts**
- **Type**: Universal
- **Validates**: Requirement 5.1
- **Statement**: For all new tenant schemas S, count(S.leads) = 10 AND count(S.contacts) = 5 AND count(S.deals) = 3
- **Test Strategy**: Seed data, verify counts

**Property 10: Sample Data Flag**
- **Type**: Universal
- **Validates**: Requirement 5.3
- **Statement**: For all records R created by seeder, R.isDemoData = true
- **Test Strategy**: Seed data, verify all records flagged



**Property 11: Welcome Email Scheduling**
- **Type**: Universal
- **Validates**: Requirement 6.1
- **Statement**: For all tenants T completing onboarding at time t, emails scheduled at t+0, t+3days, t+7days
- **Test Strategy**: Complete onboarding, verify 3 scheduled emails

**Property 12: Welcome Email Cancellation on Upgrade**
- **Type**: Universal
- **Validates**: Requirement 6.5
- **Statement**: For all tenants T, if T.status changes from 'trial' to 'active', then all unsent welcome emails are cancelled
- **Test Strategy**: Upgrade during trial, verify emails cancelled

**Property 13: Wizard Step Sequence**
- **Type**: Universal
- **Validates**: Requirement 2.1
- **Statement**: For all wizard sessions W, W.currentStep ∈ {1,2,3,4,5} AND W.canNavigateTo(n) = true IFF n ≤ W.maxCompletedStep + 1
- **Test Strategy**: Navigate wizard, verify step constraints

**Property 14: Wizard Progress Calculation**
- **Type**: Universal
- **Validates**: Requirement 2.10
- **Statement**: For all wizard sessions W, W.progress = (W.currentStep / 5) * 100
- **Test Strategy**: Complete steps, verify progress percentage

**Property 15: CSV Row Limit**
- **Type**: Universal
- **Validates**: Requirement 17.2
- **Statement**: For all CSV files F, if rowCount(F) > 10000, then import(F) returns error
- **Test Strategy**: Upload CSV with 10001 rows, verify rejection

**Property 16: CSV Email Validation**
- **Type**: Universal
- **Validates**: Requirement 17.3
- **Statement**: For all CSV rows R, if NOT isValidEmail(R.email), then R is rejected with error
- **Test Strategy**: Import CSV with invalid emails, verify rejection

**Property 17: Team Invitation Token Uniqueness**
- **Type**: Universal
- **Validates**: Requirement 18.2
- **Statement**: For all invitation tokens T1 and T2, if T1 ≠ T2, then T1 and T2 map to different invitations
- **Test Strategy**: Generate 1000 invitation tokens, verify uniqueness

**Property 18: Invitation Expiration**
- **Type**: Universal
- **Validates**: Requirement 18.2
- **Statement**: For all invitations I, if currentTime > I.expiresAt, then accept(I) returns error
- **Test Strategy**: Create invitation with past expiration, verify rejection

**Property 19: Checklist Task Uniqueness**
- **Type**: Universal
- **Validates**: Requirement 19.1
- **Statement**: For all tenants T and task keys K, at most one checklist record exists for (T, K)
- **Test Strategy**: Attempt duplicate checklist entries, verify constraint

**Property 20: Checklist Completion Percentage**
- **Type**: Universal
- **Validates**: Requirement 19.3
- **Statement**: For all tenants T, T.checklistProgress = (completedTasks(T) / totalTasks) * 100
- **Test Strategy**: Complete tasks, verify percentage calculation



### Multi-Tenancy Isolation Properties (Properties 21-40)

**Property 21: Tenant Context Extraction**
- **Type**: Universal
- **Validates**: Requirement 7.1
- **Statement**: For all requests R with Host header "subdomain.nexsaas.com", extractTenant(R) = findTenantBySubdomain("subdomain")
- **Test Strategy**: Send requests with various subdomains, verify extraction

**Property 22: Invalid Tenant Rejection**
- **Type**: Universal
- **Validates**: Requirement 7.3
- **Statement**: For all requests R, if extractTenant(R) = null, then response(R).status = 404
- **Test Strategy**: Request non-existent subdomain, verify 404

**Property 23: Query Interception for SELECT**
- **Type**: Universal
- **Validates**: Requirement 7.5
- **Statement**: For all SELECT queries Q in tenant context T, interceptQuery(Q, T) contains "WHERE tenant_id = T.id"
- **Test Strategy**: Execute SELECT, verify tenant_id in query

**Property 24: Query Interception for INSERT**
- **Type**: Universal
- **Validates**: Requirement 7.6
- **Statement**: For all INSERT queries Q in tenant context T, interceptQuery(Q, T) sets tenant_id = T.id
- **Test Strategy**: Execute INSERT, verify tenant_id set

**Property 25: Query Interception for UPDATE**
- **Type**: Universal
- **Validates**: Requirement 7.5
- **Statement**: For all UPDATE queries Q in tenant context T, interceptQuery(Q, T) contains "WHERE tenant_id = T.id"
- **Test Strategy**: Execute UPDATE, verify tenant_id in WHERE clause

**Property 26: Query Interception for DELETE**
- **Type**: Universal
- **Validates**: Requirement 7.5
- **Statement**: For all DELETE queries Q in tenant context T, interceptQuery(Q, T) contains "WHERE tenant_id = T.id"
- **Test Strategy**: Execute DELETE, verify tenant_id in WHERE clause

**Property 27: Tenant ID Override Prevention**
- **Type**: Universal
- **Validates**: Requirement 7.7
- **Statement**: For all INSERT queries Q with explicit tenant_id ≠ currentTenant.id, interceptQuery(Q) throws error
- **Test Strategy**: Attempt INSERT with wrong tenant_id, verify rejection

**Property 28: File Path Isolation**
- **Type**: Universal
- **Validates**: Requirement 8.1
- **Statement**: For all files F uploaded in tenant context T, F.path matches pattern "/storage/tenant_T.id/.*"
- **Test Strategy**: Upload files, verify path structure

**Property 29: File Name Uniqueness**
- **Type**: Universal
- **Validates**: Requirement 8.2
- **Statement**: For all files F1 and F2, if F1 ≠ F2, then F1.filename ≠ F2.filename (UUID-based)
- **Test Strategy**: Upload 1000 files, verify all filenames unique

**Property 30: File Access Authorization**
- **Type**: Universal
- **Validates**: Requirement 8.3, 8.4
- **Statement**: For all file requests R in tenant context T, if extractTenantFromPath(R.path) ≠ T.id, then response = 403
- **Test Strategy**: Attempt cross-tenant file access, verify 403



**Property 31: Redis Key Prefixing**
- **Type**: Universal
- **Validates**: Requirement 9.1
- **Statement**: For all cache operations C in tenant context T, C.key matches pattern "tenant:T.id:.*"
- **Test Strategy**: Cache data, verify key prefix

**Property 32: Redis Key Isolation**
- **Type**: Universal
- **Validates**: Requirement 9.2
- **Statement**: For all tenants T1 and T2 where T1 ≠ T2, keys(T1) ∩ keys(T2) = ∅
- **Test Strategy**: Cache data for 2 tenants, verify no key overlap

**Property 33: Redis Key Cleanup on Tenant Deletion**
- **Type**: Universal
- **Validates**: Requirement 9.4
- **Statement**: For all tenants T, if deleteTenant(T) is called, then count(keys("tenant:T.id:*")) = 0 within 60 seconds
- **Test Strategy**: Delete tenant, verify cache keys purged

**Property 34: Cross-Tenant Data Query Isolation**
- **Type**: Universal
- **Validates**: Requirement 10.2
- **Statement**: For all tenants T1 and T2 where T1 ≠ T2, query(T1, "SELECT * FROM leads") returns zero records from T2
- **Test Strategy**: Create data in T1 and T2, verify isolation

**Property 35: Cross-Tenant File Access Denial**
- **Type**: Universal
- **Validates**: Requirement 10.3
- **Statement**: For all tenants T1 and T2 where T1 ≠ T2, retrieveFile(T1, pathFromT2) throws 403 error
- **Test Strategy**: Upload file in T1, attempt access from T2

**Property 36: Cross-Tenant Cache Isolation**
- **Type**: Universal
- **Validates**: Requirement 10.4
- **Statement**: For all tenants T1 and T2 where T1 ≠ T2, getCache(T1, keyFromT2) returns null
- **Test Strategy**: Cache data in T1, attempt access from T2

**Property 37: Cross-Tenant API Authorization**
- **Type**: Universal
- **Validates**: Requirement 10.5
- **Statement**: For all API requests R with auth token from T1 accessing resource from T2, response = 403
- **Test Strategy**: Authenticate as T1, attempt to access T2 resources

**Property 38: Isolation Test Suite Execution Time**
- **Type**: Universal
- **Validates**: Requirement 10.6
- **Statement**: For all test suite executions E, E.duration < 60 seconds
- **Test Strategy**: Run full isolation test suite, measure duration

**Property 39: Isolation Test Coverage**
- **Type**: Universal
- **Validates**: Requirement 10.1-10.5
- **Statement**: Test suite covers database, file, cache, and API isolation checks
- **Test Strategy**: Verify test suite includes all isolation dimensions

**Property 40: CI/CD Isolation Test Enforcement**
- **Type**: Universal
- **Validates**: Requirement 10.7
- **Statement**: For all deployments D, if isolationTests(D) = fail, then deploy(D) is blocked
- **Test Strategy**: Simulate test failure, verify deployment blocked



### Kubernetes Deployment Properties (Properties 41-61)

**Property 41: Helm Chart Component Completeness**
- **Type**: Universal
- **Validates**: Requirement 11.1
- **Statement**: Helm chart includes deployments for PHP-FPM, Nginx, MySQL, Redis, and AI Engine
- **Test Strategy**: Parse Helm templates, verify all components present

**Property 42: Helm Chart Configurability**
- **Type**: Universal
- **Validates**: Requirement 11.2
- **Statement**: For all configuration values V in {replicas, resources, env}, V is overridable via values.yaml
- **Test Strategy**: Override values, verify applied in rendered templates

**Property 43: ConfigMap and Secret Separation**
- **Type**: Universal
- **Validates**: Requirement 11.3
- **Statement**: For all configuration C, if C.sensitive = true, then C stored in Secret, else ConfigMap
- **Test Strategy**: Verify passwords in Secrets, non-sensitive in ConfigMaps

**Property 44: Service Resource Definitions**
- **Type**: Universal
- **Validates**: Requirement 11.4
- **Statement**: For all deployments D, exists Service S where S.selector = D.labels
- **Test Strategy**: Verify Service for each deployment

**Property 45: Ingress TLS Configuration**
- **Type**: Universal
- **Validates**: Requirement 11.5
- **Statement**: Ingress resource includes TLS configuration with certificate reference
- **Test Strategy**: Parse Ingress, verify TLS section present

**Property 46: Init Container for Migrations**
- **Type**: Universal
- **Validates**: Requirement 11.6
- **Statement**: PHP-FPM deployment includes initContainer that runs migrations before main container
- **Test Strategy**: Verify initContainer in deployment spec

**Property 47: Local Deployment Compatibility**
- **Type**: Universal
- **Validates**: Requirement 11.7
- **Statement**: Helm chart deploys successfully to minikube and kind clusters
- **Test Strategy**: Deploy to minikube and kind, verify all pods running

**Property 48: HPA Replica Bounds**
- **Type**: Universal
- **Validates**: Requirement 12.1
- **Statement**: For HPA resource H, H.minReplicas = 2 AND H.maxReplicas = 10
- **Test Strategy**: Parse HPA spec, verify replica bounds

**Property 49: HPA CPU Threshold**
- **Type**: Universal
- **Validates**: Requirement 12.2
- **Statement**: For HPA resource H, H.targetCPUUtilization = 70
- **Test Strategy**: Parse HPA spec, verify CPU threshold

**Property 50: HPA Scale-Up Responsiveness**
- **Type**: Universal
- **Validates**: Requirement 12.6
- **Statement**: For all scale-up events E, E.duration < 60 seconds from threshold breach
- **Test Strategy**: Simulate CPU spike, measure scale-up time



**Property 51: Resource Requests and Limits**
- **Type**: Universal
- **Validates**: Requirement 12.5
- **Statement**: For all pods P, P.resources.requests and P.resources.limits are defined
- **Test Strategy**: Parse all deployments, verify resource specs

**Property 52: PVC Storage Capacity**
- **Type**: Universal
- **Validates**: Requirement 13.1
- **Statement**: PersistentVolumeClaim requests 100Gi storage with ReadWriteMany access mode
- **Test Strategy**: Parse PVC spec, verify capacity and access mode

**Property 53: PVC Mount Path**
- **Type**: Universal
- **Validates**: Requirement 13.2
- **Statement**: PHP-FPM deployment mounts PVC at /var/www/html/storage
- **Test Strategy**: Verify volumeMount in deployment spec

**Property 54: Dynamic Storage Provisioning**
- **Type**: Universal
- **Validates**: Requirement 13.3
- **Statement**: PVC uses StorageClass for dynamic provisioning in cloud environments
- **Test Strategy**: Deploy to cloud, verify PV auto-provisioned

**Property 55: Storage Backup Configuration**
- **Type**: Universal
- **Validates**: Requirement 13.4
- **Statement**: PVC includes annotations for daily backup with 30-day retention
- **Test Strategy**: Verify backup annotations in PVC spec

**Property 56: Liveness Probe Configuration**
- **Type**: Universal
- **Validates**: Requirement 14.1, 14.2
- **Statement**: PHP-FPM deployment includes livenessProbe checking /health every 30s with 3 failure threshold
- **Test Strategy**: Parse deployment, verify liveness probe spec

**Property 57: Readiness Probe Configuration**
- **Type**: Universal
- **Validates**: Requirement 14.3, 14.4
- **Statement**: PHP-FPM deployment includes readinessProbe checking /ready every 10s
- **Test Strategy**: Parse deployment, verify readiness probe spec

**Property 58: Health Endpoint Functionality**
- **Type**: Universal
- **Validates**: Requirement 14.5
- **Statement**: For all requests R to /health, if app.canProcessRequests = true, then response = 200
- **Test Strategy**: Call /health endpoint, verify 200 response

**Property 59: Readiness Endpoint Functionality**
- **Type**: Universal
- **Validates**: Requirement 14.6
- **Statement**: For all requests R to /ready, if app.initialized = true AND db.connected = true, then response = 200
- **Test Strategy**: Call /ready endpoint, verify 200 response

**Property 60: Secret Storage Security**
- **Type**: Universal
- **Validates**: Requirement 15.1, 15.2
- **Statement**: For all sensitive values V, V stored in Secret resource, not ConfigMap or values.yaml
- **Test Strategy**: Audit all resources, verify no secrets in plain text

**Property 61: Secret Rotation Trigger**
- **Type**: Universal
- **Validates**: Requirement 15.6
- **Statement**: For all secret updates S, rolling update of affected pods completes within 5 minutes
- **Test Strategy**: Update secret, measure pod restart time



### Trial Management Properties (Properties for Requirement 16)

**Property 62: Trial Period Duration**
- **Type**: Universal
- **Validates**: Requirement 16.1
- **Statement**: For all tenants T created at time t, T.trialEndDate = t + 14 days
- **Test Strategy**: Create tenant, verify trial end date

**Property 63: Trial Banner Display**
- **Type**: Universal
- **Validates**: Requirement 16.2
- **Statement**: For all tenants T where T.status = 'trial', dashboard displays banner with daysRemaining(T)
- **Test Strategy**: Login during trial, verify banner present

**Property 64: Trial Expiration Access Restriction**
- **Type**: Universal
- **Validates**: Requirement 16.3
- **Statement**: For all tenants T where T.status = 'trial' AND currentTime > T.trialEndDate, access returns upgrade prompt
- **Test Strategy**: Expire trial, verify access blocked

**Property 65: Trial Reminder Emails**
- **Type**: Universal
- **Validates**: Requirement 16.4
- **Statement**: For all tenants T, reminder emails scheduled at T.trialEndDate - 4 days, -1 day, and 0 days
- **Test Strategy**: Create tenant, verify 3 reminder emails scheduled

**Property 66: Trial Upgrade Status Change**
- **Type**: Universal
- **Validates**: Requirement 16.5
- **Statement**: For all tenants T, if upgrade(T) succeeds, then T.status = 'active' AND trialRestrictions(T) = false
- **Test Strategy**: Upgrade tenant, verify status and access

## Implementation Notes

### Technology Stack
- **Backend**: PHP 8.2+ with Laravel framework
- **Database**: MySQL 8.0+ with schema-per-tenant architecture
- **Cache**: Redis 7.0+ with key prefixing
- **Container Orchestration**: Kubernetes 1.27+
- **Package Management**: Helm 3.12+
- **DNS/SSL**: Let's Encrypt with cert-manager
- **Email**: SMTP with queue-based delivery

### Security Considerations
1. All passwords hashed with bcrypt (cost factor 12)
2. Verification and invitation tokens use cryptographically secure random generation
3. SSL/TLS enforced for all subdomain traffic
4. Database users restricted to tenant-specific schemas
5. File uploads validated for type and size
6. Rate limiting on signup and verification endpoints

### Performance Considerations
1. Database connection pooling per tenant schema
2. Redis caching for tenant configuration lookups
3. Async job processing for email sending and data seeding
4. CDN for static assets
5. Database indexes on tenant_id columns
6. Lazy loading of tenant schemas

### Scalability Considerations
1. Horizontal scaling via Kubernetes HPA
2. Database read replicas for tenant schemas
3. Redis cluster for distributed caching
4. Object storage (S3) for file uploads
5. Queue workers for background jobs
6. Stateless application design

### Monitoring and Observability
1. Prometheus metrics for HPA decisions
2. Application logs with tenant context
3. Distributed tracing for request flows
4. Alerting on isolation test failures
5. Dashboard for tenant provisioning status
6. Audit logs for tenant operations

