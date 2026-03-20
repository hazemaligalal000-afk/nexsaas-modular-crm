# Implementation Plan: Go-to-Market Infrastructure

## Overview

This implementation plan covers the development of NexSaaS's go-to-market infrastructure across three critical areas:

1. **Documentation System**: Auto-generated API docs, Help Center, video tutorials, and Postman collections
2. **Support Infrastructure**: Chat widget, ticketing system, status page, and SLA documentation
3. **Monitoring & Analytics**: Error tracking, business analytics, infrastructure monitoring, and alerting

The implementation follows the existing NexSaaS architecture (PHP 8.3 backend, React frontend, FastAPI AI engine, PostgreSQL database) and integrates industry-standard tools (Sentry, Prometheus, Grafana, PagerDuty).

## Tasks

### Phase 1: Documentation System Foundation

- [ ] 1. Set up OpenAPI specification generator
  - [ ] 1.1 Create OpenAPI generator core classes
    - Create `modular_core/core/OpenAPI/OpenAPIGenerator.php` with methods to scan controllers and generate OpenAPI 3.0 JSON
    - Create `modular_core/core/OpenAPI/Attributes/ApiEndpoint.php` PHP attribute for documenting endpoints
    - Create `modular_core/core/OpenAPI/OpenAPIValidator.php` to validate generated specs against OpenAPI 3.0 schema
    - _Requirements: 1.1, 1.7_
  
  - [ ]* 1.2 Write property test for OpenAPI generation validity
    - **Property 1: OpenAPI Generation Validity**
    - **Validates: Requirements 1.1**
  
  - [ ]* 1.3 Write property test for OpenAPI specification completeness
    - **Property 2: OpenAPI Specification Completeness**
    - **Validates: Requirements 1.3, 1.4, 1.5, 1.6**

- [ ] 2. Implement Swagger UI integration
  - [ ] 2.1 Set up Swagger UI static files and endpoint
    - Download Swagger UI distribution to `public/swagger-ui/`
    - Create route `/api/docs` that serves Swagger UI
    - Configure Swagger UI to load spec from `/api/openapi.json`
    - Enable JWT authentication for "Try it out" functionality
    - _Requirements: 1.2, 1.3_
  
  - [ ] 2.2 Create CLI command for OpenAPI generation
    - Create `modular_core/cli/generate_openapi.php` script
    - Implement controller scanning logic
    - Write generated spec to `public/openapi.json`
    - Add validation step before writing file
    - _Requirements: 1.7_


- [ ] 3. Build Postman collection generator
  - [ ] 3.1 Create Postman collection converter
    - Create `modular_core/core/OpenAPI/PostmanGenerator.php` to convert OpenAPI spec to Postman Collection v2.1 format
    - Implement environment variable generation (baseUrl, authToken)
    - Organize requests by module (CRM, Billing, Platform, etc.)
    - Add example request bodies with valid test data
    - _Requirements: 4.1, 4.2, 4.3, 4.4_
  
  - [ ] 3.2 Integrate Postman generation into build pipeline
    - Update `cli/generate_openapi.php` to also generate Postman collection
    - Write collection to `public/postman/nexsaas-api.json`
    - Add download link in Developer Portal
    - _Requirements: 4.5, 4.6_
  
  - [ ]* 3.3 Write property test for Postman collection completeness
    - **Property 5: Postman Collection Completeness**
    - **Validates: Requirements 4.1, 4.3, 4.4**

- [ ] 4. Checkpoint - Verify API documentation generation
  - Ensure OpenAPI spec is valid and complete
  - Verify Swagger UI loads and displays all endpoints
  - Test Postman collection import and execution
  - Ask the user if questions arise

### Phase 2: Help Center and Documentation Portal

- [ ] 5. Set up Docusaurus help center
  - [ ] 5.1 Initialize Docusaurus project
    - Create `docs/` directory with Docusaurus configuration
    - Configure `docusaurus.config.js` with bilingual support (English and Arabic)
    - Set up navigation structure and sidebars
    - Configure Algolia DocSearch for search functionality
    - _Requirements: 5.1, 5.4, 13.1, 13.2_
  
  - [ ] 5.2 Create Getting Started Guide
    - Write `docs/getting-started.md` with step-by-step setup instructions
    - Include screenshots for each setup step
    - Cover account creation, first lead creation, and team member invitation
    - Ensure guide is completable within 5 minutes
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_
  
  - [ ] 5.3 Create User Guide content structure
    - Create `docs/user-guide/` directory with feature documentation
    - Write documentation for major platform features (Leads, Deals, Contacts, etc.)
    - Add annotated screenshots for each feature
    - Translate all content to Arabic in `i18n/ar/` directory
    - _Requirements: 5.2, 5.3, 5.5_
  
  - [ ]* 5.4 Write property test for user guide feature coverage
    - **Property 6: User Guide Feature Coverage**
    - **Validates: Requirements 5.2**

- [ ] 6. Create webhook integration documentation
  - [ ] 6.1 Write webhook documentation pages
    - Create `docs/api/webhooks.md` documenting all webhook events
    - Include payload examples for each event type
    - Document signature verification process
    - Explain retry logic and failure handling
    - Provide code samples in Python, PHP, and JavaScript
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [ ] 7. Build FAQ and changelog pages
  - [ ] 7.1 Create FAQ page with categorization
    - Create `docs/faq.md` with at least 30 common questions
    - Organize by category (Billing, Features, Technical, etc.)
    - Add links to related User Guide sections
    - Translate to Arabic
    - _Requirements: 7.1, 7.2, 7.4, 7.5_
  
  - [ ] 7.2 Implement FAQ search functionality
    - Configure Algolia search to index FAQ content
    - Implement relevance ranking for search results
    - _Requirements: 7.3, 7.6_
  
  - [ ]* 7.3 Write property test for FAQ search relevance
    - **Property 11: FAQ Search Relevance**
    - **Validates: Requirements 7.3, 7.6**
  
  - [ ] 7.4 Create changelog page
    - Create `docs/changelog.md` with version history
    - Categorize changes as Features, Improvements, or Fixes
    - Include release dates for all entries
    - Add link in application footer
    - _Requirements: 8.1, 8.2, 8.3, 8.4_
  
  - [ ]* 7.5 Write property test for changelog entry categorization
    - **Property 12: Changelog Entry Categorization**
    - **Validates: Requirements 8.1, 8.2, 8.3**

- [ ] 8. Integrate video tutorials
  - [ ] 8.1 Set up video tutorial infrastructure
    - Create database table `video_tutorials` for metadata storage
    - Create `modular_core/modules/Platform/Documentation/VideoTutorialService.php` for video management
    - Implement video embedding in Docusaurus pages
    - _Requirements: 6.1, 6.5, 6.6_
  
  - [ ] 8.2 Create video tutorial tracking
    - Implement view count tracking for videos
    - Create API endpoint to increment view counts
    - Add analytics for video engagement
    - _Requirements: 13.4_
  
  - [ ]* 8.3 Write property test for video tutorial requirements
    - **Property 9: Video Tutorial Requirements**
    - **Validates: Requirements 6.2, 6.4, 6.5**

- [ ] 9. Implement help center features
  - [ ] 9.1 Build help center aggregation page
    - Create help center homepage aggregating all documentation types
    - Display popular articles based on view counts
    - Show trending topics
    - Implement article helpfulness rating system
    - _Requirements: 13.1, 13.3, 13.4, 13.5_
  
  - [ ] 9.2 Create documentation tracking system
    - Create tables `doc_page_views` and `doc_feedback` for analytics
    - Implement view count tracking for all documentation pages
    - Track last_updated timestamps for all pages
    - _Requirements: 13.4, 20.3_
  
  - [ ]* 9.3 Write property test for help center search coverage
    - **Property 26: Help Center Search Coverage**
    - **Validates: Requirements 13.2**
  
  - [ ] 9.4 Implement language preference routing
    - Detect user language preference from profile settings
    - Automatically route to Arabic content when preference is set
    - Add language switcher in navigation
    - _Requirements: 5.6_
  
  - [ ]* 9.5 Write property test for language preference routing
    - **Property 8: Language Preference Routing**
    - **Validates: Requirements 5.6**

- [ ] 10. Checkpoint - Verify documentation portal
  - Ensure help center builds successfully
  - Test bilingual content display
  - Verify search functionality works in both languages
  - Test video embeds and tracking
  - Ask the user if questions arise


### Phase 3: Support Infrastructure

- [ ] 11. Integrate Crisp chat widget
  - [ ] 11.1 Create chat widget React component
    - Create `modular_core/react-frontend/src/components/SupportChat.jsx`
    - Load Crisp script asynchronously
    - Pass user context (name, email, tenant ID, plan) to Crisp
    - Implement Enterprise customer routing to priority queue
    - _Requirements: 9.1, 9.2, 9.6_
  
  - [ ] 11.2 Implement chat session management
    - Store chat transcripts in `chat_transcripts` table
    - Implement session history persistence
    - Add file attachment support with 10MB size limit
    - _Requirements: 9.4, 9.5_
  
  - [ ]* 11.3 Write property test for chat widget availability
    - **Property 13: Chat Widget Availability**
    - **Validates: Requirements 9.1**
  
  - [ ]* 11.4 Write property test for file attachment size validation
    - **Property 16: File Attachment Size Validation**
    - **Validates: Requirements 9.5**
  
  - [ ]* 11.5 Write property test for Enterprise support routing
    - **Property 17: Enterprise Support Routing**
    - **Validates: Requirements 9.6**

- [ ] 12. Build Linear ticketing integration
  - [ ] 12.1 Create Linear API service
    - Create `modular_core/modules/Platform/Support/LinearTicketService.php`
    - Implement ticket creation via Linear GraphQL API
    - Implement ticket status querying
    - Implement ticket priority updates
    - _Requirements: 10.1, 10.2, 10.3_
  
  - [ ] 12.2 Create support ticket database schema
    - Create `support_tickets` table with tenant isolation
    - Create `ticket_comments` table for updates
    - Add indexes for performance (tenant_id, status, created_at)
    - _Requirements: 10.1, 10.2, 10.4_
  
  - [ ] 12.3 Build ticket management API
    - Create `modular_core/modules/Platform/Support/TicketController.php`
    - Implement endpoints: create ticket, list tickets, get ticket details, add comment
    - Implement RBAC for ticket access (users can only see their tenant's tickets)
    - Send email notifications on status changes
    - _Requirements: 10.5, 10.6_
  
  - [ ]* 12.4 Write property test for ticket creation
    - **Property 18: Ticket Creation for Issues**
    - **Validates: Requirements 10.1, 10.2**
  
  - [ ]* 12.5 Write property test for ticket status validity
    - **Property 19: Ticket Status Validity**
    - **Validates: Requirements 10.3, 10.4**
  
  - [ ]* 12.6 Write property test for ticket status notifications
    - **Property 20: Ticket Status Change Notifications**
    - **Validates: Requirements 10.5**

- [ ] 13. Implement ticket escalation system
  - [ ] 13.1 Create escalation worker
    - Create `modular_core/cli/escalate_tickets.php` cron job
    - Query tickets unresolved for 24+ hours
    - Update priority in Linear
    - Send Slack notifications to senior support
    - _Requirements: 10.7_
  
  - [ ]* 13.2 Write property test for ticket escalation
    - **Property 21: Ticket Escalation After 24 Hours**
    - **Validates: Requirements 10.7**

- [ ] 14. Set up Upptime status page
  - [ ] 14.1 Create Upptime repository and configuration
    - Create GitHub repository `nexsaas-status`
    - Configure `.upptimerc.yml` with monitored endpoints
    - Set up GitHub Actions workflows
    - Configure custom domain `status.nexsaas.com`
    - _Requirements: 11.1, 11.2, 11.6, 11.7_
  
  - [ ] 14.2 Create health check endpoints
    - Create `modular_core/modules/Platform/Health/HealthController.php`
    - Implement `/health` endpoint checking database, Redis, RabbitMQ, storage
    - Return JSON with status and component health
    - _Requirements: 11.1_
  
  - [ ] 14.3 Configure status page notifications
    - Set up Slack webhook for incident notifications
    - Configure email notifications for subscribers
    - Set up scheduled maintenance announcements
    - _Requirements: 11.3, 11.5_
  
  - [ ]* 14.4 Write property test for status page service coverage
    - **Property 22: Status Page Service Coverage**
    - **Validates: Requirements 11.1**
  
  - [ ]* 14.5 Write property test for incident report timing
    - **Property 23: Incident Report Timing**
    - **Validates: Requirements 11.4**

- [ ] 15. Create SLA documentation
  - [ ] 15.1 Write SLA document
    - Create `public/legal/sla.md` with uptime guarantees, support response times, and service credits
    - Define severity levels and response time commitments
    - Document backup and disaster recovery policies
    - _Requirements: 12.1, 12.2, 12.3, 12.4_
  
  - [ ] 15.2 Implement SLA document delivery
    - Convert SLA markdown to PDF
    - Create service to attach SLA to Enterprise onboarding emails
    - Store signed SLA documents in database
    - _Requirements: 12.6_
  
  - [ ]* 15.3 Write property test for Enterprise SLA delivery
    - **Property 25: Enterprise SLA Document Delivery**
    - **Validates: Requirements 12.6**

- [ ] 16. Checkpoint - Verify support infrastructure
  - Test chat widget on all pages
  - Create test tickets and verify Linear integration
  - Test ticket escalation with time-mocked scenarios
  - Verify status page updates correctly
  - Ask the user if questions arise


### Phase 4: Error Tracking and Business Analytics

- [ ] 17. Integrate Sentry error tracking
  - [ ] 17.1 Set up Sentry for PHP backend
    - Install Sentry PHP SDK via Composer
    - Create `modular_core/bootstrap/sentry.php` initialization
    - Configure DSN, environment, and release tracking
    - Implement PII scrubbing in before_send callback
    - Set user context in authentication middleware
    - _Requirements: 14.1, 14.4_
  
  - [ ] 17.2 Set up Sentry for React frontend
    - Install Sentry React SDK via npm
    - Create `modular_core/react-frontend/src/sentry.js` configuration
    - Enable Browser Tracing and Session Replay
    - Implement error boundary components
    - Set user context after login
    - _Requirements: 14.2, 14.4_
  
  - [ ] 17.3 Configure Sentry alert rules
    - Create alert for high error rate (>10 errors in 5 minutes)
    - Create alert for new error types
    - Create alert for performance degradation (p95 > 3s)
    - Configure Slack and email notifications
    - _Requirements: 14.6_
  
  - [ ]* 17.4 Write property test for exception capture completeness
    - **Property 28: Exception Capture Completeness**
    - **Validates: Requirements 14.1, 14.2, 14.4**
  
  - [ ]* 17.5 Write property test for error grouping logic
    - **Property 29: Error Grouping Logic**
    - **Validates: Requirements 14.3**
  
  - [ ]* 17.6 Write property test for high-frequency error alerting
    - **Property 30: High-Frequency Error Alerting**
    - **Validates: Requirements 14.6**

- [ ] 18. Build business analytics system
  - [ ] 18.1 Create analytics database schema
    - Create `business_events` table with partitioning by month
    - Create `daily_metrics` aggregation table
    - Create `retention_cohorts` materialized view
    - Add indexes for performance (tenant_id, event_type, created_at)
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6, 15.7_
  
  - [ ] 18.2 Create event emitter service
    - Create `modular_core/modules/Platform/Analytics/EventEmitter.php`
    - Implement RabbitMQ publisher for analytics events
    - Add tracking calls throughout application (login, lead creation, email sent, etc.)
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6_
  
  - [ ] 18.3 Create analytics worker
    - Create `modular_core/cli/analytics_worker.php` to consume RabbitMQ queue
    - Implement batch insertion to database
    - Add error handling and dead letter queue
    - _Requirements: 15.8_
  
  - [ ]* 18.4 Write property test for analytics event recording
    - **Property 31: Analytics Event Recording**
    - **Validates: Requirements 15.1, 15.2, 15.3, 15.4, 15.5, 15.6**
  
  - [ ]* 18.5 Write property test for analytics data retention
    - **Property 32: Analytics Data Retention**
    - **Validates: Requirements 15.7**

- [ ] 19. Implement performance monitoring
  - [ ] 19.1 Add Sentry performance tracking
    - Enable transaction tracing in Sentry PHP SDK
    - Track API endpoint response times
    - Track database query execution times
    - Track external API call latency
    - _Requirements: 16.1, 16.2, 16.3_
  
  - [ ] 19.2 Configure performance alerts
    - Create alert for endpoints with p95 > 1s (warning)
    - Create alert for endpoints with p95 > 3s (critical)
    - Send alerts to Slack #performance channel
    - _Requirements: 16.5, 16.6_
  
  - [ ]* 19.3 Write property test for performance metric tracking
    - **Property 33: Performance Metric Tracking**
    - **Validates: Requirements 16.1, 16.2, 16.3**
  
  - [ ]* 19.4 Write property test for performance threshold warnings
    - **Property 34: Performance Threshold Warnings**
    - **Validates: Requirements 16.5, 16.6**

- [ ] 20. Checkpoint - Verify error tracking and analytics
  - Trigger test errors and verify Sentry capture
  - Generate test analytics events and verify storage
  - Check performance tracking in Sentry
  - Verify alert rules trigger correctly
  - Ask the user if questions arise


### Phase 5: Infrastructure Monitoring and Alerting

- [ ] 21. Set up Prometheus metrics collection
  - [ ] 21.1 Create Prometheus configuration
    - Create `prometheus/prometheus.yml` with scrape configs
    - Configure scrape targets for PHP, PostgreSQL, Redis, RabbitMQ, Node
    - Set up alert rules in `prometheus/alerts.yml`
    - Configure Alertmanager integration
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5, 17.6, 17.7_
  
  - [ ] 21.2 Create PHP metrics exporter
    - Create `modular_core/modules/Platform/Monitoring/MetricsController.php`
    - Expose `/metrics` endpoint in Prometheus format
    - Track HTTP request counts, database connections, memory usage, queue depth
    - _Requirements: 17.1, 17.2, 17.3_
  
  - [ ] 21.3 Deploy Prometheus exporters
    - Add postgres-exporter to docker-compose
    - Add redis-exporter to docker-compose
    - Configure RabbitMQ Prometheus plugin
    - Add node-exporter for system metrics
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5_
  
  - [ ]* 21.4 Write property test for infrastructure metric collection
    - **Property 35: Infrastructure Metric Collection**
    - **Validates: Requirements 17.1, 17.2, 17.3, 17.4, 17.5**

- [ ] 22. Configure Prometheus alert rules
  - [ ] 22.1 Create alert rules for application metrics
    - High error rate alert (>5% of requests)
    - Slow API response alert (p95 > 3s)
    - Database connection pool exhaustion alert
    - _Requirements: 18.1, 18.2, 18.3_
  
  - [ ] 22.2 Create alert rules for infrastructure metrics
    - High CPU usage alert (>80% for 5 minutes)
    - High memory usage alert (>85% for 5 minutes)
    - Disk space alert (>90% capacity)
    - _Requirements: 17.8, 18.4_
  
  - [ ]* 22.3 Write property test for CPU usage alert threshold
    - **Property 36: CPU Usage Alert Threshold**
    - **Validates: Requirements 17.8**
  
  - [ ]* 22.4 Write property test for alert threshold monitoring
    - **Property 37: Alert Threshold Monitoring**
    - **Validates: Requirements 18.1, 18.2, 18.3, 18.4, 18.5**

- [ ] 23. Set up Grafana dashboards
  - [ ] 23.1 Deploy Grafana and configure data sources
    - Add Grafana to docker-compose
    - Configure Prometheus as data source
    - Set up admin authentication
    - _Requirements: 17.7_
  
  - [ ] 23.2 Create application overview dashboard
    - Panel for request rate
    - Panel for error rate
    - Panel for response time (p95)
    - Panel for active users
    - _Requirements: 16.4_
  
  - [ ] 23.3 Create infrastructure health dashboard
    - Panel for CPU usage gauge
    - Panel for memory usage gauge
    - Panel for database connections graph
    - Panel for queue depth graph
    - _Requirements: 17.1, 17.2, 17.3_

- [ ] 24. Integrate PagerDuty alerting
  - [ ] 24.1 Configure Alertmanager for PagerDuty
    - Create `prometheus/alertmanager.yml` configuration
    - Set up PagerDuty integration with service key
    - Configure routing rules (critical → PagerDuty, warning → Slack)
    - Implement alert grouping and deduplication
    - _Requirements: 18.5, 18.6_
  
  - [ ] 24.2 Implement alert suppression logic
    - Configure 15-minute suppression window for duplicate alerts
    - Implement escalation policy for unacknowledged critical alerts (10 minutes)
    - _Requirements: 18.7, 18.8_
  
  - [ ]* 24.3 Write property test for duplicate alert suppression
    - **Property 38: Duplicate Alert Suppression**
    - **Validates: Requirements 18.7**
  
  - [ ]* 24.4 Write property test for critical alert escalation
    - **Property 39: Critical Alert Escalation**
    - **Validates: Requirements 18.8**

- [ ] 25. Checkpoint - Verify monitoring and alerting
  - Check Prometheus is scraping all targets
  - Verify Grafana dashboards display data
  - Trigger test alerts and verify PagerDuty delivery
  - Test alert suppression and escalation
  - Ask the user if questions arise


### Phase 6: Usage Dashboards and Analytics UI

- [ ] 26. Build usage analytics backend
  - [ ] 26.1 Create usage dashboard controller
    - Create `modular_core/modules/Platform/Analytics/UsageDashboardController.php`
    - Implement endpoint for daily active users (DAU)
    - Implement endpoint for feature adoption rates
    - Implement endpoint for retention cohorts
    - Implement endpoint for average session duration
    - _Requirements: 19.1, 19.2, 19.3, 19.4_
  
  - [ ] 26.2 Optimize dashboard queries
    - Create indexes on business_events table
    - Implement query caching (15-minute TTL)
    - Use daily_metrics aggregation table for performance
    - Refresh retention_cohorts materialized view daily
    - _Requirements: 19.6_
  
  - [ ] 26.3 Implement dashboard access control
    - Add RBAC check to restrict access to platform administrators only
    - Return 403 Forbidden for non-admin users
    - _Requirements: 19.7_
  
  - [ ]* 26.4 Write property test for usage dashboard access control
    - **Property 41: Usage Dashboard Access Control**
    - **Validates: Requirements 19.7**

- [ ] 27. Build usage analytics frontend
  - [ ] 27.1 Create usage dashboard React component
    - Create `modular_core/react-frontend/src/modules/Analytics/UsageDashboard.jsx`
    - Implement date range picker for filtering
    - Create charts for DAU, feature adoption, and retention cohorts
    - Implement auto-refresh every 15 minutes
    - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 19.6_
  
  - [ ] 27.2 Add tenant tier filtering
    - Implement dropdown to filter by tenant tier (Starter, Professional, Enterprise)
    - Update all charts based on selected filter
    - _Requirements: 19.5_
  
  - [ ]* 27.3 Write property test for usage dashboard filtering
    - **Property 40: Usage Dashboard Filtering**
    - **Validates: Requirements 19.5**

- [ ] 28. Checkpoint - Verify usage dashboards
  - Test dashboard with sample analytics data
  - Verify access control for non-admin users
  - Test filtering and date range selection
  - Verify auto-refresh functionality
  - Ask the user if questions arise

### Phase 7: Documentation Maintenance and Deployment

- [ ] 29. Implement documentation maintenance system
  - [ ] 29.1 Create documentation tracking system
    - Add last_updated timestamp to all documentation pages
    - Create automated script to detect stale documentation (>90 days)
    - Implement documentation review checklist
    - _Requirements: 20.3, 20.4_
  
  - [ ] 29.2 Set up documentation review workflow
    - Require documentation updates before feature deployment
    - Implement reviewer approval process for documentation changes
    - Version documentation alongside code releases
    - _Requirements: 20.1, 20.2, 20.5, 20.6_
  
  - [ ]* 29.3 Write property test for documentation timestamp tracking
    - **Property 42: Documentation Timestamp Tracking**
    - **Validates: Requirements 20.3**
  
  - [ ]* 29.4 Write property test for stale documentation flagging
    - **Property 43: Stale Documentation Flagging**
    - **Validates: Requirements 20.4**

- [ ] 30. Deploy documentation to production
  - [ ] 30.1 Set up Docusaurus build pipeline
    - Create GitHub Actions workflow for documentation builds
    - Configure deployment to CDN (Cloudflare Pages, Netlify, or Vercel)
    - Set up custom domain `docs.nexsaas.com`
    - _Requirements: 13.6_
  
  - [ ] 30.2 Configure documentation search
    - Set up Algolia DocSearch crawler
    - Configure weekly re-indexing
    - Test search in both English and Arabic
    - _Requirements: 5.4, 13.2_

- [ ] 31. Annotate existing API endpoints
  - [ ] 31.1 Add OpenAPI annotations to CRM controllers
    - Annotate LeadController endpoints
    - Annotate DealController endpoints
    - Annotate ContactController endpoints
    - _Requirements: 1.1, 1.3, 1.4, 1.5, 1.6_
  
  - [ ] 31.2 Add OpenAPI annotations to Platform controllers
    - Annotate AuthController endpoints
    - Annotate UserController endpoints
    - Annotate TenantController endpoints
    - Annotate BillingController endpoints
    - _Requirements: 1.1, 1.3, 1.4, 1.5, 1.6_
  
  - [ ] 31.3 Add OpenAPI annotations to remaining modules
    - Annotate Accounting module endpoints
    - Annotate ERP module endpoints
    - Annotate Inventory module endpoints
    - _Requirements: 1.1, 1.3, 1.4, 1.5, 1.6_

- [ ] 32. Checkpoint - Verify complete documentation system
  - Generate OpenAPI spec from all annotated endpoints
  - Verify Swagger UI displays complete API reference
  - Test Postman collection with all endpoints
  - Verify help center is fully deployed and searchable
  - Ask the user if questions arise


### Phase 8: Infrastructure Deployment and Configuration

- [ ] 33. Deploy monitoring stack
  - [ ] 33.1 Create docker-compose configuration for monitoring
    - Create `docker-compose.monitoring.yml` with Prometheus, Grafana, Alertmanager
    - Add postgres-exporter, redis-exporter, node-exporter services
    - Configure volumes for data persistence
    - Set up networking between services
    - _Requirements: 17.6, 17.7_
  
  - [ ] 33.2 Configure environment variables
    - Add Sentry DSN and configuration to `.env`
    - Add Crisp website ID
    - Add Linear API key and team ID
    - Add PagerDuty service key
    - Add Vimeo access token
    - Add Algolia credentials
    - Add Grafana admin password
    - Add Slack webhook URL
    - _Requirements: All monitoring and support requirements_

- [ ] 34. Set up external service integrations
  - [ ] 34.1 Configure Crisp account
    - Create Crisp account and workspace
    - Configure business hours and auto-responses
    - Set up routing rules for Enterprise segment
    - Enable file attachments (max 10MB)
    - _Requirements: 9.1, 9.2, 9.4, 9.5, 9.6_
  
  - [ ] 34.2 Configure Linear workspace
    - Create Linear team for support tickets
    - Set up labels for ticket categories
    - Configure priority levels
    - Create API key with appropriate permissions
    - _Requirements: 10.1, 10.2, 10.3, 10.4_
  
  - [ ] 34.3 Configure PagerDuty
    - Create PagerDuty service for NexSaaS
    - Set up escalation policy (4 levels: Primary → Secondary → Manager → CTO)
    - Configure notification channels (push, SMS, phone, email)
    - Create integration key for Alertmanager
    - _Requirements: 18.5, 18.6, 18.8_
  
  - [ ] 34.4 Configure Sentry projects
    - Create Sentry project for PHP backend
    - Create Sentry project for React frontend
    - Create Sentry project for FastAPI AI engine
    - Configure alert rules for each project
    - Set up Slack integration
    - _Requirements: 14.1, 14.2, 14.5, 14.6_
  
  - [ ] 34.5 Set up Vimeo account
    - Create Vimeo Pro account
    - Upload placeholder video tutorials
    - Configure privacy settings (hide from Vimeo.com)
    - Generate embed codes for each video
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 35. Configure cron jobs and workers
  - [ ] 35.1 Set up analytics worker
    - Configure systemd service or supervisor for `analytics_worker.php`
    - Ensure worker restarts on failure
    - Set up logging to `/var/log/analytics-worker.log`
    - _Requirements: 15.8_
  
  - [ ] 35.2 Set up ticket escalation cron job
    - Add cron entry: `0 * * * * php cli/escalate_tickets.php`
    - Configure logging and error notifications
    - _Requirements: 10.7_
  
  - [ ] 35.3 Set up OpenAPI regeneration cron job
    - Add cron entry: `0 2 * * * php cli/generate_openapi.php`
    - Automatically commit and deploy updated spec
    - _Requirements: 1.7, 4.6_
  
  - [ ] 35.4 Set up materialized view refresh
    - Add cron entry: `0 2 * * * psql -c "REFRESH MATERIALIZED VIEW retention_cohorts;"`
    - _Requirements: 19.3_

- [ ] 36. Checkpoint - Verify infrastructure deployment
  - Verify all Docker containers are running
  - Test external service integrations
  - Verify cron jobs execute successfully
  - Check logs for errors
  - Ask the user if questions arise

### Phase 9: Testing and Quality Assurance

- [ ] 37. Run comprehensive integration tests
  - [ ] 37.1 Test documentation generation end-to-end
    - Add annotations to sample controller
    - Run OpenAPI generation
    - Verify Swagger UI displays endpoint
    - Verify Postman collection includes endpoint
    - _Requirements: 1.1, 1.7, 4.6_
  
  - [ ] 37.2 Test support workflow end-to-end
    - Send chat message as test user
    - Verify ticket created in Linear
    - Update ticket status in Linear
    - Verify email notification sent
    - Test escalation after 24 hours (time-mocked)
    - _Requirements: 9.2, 10.1, 10.5, 10.7_
  
  - [ ] 37.3 Test monitoring and alerting end-to-end
    - Trigger test error in application
    - Verify Sentry captures error
    - Trigger alert threshold
    - Verify PagerDuty notification sent
    - Acknowledge alert and verify escalation stops
    - _Requirements: 14.1, 14.6, 18.1, 18.5, 18.8_
  
  - [ ] 37.4 Test analytics tracking end-to-end
    - Generate test user actions (login, lead creation, etc.)
    - Verify events published to RabbitMQ
    - Verify analytics worker consumes events
    - Verify events stored in database
    - Verify usage dashboard displays data
    - _Requirements: 15.1, 15.8, 19.1_
  
  - [ ] 37.5 Test help center functionality
    - Search for documentation in English and Arabic
    - View article and verify view count increments
    - Rate article helpfulness
    - Watch video tutorial and verify tracking
    - _Requirements: 5.4, 13.2, 13.4, 13.5_

- [ ] 38. Perform load and performance testing
  - [ ] 38.1 Load test analytics event ingestion
    - Generate 10,000 events/second
    - Verify RabbitMQ queue handles load
    - Verify analytics worker keeps up
    - Monitor database performance
    - _Requirements: 15.8_
  
  - [ ] 38.2 Load test help center search
    - Simulate 1,000 concurrent searches
    - Verify Algolia response times
    - Check for rate limiting issues
    - _Requirements: 13.2_
  
  - [ ] 38.3 Load test Prometheus metrics endpoint
    - Simulate 100 requests/second to `/metrics`
    - Verify response times stay under 100ms
    - Check for memory leaks
    - _Requirements: 17.1, 17.2_

- [ ] 39. Verify security and compliance
  - [ ] 39.1 Audit PII handling
    - Verify Sentry scrubs sensitive data
    - Verify analytics events don't contain PII
    - Verify chat transcripts are encrypted at rest
    - _Requirements: 14.4_
  
  - [ ] 39.2 Test access controls
    - Verify usage dashboard restricted to admins
    - Verify ticket access follows tenant isolation
    - Verify Swagger UI requires authentication for "Try it out"
    - _Requirements: 19.7_
  
  - [ ] 39.3 Verify data retention policies
    - Check analytics events older than 2 years are archived
    - Verify logs older than 90 days are deleted
    - _Requirements: 15.7_

- [ ] 40. Final checkpoint - Complete system verification
  - Run all property-based tests (43 properties)
  - Run all unit tests
  - Verify all integration tests pass
  - Review test coverage (target: >80%)
  - Ensure all tests pass, ask the user if questions arise


### Phase 10: Production Launch and Handoff

- [ ] 41. Prepare production deployment
  - [ ] 41.1 Create deployment runbook
    - Document deployment steps for all components
    - Create rollback procedures
    - Document common issues and solutions
    - _Requirements: All requirements_
  
  - [ ] 41.2 Set up production monitoring
    - Verify all Prometheus targets are healthy
    - Verify Grafana dashboards display production data
    - Verify PagerDuty escalation policies are active
    - Test alert delivery to on-call engineers
    - _Requirements: 17.6, 17.7, 18.5, 18.6_
  
  - [ ] 41.3 Deploy documentation to production
    - Build and deploy help center to `docs.nexsaas.com`
    - Deploy Swagger UI to `/api/docs`
    - Deploy status page to `status.nexsaas.com`
    - Verify all links and embeds work
    - _Requirements: 1.2, 11.6, 13.6_

- [ ] 42. Create operational procedures
  - [ ] 42.1 Document maintenance tasks
    - Daily: Review Sentry errors, check PagerDuty incidents, verify analytics lag
    - Weekly: Update help center, review FAQ, check video views, review Grafana dashboards
    - Monthly: Regenerate OpenAPI spec, update changelog, archive old analytics, review SLA
    - Quarterly: Documentation review, update videos, optimize Prometheus retention, audit tickets
    - _Requirements: 20.1, 20.2, 20.3, 20.4_
  
  - [ ] 42.2 Create troubleshooting guides
    - OpenAPI generation failures
    - Chat widget not loading
    - Alerts not being sent
    - Analytics events not appearing
    - Help center search not working
    - _Requirements: All requirements_

- [ ] 43. Conduct team training
  - [ ] 43.1 Train support team
    - How to use Crisp chat interface
    - How to manage tickets in Linear
    - How to escalate critical issues
    - How to update status page during incidents
    - _Requirements: 9.1, 10.1, 11.4_
  
  - [ ] 43.2 Train development team
    - How to add OpenAPI annotations
    - How to use Sentry for debugging
    - How to interpret Grafana dashboards
    - How to respond to PagerDuty alerts
    - _Requirements: 1.1, 14.1, 17.7, 18.6_
  
  - [ ] 43.3 Train product team
    - How to access usage dashboards
    - How to interpret analytics data
    - How to update documentation
    - How to create video tutorials
    - _Requirements: 19.1, 20.1_

- [ ] 44. Launch go-to-market infrastructure
  - [ ] 44.1 Enable all monitoring and alerting
    - Start Prometheus, Grafana, and Alertmanager
    - Verify all exporters are scraping
    - Enable PagerDuty integration
    - Test end-to-end alert flow
    - _Requirements: 17.6, 17.7, 18.5, 18.6_
  
  - [ ] 44.2 Enable support infrastructure
    - Activate Crisp chat widget on all pages
    - Enable Linear ticket creation
    - Activate status page monitoring
    - _Requirements: 9.1, 10.1, 11.1_
  
  - [ ] 44.3 Enable analytics tracking
    - Start analytics worker
    - Verify events are being tracked
    - Enable usage dashboards for admins
    - _Requirements: 15.1, 19.1_
  
  - [ ] 44.4 Announce launch
    - Update changelog with go-to-market features
    - Send email to customers about new documentation and support
    - Post announcement on status page
    - _Requirements: 8.6_

- [ ] 45. Post-launch monitoring
  - [ ] 45.1 Monitor first 24 hours
    - Track error rates in Sentry (target: <0.1%)
    - Monitor API response times (target: p95 <500ms)
    - Monitor help center traffic and search performance
    - Monitor chat widget response times
    - Track ticket creation and resolution times
    - _Requirements: 14.6, 16.5, 16.6_
  
  - [ ] 45.2 Monitor first week
    - Review analytics event ingestion lag (target: <1 minute)
    - Monitor alert delivery success rate (target: 100%)
    - Track documentation view counts
    - Review support ticket backlog
    - _Requirements: 15.8, 18.5_
  
  - [ ] 45.3 Conduct post-launch review
    - Review any incidents or issues
    - Gather feedback from support and development teams
    - Identify areas for improvement
    - Update documentation based on learnings
    - _Requirements: All requirements_

## Notes

- Tasks marked with `*` are optional property-based tests and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at the end of each phase
- Property tests validate universal correctness properties (43 total properties)
- Unit tests validate specific examples and edge cases
- The implementation follows the existing NexSaaS architecture and integrates with established patterns
- External service integrations (Crisp, Linear, Sentry, PagerDuty, Vimeo, Algolia) require account setup and API keys
- Monitoring stack (Prometheus, Grafana) requires additional Docker containers
- Analytics system uses RabbitMQ for event streaming and PostgreSQL for storage
- Documentation system uses Docusaurus for static site generation with bilingual support
- All components follow security best practices including PII scrubbing, access control, and encryption

## Dependencies

- PHP 8.3 with Composer
- Node.js and npm for Docusaurus
- Docker and docker-compose for monitoring stack
- PostgreSQL 14+ for analytics storage
- RabbitMQ for event streaming
- Redis for caching
- External services: Sentry, Crisp, Linear, PagerDuty, Vimeo, Algolia

## Estimated Timeline

- Phase 1 (Documentation Foundation): 3-4 days
- Phase 2 (Help Center): 4-5 days
- Phase 3 (Support Infrastructure): 4-5 days
- Phase 4 (Error Tracking & Analytics): 3-4 days
- Phase 5 (Infrastructure Monitoring): 4-5 days
- Phase 6 (Usage Dashboards): 2-3 days
- Phase 7 (Documentation Maintenance): 2-3 days
- Phase 8 (Infrastructure Deployment): 2-3 days
- Phase 9 (Testing & QA): 3-4 days
- Phase 10 (Production Launch): 2-3 days

Total: 29-39 days (approximately 6-8 weeks)
