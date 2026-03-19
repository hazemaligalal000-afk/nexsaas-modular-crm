# Implementation Plan: NexSaaS Modular CRM/ERP/Accounting Platform

## Overview

Implement the full NexSaaS platform in phases: Platform Foundation → CRM Module → ERP Module → Accounting Module → Platform Core Services → AI Engine → Cross-Cutting Concerns. Each phase builds on the previous, ending with full integration. Stack: PHP 8.3 MVC, Python FastAPI, React 18/Vite, PostgreSQL 16, Redis 7, RabbitMQ + Celery, Docker/K8s.

---

## Tasks

### Phase 1: Platform Foundation

- [x] 1. Set up project structure, Docker environment, and database schema
  - Create directory layout: `/modular_core/`, `/ai_engine/`, `/frontend/`, `/api/`, `/cron/`
  - Write `docker-compose.yml` with services: php-fpm, postgres16, redis7, rabbitmq, celery-worker, fastapi
  - Write PostgreSQL migration for universal table columns (id, company_code, tenant_id, created_by, created_at, updated_at, deleted_at)
  - Create `tenants` and `users` tables per design schema
  - _Requirements: 1.1, 1.2, 5.1_

- [x] 2. Implement BaseModel, BaseController, BaseService with tenant isolation
  - [x] 2.1 Implement `BaseModel` in `/modular_core/core/BaseModel.php`
    - Enforce `tenant_id` + `company_code` on all queries via adodb
    - Implement `softDelete(int $id)` setting `deleted_at`
    - Implement `scopeQuery()` that auto-appends `WHERE tenant_id = ? AND deleted_at IS NULL`
    - Reject queries constructed without `tenant_id` and return error
    - _Requirements: 1.3, 1.4, 1.5, 1.6_
  - [x] 2.2 Write property test for tenant data isolation
    - **Property 1: Tenant Data Isolation**
    - **Validates: Requirements 1.3, 1.4**
  - [x] 2.3 Write property test for soft delete round trip
    - **Property 2: Soft Delete Round Trip**
    - **Validates: Requirements 1.5, 1.6**
  - [x] 2.4 Implement `BaseController` with `respond()` wrapping all output in API_Response envelope
    - Shape: `{ success, data, error, meta: { company_code, tenant_id, user_id, currency, fin_period, timestamp } }`
    - _Requirements: 3.1, 3.2, 3.3, 3.4_
  - [x] 2.5 Write property test for standard API response envelope
    - **Property 5: Standard API Response Envelope**
    - **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
  - [x] 2.6 Implement `BaseService` with `transaction(callable $fn)` for DB transaction management
    - _Requirements: 5.1_

- [x] 3. Implement Module Bootstrap and auto-discovery
  - [x] 3.1 Implement `ModuleRegistry` in `/modular_core/bootstrap/`
    - Scan `/modular_core/modules/*/module.json` for `{ name, version, dependencies[], permissions[], routes[] }`
    - Topological sort for dependency order
    - On missing dependency: log error, mark module disabled, continue bootstrap
    - _Requirements: 5.2, 5.3, 5.4, 5.5_
  - [x] 3.2 Create `module.json` stubs for CRM, ERP, Accounting, Platform modules
    - _Requirements: 5.1_

- [x] 4. Implement RBAC engine and permission middleware
  - [x] 4.1 Create `role_permissions` table migration
    - _Requirements: 2.1, 2.2, 2.3_
  - [x] 4.2 Implement `RBACService::check(userId, permission)` in `/modular_core/modules/Platform/RBAC/`
    - Redis-first: key `permissions:{tenant_id}:{user_id}`, TTL 300s
    - Fall back to DB on cache miss
    - Publish `rbac.invalidate:{user_id}` on role/permission change; all instances subscribe and delete key within 5s
    - _Requirements: 2.4, 2.5, 2.6, 2.7_
  - [x] 4.3 Implement `PermissionMiddleware` returning HTTP 403 on failure
    - _Requirements: 2.5_
  - [x] 4.4 Implement Admin API endpoint `PUT /api/v1/platform/roles/{role}/permissions`
    - _Requirements: 2.8_
  - [x] 4.5 Write property test for RBAC permission enforcement
    - **Property 4: RBAC Permission Enforcement**
    - **Validates: Requirements 2.3, 2.4, 2.5**

- [x] 5. Implement Authentication and Session Management
  - [x] 5.1 Implement JWT RS256 login: 15-min expiry, refresh token 7-day expiry stored in Redis
    - `POST /api/v1/auth/login`, `POST /api/v1/auth/refresh`, `POST /api/v1/auth/logout`
    - bcrypt cost 12 for password hashing
    - Rate-limit auth endpoints: 10 attempts/min per IP; block 15 min on exceed
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 42.1, 42.5, 42.6_
  - [x] 5.2 Implement JWT key rotation on 90-day schedule without disrupting active sessions
    - _Requirements: 42.7_
  - [x] 5.3 Implement TOTP 2FA via `spomky-labs/otphp`: enroll, verify, backup codes
    - `POST /api/v1/auth/2fa/enroll`, `POST /api/v1/auth/2fa/verify`
    - _Requirements: 4.6, 33.1, 33.2, 33.3, 33.4, 33.5_
  - [x] 5.4 Implement SAML 2.0 SSO via `onelogin/php-saml` and OAuth 2.0/OIDC via `league/oauth2-client`
    - `GET /api/v1/auth/sso/{provider}`, `POST /api/v1/auth/sso/{provider}/callback`
    - Auto-provision user on first SSO login; map IdP groups to RBAC roles
    - _Requirements: 4.7, 34.1, 34.2, 34.3, 34.4, 34.5_

- [x] 6. Checkpoint — Ensure all Phase 1 tests pass
  - Ensure all tests pass, ask the user if questions arise.


---

### Phase 2: CRM Module

- [x] 7. Implement Contact Management
  - [x] 7.1 Create `contacts` table migration with tsvector index and GIN index
    - _Requirements: 6.1_
  - [x] 7.2 Implement `ContactService` in `/modular_core/modules/CRM/Contacts/`
    - `create()`: validate email or phone present; check for duplicate email within tenant
    - `merge(survivorId, duplicateId)`: transfer all activities, deals, notes; soft-delete duplicate in single transaction
    - `search(query)`: full-text search on name, email, phone, company via `tsvector` index; return within 500ms for 1M records
    - _Requirements: 6.2, 6.3, 6.5, 6.7_
  - [x] 7.3 Implement Contact REST endpoints: CRUD + merge + timeline
    - `GET/POST /api/v1/crm/contacts`, `GET/PUT/DELETE /api/v1/crm/contacts/{id}`, `POST /api/v1/crm/contacts/{id}/merge`
    - Support custom fields stored in `contact_custom_fields` JSONB per tenant definition
    - _Requirements: 6.1, 6.4, 6.6_
  - [x] 7.4 Write property test for contact requires email or phone
    - **Property 7: Contact Requires Email or Phone**
    - **Validates: Requirements 6.2**
  - [x] 7.5 Write property test for contact merge completeness
    - **Property 9: Contact Merge Completeness**
    - **Validates: Requirements 6.7**

- [x] 8. Implement Lead Management and Capture
  - [x] 8.1 Create `leads` table migration
    - _Requirements: 7.1_
  - [x] 8.2 Implement `LeadService` in `/modular_core/modules/CRM/Leads/`
    - `capture()`: create lead, enqueue `lead.captured` event within 2s
    - `convert(leadId)`: atomic transaction creating Contact + Account + Deal; rollback all on failure
    - CSV import via `LeadImportJob` (Celery): field mapping, duplicate detection by email/phone
    - _Requirements: 7.2, 7.3, 7.4, 7.5, 7.6, 7.7_
  - [x] 8.3 Implement `LeadFormBuilder` generating embeddable HTML form with CSRF token
    - Public endpoint `POST /api/v1/crm/leads/capture` (no auth required)
    - _Requirements: 7.2, 7.3_
  - [x] 8.4 Implement Lead REST endpoints: CRUD + convert + import
    - `GET/POST /api/v1/crm/leads`, `POST /api/v1/crm/leads/{id}/convert`, `POST /api/v1/crm/leads/import`
    - _Requirements: 7.1, 7.5_

- [x] 9. Implement Lead Scoring integration
  - [x] 9.1 Implement `LeadScoringService` in `/modular_core/modules/CRM/LeadScoring/`
    - On `lead.captured` / `lead.updated`: enqueue `lead.score_request` to RabbitMQ within 5s
    - `applyScore(leadId, score)`: persist score + `score_updated_at`; if delta > 20, push WebSocket notification within 30s
    - _Requirements: 8.1, 8.3, 8.6_
  - [x] 9.2 Implement FastAPI endpoint `POST /predict/lead-score` in `/ai_engine/`
    - Gradient boosted model on demographic + behavioral features
    - Return `{ result: { score: int }, confidence: float, model_version: string }`
    - _Requirements: 8.2, 35.1, 35.2, 35.4, 35.5_
  - [x] 9.3 Write property test for lead score range
    - **Property 10: Lead Score Range**
    - **Validates: Requirements 8.2**
  - [x] 9.4 Write property test for lead score change notification
    - **Property 11: Lead Score Change Notification**
    - **Validates: Requirements 8.6**
  - [x] 9.5 Write property test for AI Engine response contract
    - **Property 6: AI Engine Response Contract**
    - **Validates: Requirements 3.5, 35.2**

- [x] 10. Implement Account Management
  - [x] 10.1 Create `accounts` table migration
    - _Requirements: 9.1_
  - [x] 10.2 Implement `AccountService` with hierarchy validation (max 5 levels)
    - Link multiple contacts to account; compute aggregate deal value and win rate
    - _Requirements: 9.2, 9.3, 9.4, 9.5_
  - [x] 10.3 Implement Account REST endpoints: CRUD + timeline view
    - `GET/POST /api/v1/crm/accounts`, `GET/PUT/DELETE /api/v1/crm/accounts/{id}`
    - _Requirements: 9.1, 9.3_
  - [x] 10.4 Write property test for account hierarchy depth limit
    - **Property 12: Account Hierarchy Depth Limit**
    - **Validates: Requirements 9.4**

- [x] 11. Implement Sales Pipeline and Deal Management
  - [x] 11.1 Create `pipelines`, `pipeline_stages`, `deals`, `deal_stage_history` table migrations
    - _Requirements: 10.1, 10.2_
  - [x] 11.2 Implement `PipelineService` and `DealService`
    - `DealService::moveStage()`: record transition in `deal_stage_history` with timestamp + user
    - `DealService::computeForecast(pipelineId)`: `SUM(value × win_probability)` per pipeline
    - Deal rotting: nightly Celery task sets `is_stale = true` when no activity for configurable days
    - Overdue check: mark `is_overdue = true` when `close_date < NOW()` and stage not closed
    - _Requirements: 10.1, 10.2, 10.3, 10.6, 10.7, 10.8_
  - [x] 11.3 Implement Deal REST endpoints including Kanban and forecast
    - `GET/POST /api/v1/crm/deals`, `PUT /api/v1/crm/deals/{id}/stage`, `GET /api/v1/crm/pipelines/{id}/forecast`
    - _Requirements: 10.4, 10.5, 10.7_
  - [x] 11.4 Write property test for weighted pipeline forecast correctness
    - **Property 13: Weighted Pipeline Forecast Correctness**
    - **Validates: Requirements 10.7**

- [x] 12. Implement Deal Win Probability Prediction
  - [x] 12.1 Implement `DealService` win probability enqueue on deal create/stage/value/date change
    - Enqueue `deal.win_probability_request` within 5s; persist `win_probability` + `win_probability_updated_at`
    - _Requirements: 11.1, 11.3_
  - [x] 12.2 Implement FastAPI endpoint `POST /predict/win-probability`
    - Logistic regression on deal stage, value, age, historical win rate
    - _Requirements: 11.2, 35.1, 35.2_
  - [x] 12.3 Write property test for deal win probability range
    - **Property 14: Deal Win Probability Range**
    - **Validates: Requirements 11.2**

- [x] 13. Implement Omnichannel Inbox
  - [x] 13.1 Create `inbox_conversations` and `inbox_messages` table migrations
    - _Requirements: 12.1_
  - [x] 13.2 Implement `InboxService` aggregating email (IMAP/SMTP), SMS (Twilio), WhatsApp (Meta API), live chat (WebSocket), VoIP (SIP)
    - Auto-link conversation to contact/lead by sender email or phone
    - Track `first_response_at`, `handle_time`, `resolved_at` per conversation
    - _Requirements: 12.1, 12.2, 12.6_
  - [x] 13.3 Implement Inbox REST endpoints and WebSocket real-time delivery within 3s
    - `GET /api/v1/crm/inbox/conversations`, `GET /api/v1/crm/inbox/conversations/{id}/messages`, `POST /api/v1/crm/inbox/conversations/{id}/reply`
    - _Requirements: 12.3, 12.4, 12.5_
  - [x] 13.4 Implement chat widget `/widget.js` served as embeddable script connecting to WebSocket
    - Implement canned responses: pre-written reply templates selectable by agents
    - _Requirements: 12.7, 12.8_

- [x] 14. Implement Email Integration
  - [x] 14.1 Implement Gmail and Microsoft 365 OAuth 2.0 mailbox connection per user
    - Sync emails to Inbox and link to matching contact within 60s
    - Track email open events and link click events
    - _Requirements: 13.1, 13.2, 13.3, 13.4_
  - [x] 14.2 Implement `EmailSyncWorker` (Celery) with error notification and retry logging
    - _Requirements: 13.5_

- [x] 15. Implement Workflow Automation Engine
  - [x] 15.1 Implement `WorkflowEngine::evaluate(event, context)` in `/modular_core/modules/CRM/Workflows/`
    - Find matching enabled workflows; enqueue execution within 5s
    - Trigger types: record created, record updated, field value changed, date/time reached, inbound message received, manual
    - _Requirements: 14.1, 14.2, 14.4, 14.9_
  - [x] 15.2 Implement `WorkflowExecutor` (Celery) running actions sequentially
    - Action types: send email, send SMS, create Task, update field, assign owner, add tag, create Deal, move Deal stage, call Webhook, wait
    - Record each step result in `workflow_execution_steps`
    - Retry: exponential backoff (1s, 2s, 4s) up to 3 retries per failed action
    - _Requirements: 14.3, 14.5, 14.6, 14.7_
  - [x] 15.3 Implement Workflow CRUD endpoints with enable/disable/clone
    - _Requirements: 14.8_
  - [x] 15.4 Write property test for workflow action ordering
    - **Property 15: Workflow Action Ordering**
    - **Validates: Requirements 14.5**
  - [x] 15.5 Write property test for disabled workflow not executed
    - **Property 16: Disabled Workflow Not Executed**
    - **Validates: Requirements 14.9**

- [x] 16. Implement Task, Activity Management, and Calendar Integration
  - [x] 16.1 Create `tasks` and `activities` table migrations
    - _Requirements: 15.1, 15.2_
  - [x] 16.2 Implement `TaskService` with due-date reminder notifications and bulk assignment
    - Activity types: call, email, meeting, note, task
    - Log completed activities to linked record timeline
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6_
  - [x] 16.3 Implement calendar view endpoints (day/week/month) and Google Calendar + Outlook OAuth 2.0 two-way sync
    - Create external calendar event within 30s of meeting creation; reflect external changes within 60s
    - Implement meeting scheduling links (public booking URL)
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5_

- [x] 17. Implement CRM Analytics and Reporting
  - [x] 17.1 Implement pre-built reports: pipeline summary, deal velocity, lead conversion rate, activity summary, revenue forecast
    - Return results within 10s for up to 500K rows
    - _Requirements: 17.1, 17.3_
  - [x] 17.2 Implement drag-and-drop custom report builder with data source, dimensions, metrics, filters
    - Support CSV and PDF export; scheduled report delivery via email
    - _Requirements: 17.2, 17.4, 17.5_
  - [x] 17.3 Implement dashboard builder with configurable grid layout and real-time WebSocket widgets
    - _Requirements: 17.6, 17.7_

- [x] 18. Checkpoint — Ensure all CRM tests pass
  - Ensure all tests pass, ask the user if questions arise.


---

### Phase 3: ERP Module

- [x] 19. Implement Chart of Accounts and General Ledger (ERP)
  - [x] 19.1 Create `chart_of_accounts`, `financial_periods`, `journal_entries`, `journal_entry_lines` table migrations
    - Include all 35 fields on `journal_entry_lines` per design schema
    - Create indexes: `idx_jel_tenant_company`, `idx_jel_fin_period`, `idx_jel_account`
    - _Requirements: 18.1, 18.4, 18.8_
  - [x] 19.2 Implement `JournalEntryService::post()` in `/modular_core/modules/ERP/GL/`
    - Validate Σ Dr = Σ Cr; reject if unbalanced
    - Validate period open; auto-assign voucher_code and section_code
    - Store all 35 fields; convert to EGP using exchange rate
    - Require explicit `company_code` filter on all queries
    - _Requirements: 18.2, 18.3, 18.5, 18.6, 18.7, 18.9, 18.12_
  - [x] 19.3 Implement `TrialBalanceService`, `FinancialStatementService` (P&L, Balance Sheet, Cash Flow)
    - _Requirements: 18.10_
  - [x] 19.4 Implement `FinPeriodService::close()` preventing new posts to closed period
    - _Requirements: 18.13_
  - [x] 19.5 Write property test for double-entry balance invariant
    - **Property 17: Double-Entry Balance Invariant**
    - **Validates: Requirements 18.2, 18.3, 46.6**
  - [x] 19.6 Write property test for voucher code assignment
    - **Property 18: Voucher Code Assignment**
    - **Validates: Requirements 18.5, 18.6, 18.7**
  - [x] 19.7 Write property test for company code query isolation
    - **Property 19: Company Code Query Isolation**
    - **Validates: Requirements 18.9**
  - [x] 19.8 Write property test for closed period immutability
    - **Property 20: Closed Period Immutability**
    - **Validates: Requirements 18.13, 46.19, 58.2**
  - [x] 19.9 Write property test for monetary amount precision
    - **Property 21: Monetary Amount Precision**
    - **Validates: Requirements 18.8, 44.6**

- [x] 20. Implement Invoicing and Accounts Receivable (ERP)
  - [x] 20.1 Create `invoices`, `invoice_lines`, `payments` table migrations
    - _Requirements: 19.1_
  - [x] 20.2 Implement `InvoiceService::finalize()`: generate PDF (mPDF), send via SMTP, post AR journal entry
    - Auto-number with configurable prefix; support partial payments; mark overdue when due date passes
    - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5_
  - [x] 20.3 Implement recurring invoice Celery task `RecurringInvoiceTask`
    - _Requirements: 19.6_
  - [x] 20.4 Implement Stripe integration: webhook handler at `/api/billing/stripe/webhook`
    - Process `invoice.payment_succeeded`; record payment and update status within 60s
    - _Requirements: 19.7, 19.8_

- [x] 21. Implement Expense Management and Accounts Payable
  - [x] 21.1 Create `expense_claims`, `purchase_orders`, `goods_receipts`, `supplier_invoices` table migrations
    - _Requirements: 20.1, 20.4_
  - [x] 21.2 Implement expense claim submission with configurable approval workflow by amount threshold
    - Post journal entry on approval
    - _Requirements: 20.1, 20.2, 20.3_
  - [x] 21.3 Implement three-way matching: PO + goods receipt + supplier invoice; flag discrepancy > 5%
    - _Requirements: 20.5, 20.6, 20.7_

- [x] 22. Implement Inventory and Warehouse Management
  - [x] 22.1 Create `inventory_items`, `warehouses`, `stock_ledger`, `inventory_stock` table migrations
    - _Requirements: 21.1, 21.2_
  - [x] 22.2 Implement `StockMovementService::record()`: update quantities, append immutable stock ledger entry
    - Support batch/serial number tracking; FIFO/LIFO/weighted average valuation
    - _Requirements: 21.3, 21.5, 21.7_
  - [x] 22.3 Implement reorder alert nightly task and optional auto-PO generation
    - _Requirements: 21.4_
  - [x] 22.4 Implement stock take: lock items for counting, compute variance on completion
    - _Requirements: 21.6_

- [x] 23. Implement Procurement Management
  - [x] 23.1 Create `purchase_requisitions`, `rfqs`, `supplier_catalog` table migrations
    - _Requirements: 22.1, 22.2_
  - [x] 23.2 Implement purchase requisition → approval → PO workflow
    - Track PO fulfillment status: pending, partially received, fully received, cancelled
    - _Requirements: 22.1, 22.3, 22.4_
  - [x] 23.3 Implement RFQ: issue to multiple suppliers, record and compare quotes
    - Generate procurement spend report by supplier, category, period
    - _Requirements: 22.5, 22.6_

- [x] 24. Implement HR and Employee Management
  - [x] 24.1 Create `employees`, `departments`, `leave_types`, `leave_requests`, `employee_documents` table migrations
    - _Requirements: 23.1_
  - [x] 24.2 Implement employee lifecycle management: onboarding, active, on leave, offboarding
    - Trigger configured workflow on status change
    - Org chart view derived from manager relationships
    - _Requirements: 23.2, 23.3, 23.4_
  - [x] 24.3 Implement leave management: configurable leave types, accrual rules, balance tracking, approval routing
    - _Requirements: 23.6, 23.7_
  - [x] 24.4 Implement employee document storage with versioning, access restricted to HR role and above
    - _Requirements: 23.5_

- [x] 25. Implement Payroll Processing (ERP)
  - [x] 25.1 Create `payroll_runs` and `payroll_lines` table migrations with all 28 allowance + 18 deduction components
    - _Requirements: 24.1_
  - [x] 25.2 Implement `PayrollRunService::compute()`: iterate active employees, apply salary components, deductions, tax tables; post journal entry
    - Flag and exclude negative net pay employees from finalization
    - Generate bilingual payslip PDF per employee via mPDF
    - _Requirements: 24.2, 24.3, 24.4, 24.6_
  - [x] 25.3 Implement payroll export in bank transfer file formats (CSV, NACHA, BACS)
    - _Requirements: 24.5_
  - [x] 25.4 Write property test for payroll negative net pay exclusion
    - **Property 25: Payroll Negative Net Pay Exclusion**
    - **Validates: Requirements 24.6, 52.13**

- [x] 26. Implement Project Management
  - [x] 26.1 Create `projects`, `project_tasks`, `milestones`, `time_logs` table migrations
    - _Requirements: 25.1_
  - [x] 26.2 Implement project task hierarchy (up to 3 levels), dependencies (finish-to-start, start-to-start), conflict detection
    - Gantt chart data endpoint; completion percentage from completed/total tasks ratio
    - _Requirements: 25.2, 25.3, 25.4, 25.7_
  - [x] 26.3 Implement milestone due-date notification and time tracking (actual vs. budgeted hours)
    - _Requirements: 25.5, 25.6_

- [x] 27. Implement Manufacturing and Bill of Materials
  - [x] 27.1 Create `boms`, `bom_lines`, `work_orders` table migrations
    - _Requirements: 26.1_
  - [x] 27.2 Implement multi-level BOM with component quantity computation and stock availability check
    - On insufficient stock: list shortfall per component, optionally generate POs
    - _Requirements: 26.1, 26.2, 26.3, 26.4_
  - [x] 27.3 Implement work order completion: consume components, add finished product to inventory, compute production cost
    - Track work order status: planned, in progress, completed, cancelled
    - _Requirements: 26.5, 26.6, 26.7_

- [x] 28. Checkpoint — Ensure all ERP tests pass
  - Ensure all tests pass, ask the user if questions arise.


---

### Phase 4: Accounting Module

- [x] 29. Implement COA Management (Batch A)
  - [x] 29.1 Create `chart_of_accounts` migration with 5-level hierarchy, blocked flag, allowed_currencies, allowed_companies
    - _Requirements: 45.1, 45.2, 45.3, 45.4, 45.5_
  - [x] 29.2 Implement `COAService` in `/modular_core/modules/Accounting/COA/`
    - Account balance viewer per account per Company_Code per Fin_Period
    - COA import/export from Excel preserving 5-level hierarchy
    - Account merge tool: transfer all journal lines, block source account
    - Account usage report by date range
    - Opening balance journal entries per account per Company_Code
    - _Requirements: 45.6, 45.7, 45.8, 45.9, 45.10_
  - [x] 29.3 Implement allocation accounts engine: on posting to allocation account, distribute to target accounts by configured ratios
    - _Requirements: 45.11_
  - [x] 29.4 Implement WIP stale check Celery task: flag WIP accounts with no movement > 90 days, notify Accountant
    - _Requirements: 45.12_

- [x] 30. Implement Journal Entry and Voucher Engine (Batch B)
  - [x] 30.1 Implement `VoucherService::save()` in `/modular_core/modules/Accounting/Vouchers/`
    - Validate period open, validate balance (Σ Dr = Σ Cr), auto-assign voucher/section codes, store all 35 fields
    - Validate: asset_no required when account is fixed asset; check_transfer_no required when account is bank
    - Word count auto-calculation: word_count × per_word_rate
    - Opening balance journal type bypassing balance check (Owner role only)
    - _Requirements: 46.1, 46.2, 46.3, 46.4, 46.5, 46.6, 46.10, 46.11, 46.12, 46.20_
  - [x] 30.2 Implement voucher approval state machine: Draft → Submitted → Approved → Posted → Reversed
    - Reversal: create equal-and-opposite voucher linked via `reversed_by_voucher_id`
    - Voucher copy: create new Draft pre-populated with same lines
    - _Requirements: 46.13, 46.14, 46.15_
  - [x] 30.3 Implement FX rate auto-fill from Redis cache `fx:rate:{currency_code}:{date}` with user override
    - Display transaction-currency amount and EGP equivalent on every line
    - Vendor/client lookup typeahead on vendor_code/vendor_name
    - _Requirements: 46.7, 46.8, 46.9_
  - [x] 30.4 Implement bulk voucher import from Excel: validate all 35 fields, report row-level errors before commit
    - _Requirements: 46.16_
  - [x] 30.5 Implement voucher search interface filterable by Company_Code, Fin_Period, Voucher_Code, Section_Code, account_code, vendor_code, date range
    - _Requirements: 46.17_
  - [x] 30.6 Implement bilingual PDF generation for posted vouchers (mPDF, Arabic RTL + English LTR, company letterhead)
    - _Requirements: 46.18, 58.6_
  - [x] 30.7 Implement Settlement_Voucher engine: enforce Voucher_Code 999 with Section_Codes 991–996, calculate net settlement amount
    - _Requirements: 47.10_
  - [x] 30.8 Implement multi-currency trial balance showing debit/credit/net in transaction currency and EGP per Company_Code per Fin_Period
    - _Requirements: 47.11_

- [x] 31. Implement Multi-Currency and Exchange Rate Engine (Batch C)
  - [x] 31.1 Create `exchange_rates` table migration; implement currency master UI with 6 currencies
    - _Requirements: 47.1, 47.2_
  - [x] 31.2 Implement `FXService` in `/modular_core/modules/Accounting/FX/`
    - `getRateForDate(currencyCode, date)`: Redis-first (`fx:rate:{currency_code}:{date}`, TTL 24h), fall back to DB
    - Rate source toggle: manual entry OR auto-fetch from Central Bank of Egypt API
    - Exchange rate history viewer (line chart)
    - _Requirements: 47.3, 47.4, 47.5_
  - [x] 31.3 Implement `FXService::computeRealizedGainLoss()`: post gain/loss to configured FX account on settlement
    - _Requirements: 47.6_
  - [x] 31.4 Implement `FXRevaluationTask` (Celery): period-close unrealized revaluation + auto-reversal on first day of next period
    - _Requirements: 47.7, 47.8_
  - [x] 31.5 Implement currency translation report (foreign currency → EGP using closing rate method)
    - _Requirements: 47.9_
  - [x] 31.6 Write property test for realized FX gain/loss calculation
    - **Property 23: Realized FX Gain/Loss Calculation**
    - **Validates: Requirements 47.6**
  - [x] 31.7 Write property test for FX revaluation round trip
    - **Property 24: FX Revaluation Round Trip**
    - **Validates: Requirements 47.7, 47.8**

- [x] 32. Implement Accounts Receivable and Payable (Batch D)
  - [x] 32.1 Create `ar_invoices`, `ap_bills`, `payments`, `payment_allocations`, `accruals` table migrations
    - _Requirements: 48.1, 48.2_
  - [x] 32.2 Implement AR invoice creation and AP bill creation with payment matching (partial and full allocation)
    - Post journal entries on customer receipt (debit bank, credit AR) and vendor disbursement (debit AP, credit bank)
    - _Requirements: 48.1, 48.2, 48.3, 48.4, 48.5_
  - [x] 32.3 Implement AR and AP aging reports: 0–30, 31–60, 61–90, 91–120, 120+ day buckets per vendor per currency per Company_Code
    - _Requirements: 48.6, 48.7_
  - [x] 32.4 Implement withholding tax auto-deduction on vendor payment disbursement
    - _Requirements: 48.8_
  - [x] 32.5 Implement retention tracking per contract; release on milestone completion
    - _Requirements: 48.9_
  - [x] 32.6 Implement accruals module: auto-reverse accrual entry at start of next Fin_Period
    - _Requirements: 48.10_
  - [x] 32.7 Implement sister-company intercompany reconciliation view (net AR/AP between each pair of 6 companies)
    - _Requirements: 48.11_
  - [x] 32.8 Implement partner dues and withdrawals tracking per Partner_Code per Company_Code
    - _Requirements: 48.12_
  - [x] 32.9 Implement customer statement (all transactions, payments, running balance per vendor per Fin_Period)
    - _Requirements: 48.13_
  - [x] 32.10 Implement overdue AR alert Celery task; implement ETA e-invoice formatting, signing, and submission for Company 01
    - _Requirements: 48.14, 48.15_

- [x] 33. Implement Bank and Cash Management (Batch E)
  - [x] 33.1 Create `bank_accounts`, `petty_cash_funds`, `cash_calls`, `bank_transactions` table migrations
    - _Requirements: 49.1_
  - [x] 33.2 Implement bank account master covering all configured accounts (SAIB LE/EGP, PAYPALL USD, AMARAT AED, QNB E-WALLET, INSTAPAY NBE)
    - Petty cash fund management: issue, replenish, reconcile
    - Cash call management: track calls per Company_Code, record receipts
    - Cash in transit tracking for inter-bank transfers
    - _Requirements: 49.1, 49.2, 49.3, 49.4_
  - [x] 33.3 Implement bank statement CSV import with voucher matching and unmatched item highlighting
    - Bank reconciliation UI: book balance, bank balance, outstanding deposits/payments, reconciling difference
    - Auto-post bank charges journal entry on import
    - _Requirements: 49.5, 49.6, 49.7_
  - [x] 33.4 Implement PAYPALL USD balance with EGP equivalent; mobile wallet tracker for QNB E-WALLET and INSTAPAY
    - Cash position dashboard: all bank/cash balances per currency with total EGP equivalent
    - _Requirements: 49.8, 49.9, 49.10_
  - [x] 33.5 Implement cash flow forecast (30/60/90 days) from open AR/AP per Company_Code
    - Month-end bank interest accrual calculation and journal entry posting
    - _Requirements: 49.11, 49.12_

- [x] 34. Implement Cost Centers and Project Accounting (Batch F)
  - [x] 34.1 Create `cost_centers`, `cost_center_budgets`, `afes` table migrations
    - _Requirements: 50.1, 50.2_
  - [x] 34.2 Implement cost center master with full hierarchy linked to Company_Code
    - Annual budgets per cost center per expense account; budget vs. actual report with drill-down
    - _Requirements: 50.1, 50.2, 50.3_
  - [x] 34.3 Implement cost allocation engine: distribute indirect expenses from allocation account to target cost centers by configured ratios
    - _Requirements: 50.4_
  - [x] 34.4 Implement WIP tracking per AFE number: WIP EXP, WIP DEV, WIP CONSTR accounts
    - AFE management: create AFE with approved budget, track actual spend, alert at 90% budget
    - AFE closing workflow: transfer WIP balance to capitalized asset or expense account
    - _Requirements: 50.5, 50.6, 50.7, 50.8, 50.9_
  - [x] 34.5 Implement department time allocation: distribute payroll costs across cost centers on payroll journal post
    - Dry hole transfer: move WIP EXP balance to Dry Hole Expenses account
    - Production expense report by cost center, account, Fin_Period
    - _Requirements: 50.10, 50.11, 50.12_

- [x] 35. Implement Fixed Assets (Batch G)
  - [x] 35.1 Create `fixed_assets` table migration with all asset categories and depreciation fields
    - _Requirements: 51.1_
  - [x] 35.2 Implement asset acquisition linking to tangible cost COA account
    - Depreciation setup per category: straight-line or declining balance, useful life, salvage value
    - _Requirements: 51.2, 51.3_
  - [x] 35.3 Implement monthly depreciation Celery task: calculate and post depreciation journal entries for all active assets per Company_Code
    - _Requirements: 51.4_
  - [x] 35.4 Implement asset disposal: calculate gain/loss vs. net book value, post disposal journal entry to RETIRED ASSETS & EQUIPMENT and ASSETS CLEARING ACCOUNT, mark retired
    - Salvage material gain/loss posting
    - _Requirements: 51.5, 51.10_
  - [x] 35.5 Implement asset revaluation: post revaluation difference to equity revaluation reserve
    - Asset overhaul/CAPEX: capitalize or expense based on configurable threshold
    - _Requirements: 51.6, 51.7_
  - [x] 35.6 Implement asset register report (cost, accumulated depreciation, net book value per asset per Company_Code)
    - Asset movement report (inter-company transfers)
    - _Requirements: 51.8, 51.9_

- [x] 36. Implement Payroll and Salary Module (Batch H)
  - [x] 36.1 Create `payroll_runs` and `payroll_lines` table migrations with all 28 allowance + 18 deduction components per design schema
    - _Requirements: 52.1, 52.2_
  - [x] 36.2 Implement `PayrollRunService::compute()` for Accounting module
    - Compute gross pay, all deductions, net pay for every active employee per Company_Code per Fin_Period
    - Post journal entries to COA automatically; flag and exclude negative net pay
    - _Requirements: 52.3, 52.4, 52.13_
  - [x] 36.3 Implement bilingual payslip PDF (Arabic + English) per employee per Payroll_Run
    - ATM salary payment file in configurable bank transfer format
    - _Requirements: 52.5, 52.6_
  - [x] 36.4 Implement on-loan employee tracking: post salary cost to Loanes Sal. From Other / To Other / Onloan Epsco accounts
    - Board member compensation run with separate journal entry
    - _Requirements: 52.7, 52.8_
  - [x] 36.5 Implement social insurance report (employer + employee share per employee per month)
    - Payroll cost distribution across cost centers by time allocation percentages
    - EOS bonus calculation and provision journal entry
    - Translator payment: translator_word_count × per-word rate as payroll line
    - _Requirements: 52.9, 52.10, 52.11, 52.12_

- [x] 37. Implement Partner Profit Distribution (Batch I)
  - [x] 37.1 Create `partners` table migration with `share_pct` and `withdrawal_approval_threshold`
    - _Requirements: 53.1_
  - [x] 37.2 Implement partner distribution on Fin_Period close: aggregate net income, calculate each partner's share, post distribution journal entry (debit ANNUAL PROFIT, credit PARTNER DUES)
    - _Requirements: 53.1, 53.2_
  - [x] 37.3 Implement partner withdrawal: post journal entry (debit PARTNER DUES, credit bank); require dual approval when amount exceeds threshold
    - Track partner capital in SHARE CAPITAL account per Company_Code
    - _Requirements: 53.3, 53.4, 53.7_
  - [x] 37.4 Implement partner account statement (dues, withdrawals, running balance per Partner_Code per Company_Code per Fin_Period)
    - Multi-company partner roll-up view across all 6 companies
    - Partner profit forecast based on projected net income
    - _Requirements: 53.5, 53.6, 53.8_
  - [x] 37.5 Write property test for partner profit distribution calculation
    - **Property 26: Partner Profit Distribution Calculation**
    - **Validates: Requirements 53.1**

- [x] 38. Implement Financial Statements (Batch J)
  - [x] 38.1 Implement Trial Balance per Company_Code per Fin_Period (debit, credit, net per account), exportable to Excel
    - _Requirements: 54.1_
  - [x] 38.2 Implement auto-generated P&L Statement (Income − Cost − Expenses per Company_Code per Fin_Period)
    - Balance Sheet verifying Assets = Liabilities + Equity with comparative prior-period columns
    - _Requirements: 54.2, 54.3_
  - [x] 38.3 Implement Cash Flow Statement (direct method from bank account movements)
    - Consolidated financial statements aggregating all 6 companies with inter-company elimination
    - _Requirements: 54.4, 54.5_
  - [x] 38.4 Implement department P&L by cost center using time-allocation entries
    - Currency-translated financial statements (closing rate method)
    - Comparative period report (current vs. prior-year, variance amount and %)
    - _Requirements: 54.6, 54.7, 54.8_
  - [x] 38.5 Implement financial period close checklist: reconcile AR/AP, revalue FX, post depreciation, allocate indirect expenses, post partner profit — each step with completion status
    - Immutable audit trail report (user, timestamp, IP, Company_Code, Fin_Period), exportable as PDF
    - _Requirements: 54.9, 54.10_

- [x] 39. Implement Tax and Compliance (Batch K)
  - [x] 39.1 Implement withholding tax ledger with monthly reconciliation report
    - Monthly income tax provision calculation and journal entry posting
    - _Requirements: 55.1, 55.2_
  - [x] 39.2 Implement social insurance Form 2 report per employee per Company_Code
    - VAT tagging on invoices and VAT report per Fin_Period
    - _Requirements: 55.3, 55.4_
  - [x] 39.3 Implement ETA e-invoice: format as JSON per ETA schema, sign payload, submit to ETA API, store submission status and response (Company 01 only)
    - _Requirements: 55.5_
  - [x] 39.4 Implement tax card and commercial registry expiry alerts (90/60/30 days) to Owner and Admin
    - Tax filing calendar with Egyptian VAT, withholding tax, social insurance monthly due dates
    - Annual tax return summary per Company_Code
    - _Requirements: 55.6, 55.7, 55.8, 55.9_

- [x] 40. Implement Accounting Reporting and Analytics Dashboard (Batch L)
  - [x] 40.1 Implement accounting home dashboard: cash position, AR/AP aging summary, pending approval count, period close checklist progress
    - _Requirements: 56.1_
  - [x] 40.2 Implement voucher volume bar chart, currency exposure donut chart, partner equity dashboard
    - Cost center spend heatmap (actual vs. budget, color-coded by variance %)
    - _Requirements: 56.2, 56.3, 56.4, 56.5_
  - [x] 40.3 Implement top expense accounts ranked bar chart, inter-company balances monitor (sum must equal zero), cash call tracker widget, WIP account balances widget
    - _Requirements: 56.6, 56.7, 56.8, 56.9_
  - [x] 40.4 Implement custom report builder: select journal entry line fields, group-by, filter, sort; export as CSV, Excel, PDF
    - Scheduled reports: auto-generate and email Trial Balance, P&L, Cash Position at month-end
    - _Requirements: 56.10, 56.11_

- [x] 41. Implement Platform-Wide Accounting Features (Batch N)
  - [x] 41.1 Implement company switcher in top navigation bar; re-scope all accounting queries on company change
    - _Requirements: 58.1_
  - [x] 41.2 Implement financial period manager: open, close, lock YYYYMM periods per Company_Code; prevent backdated posting to locked period
    - _Requirements: 58.2_
  - [x] 41.3 Implement multi-company journal entries: intercompany flag posts lines to each involved Company_Code atomically
    - _Requirements: 58.3_
  - [x] 41.4 Implement accounting audit log UI filterable by User, Company_Code, account_code, Fin_Period, action type; immutable and exportable
    - _Requirements: 58.4_
  - [x] 41.5 Enforce accounting RBAC permissions: accounting.voucher.create, accounting.voucher.approve, accounting.voucher.reverse, accounting.period.close, accounting.statements.view, accounting.payroll.run, accounting.partner.distribute
    - _Requirements: 58.5_
  - [x] 41.6 Implement bilingual PDF engine for all accounting reports and vouchers (Arabic RTL + English LTR sections)
    - Format monetary amounts as #,##0.00 with currency symbol; Arabic-Indic numerals for Arabic locale
    - _Requirements: 58.6, 58.7_
  - [x] 41.7 Implement full accounting data export per Company_Code per fiscal year as Excel workbook (one sheet per module)
    - _Requirements: 58.8_

- [x] 42. Checkpoint — Ensure all Accounting tests pass
  - Ensure all tests pass, ask the user if questions arise.


---

### Phase 5: Platform Core Services

- [x] 43. Implement WebSocket Notification Server
  - [x] 43.1 Implement Ratchet/Swoole WebSocket server with per-user authenticated channels
    - Channel: `tenant:{tenant_id}:user:{user_id}`
    - Store undelivered notifications in Redis list `notifications:pending:{user_id}`; flush on reconnect
    - _Requirements: 27.1, 27.3_
  - [x] 43.2 Implement `notifications` table migration and 90-day TTL nightly Celery cleanup task
    - Push notification within 3s of relevant platform event
    - Mark notifications read individually or in bulk
    - _Requirements: 27.2, 27.4, 27.5_
  - [x] 43.3 Implement `useNotifications()` React hook maintaining WS connection and dispatching to notification store
    - _Requirements: 27.1, 27.2_

- [x] 44. Implement SaaS Subscription Billing via Stripe
  - [x] 44.1 Implement Stripe Billing integration: create Stripe Customer on tenant signup, attach payment method
    - _Requirements: 28.1, 28.2_
  - [x] 44.2 Implement Stripe webhook handler: update plan status to active within 60s on `invoice.payment_succeeded`
    - Notify Owner and restrict paid features after 7-day grace period on payment failure
    - _Requirements: 28.3, 28.4_
  - [x] 44.3 Implement subscription management UI: upgrade, downgrade, cancel plan
    - Enforce per-plan feature flags and seat limits; prevent new user creation when seat limit exceeded
    - _Requirements: 28.5, 28.6, 28.7_

- [x] 45. Implement Global Search
  - [x] 45.1 Implement `GlobalSearchService` combining PostgreSQL full-text search and pgvector semantic search
    - Return results within 500ms for Contacts, Leads, Deals, Accounts, Invoices, Projects
    - Scope results to current tenant_id and user RBAC permissions
    - Deduplicate and group results by record type with navigation links
    - _Requirements: 29.1, 29.2, 29.3, 29.4, 29.5_
  - [x] 45.2 Implement `GET /api/v1/platform/search` endpoint and global search bar React component
    - _Requirements: 29.1, 29.2_

- [x] 46. Implement Audit Logging
  - [x] 46.1 Implement append-only `audit_log` table with PostgreSQL row-level security preventing UPDATE/DELETE
    - Record every create, update, delete, permission change with: timestamp, tenant_id, user_id, operation, table, record_id, prev_values, new_values, ip_address
    - _Requirements: 30.1, 30.2, 30.3_
  - [x] 46.2 Implement audit log search UI filterable by user, date range, record type, operation type; return within 5s for 10M entries
    - _Requirements: 30.4, 30.5_

- [x] 47. Implement Webhook Management
  - [x] 47.1 Create `webhooks` and `webhook_deliveries` table migrations
    - _Requirements: 31.1_
  - [x] 47.2 Implement webhook registration, HMAC-SHA256 payload signing, and HTTP POST delivery within 10s
    - Retry up to 5 times with exponential backoff over 24 hours on failure
    - Record delivery attempts with status, response code, response body for 30 days
    - _Requirements: 31.1, 31.2, 31.3, 31.4, 31.5_

- [x] 48. Implement Internationalization (i18n)
  - [x] 48.1 Implement `react-i18next` with locale files per module; RTL layout via `dir="rtl"` on `<html>` for Arabic
    - Externalize all user-facing strings; fall back to English on missing translation
    - _Requirements: 32.1, 32.2, 32.3, 32.4, 32.5_
  - [x] 48.2 Implement Smarty bilingual templates for PHP-rendered views (Arabic RTL + English LTR)
    - _Requirements: 32.4, 58.6_

- [x] 49. Checkpoint — Ensure all Platform Core tests pass
  - Ensure all tests pass, ask the user if questions arise.


---

### Phase 6: AI Engine

- [x] 50. Implement AI Engine FastAPI application structure
  - [x] 50.1 Set up FastAPI app in `/ai_engine/app/` with standard response schema `{ result, confidence, model_version }`
    - Validate `tenant_id` on every request; return HTTP 400 on missing/invalid tenant_id
    - Process prediction requests within 2s at p95; version all deployed models
    - _Requirements: 35.1, 35.2, 35.3, 35.4, 35.5_
  - [x] 50.2 Set up Celery workers in `/ai_engine/workers/` consuming RabbitMQ queues
    - _Requirements: 35.1_

- [x] 51. Implement Churn Prediction
  - [x] 51.1 Implement `ChurnPredictionService` (survival analysis on engagement, recency, support volume, contract renewal proximity)
    - FastAPI endpoint `POST /predict/churn`; persist Churn_Score + `churn_score_updated_at` on Account
    - Display risk tier: low (0–33), medium (34–66), high (67–100)
    - _Requirements: 36.1, 36.2, 36.3_
  - [x] 51.2 Implement high-churn task creation for Account owner and daily recompute Celery task for all Accounts
    - _Requirements: 36.4, 36.5_

- [x] 52. Implement NLP and Sentiment Analysis
  - [x] 52.1 Implement `SentimentService` (multilingual BERT for Arabic + English)
    - FastAPI endpoint `POST /predict/sentiment`; store sentiment label + confidence on inbox_messages
    - _Requirements: 37.1, 37.2_
  - [x] 52.2 Implement negative sentiment flagging (confidence > 0.75) for supervisor review
    - Display sentiment indicators on conversation list; sentiment trend report per Account
    - _Requirements: 37.3, 37.4, 37.5_

- [x] 53. Implement AI-Powered Semantic Search with Embeddings
  - [x] 53.1 Create `record_embeddings` table migration with pgvector extension and ivfflat index
    - _Requirements: 38.1, 38.2_
  - [x] 53.2 Implement `EmbeddingService` (sentence-transformers) generating 768-dim vectors per record
    - FastAPI endpoints `POST /embed/record` and `POST /search/semantic`
    - Update embedding within 30s of record create/update; return top 20 semantically similar records within 500ms
    - _Requirements: 38.1, 38.3, 38.5_
  - [x] 53.3 Combine semantic + keyword search results, deduplicate before returning
    - _Requirements: 38.4_

- [x] 54. Implement AI Email and Response Suggestions
  - [x] 54.1 Implement FastAPI endpoint `POST /predict/email-suggestions`
    - Generate up to 3 reply drafts based on email content and conversation history
    - Personalize using linked Contact name and recent interaction history
    - _Requirements: 39.1, 39.4_
  - [x] 54.2 Implement suggestion display in reply composer; log original + edited text for model improvement on edit
    - _Requirements: 39.2, 39.3, 39.5_

- [x] 55. Implement Revenue Forecasting and Intelligence
  - [x] 55.1 Implement `ForecastService` (ARIMA/Prophet) for monthly revenue forecast (next 3 months) with confidence intervals
    - FastAPI endpoints `POST /predict/revenue-forecast` and `POST /predict/cash-flow-forecast`
    - _Requirements: 40.1, 40.3_
  - [x] 55.2 Display AI forecast alongside weighted pipeline forecast on revenue dashboard
    - Daily refresh via Celery queue; track forecast accuracy on month close
    - _Requirements: 40.2, 40.4, 40.5_

- [x] 56. Implement AI Accounting Features (Batch M)
  - [x] 56.1 Implement exchange rate deviation check: compare entered rate to Redis-cached market rate; flag if deviation exceeds threshold
    - FastAPI endpoint `POST /accounting/anomaly-detect`
    - _Requirements: 57.1_
  - [x] 56.2 Implement duplicate voucher check: search posted vouchers by vendor + amount + date ±3 days; warn Accountant
    - FastAPI endpoint `POST /accounting/duplicate-check`
    - _Requirements: 57.2_
  - [x] 56.3 Implement account code suggestion from cost_identifier free-text using text similarity (top 3 COA matches)
    - FastAPI endpoint `POST /accounting/account-suggest`
    - _Requirements: 57.3_
  - [x] 56.4 Implement WIP stale balance AI flagging in monthly Celery task; notify Accountant
    - _Requirements: 57.4_
  - [x] 56.5 Implement cash flow prediction (next 3 months per Company_Code per currency) displayed on cash position dashboard
    - Translation revenue forecast: vendor_word_count × per-word rate aggregated by Fin_Period
    - _Requirements: 57.5, 57.6_
  - [x] 56.6 Implement journal entry line amount outlier detection: compare Dr/Cr to historical entries on same account; flag statistical outliers
    - _Requirements: 57.7_

- [x] 57. Checkpoint — Ensure all AI Engine tests pass
  - Ensure all tests pass, ask the user if questions arise.


---

### Phase 7: Cross-Cutting Concerns and Integration

- [x] 58. Implement Performance, Caching, and Scalability
  - [x] 58.1 Implement Redis caching for permissions, tenant config, and lookup tables with appropriate TTLs
    - Fall back to DB reads when Redis unavailable; log cache unavailability warning
    - _Requirements: 41.3, 41.4_
  - [x] 58.2 Implement database connection pooling (min 10, max 100 connections per app instance)
    - _Requirements: 41.5_
  - [x] 58.3 Write Kubernetes HPA manifests for PHP backend and AI Engine pod autoscaling
    - _Requirements: 41.2_

- [x] 59. Implement Security Hardening
  - [x] 59.1 Implement input sanitization middleware for SQL injection and XSS prevention on all endpoints
    - CSRF protection on all state-changing HTTP endpoints
    - Enforce HTTPS via HSTS header
    - _Requirements: 42.2, 42.3, 42.4_
  - [x] 59.2 Implement request body JSON Schema validation returning HTTP 422 with field-level error map on failure
    - Implement Zod schema validation on React frontend before submission
    - _Requirements: 44.2, 44.3_

- [x] 60. Implement API Serializer Integrity
  - [x] 60.1 Implement consistent UTF-8 JSON serialization with consistent key ordering and no trailing whitespace
    - Represent monetary amounts as strings or fixed-point decimals in JSON (never float)
    - _Requirements: 44.1, 44.4, 44.6_
  - [x] 60.2 Write property test for API serialization round trip
    - **Property 22: API Serialization Round Trip**
    - **Validates: Requirements 44.5**
  - [x] 60.3 Write property test for standard table schema invariant
    - **Property 3: Standard Table Schema Invariant**
    - **Validates: Requirements 1.1, 1.2**

- [x] 61. Implement Data Export and Portability
  - [x] 61.1 Implement full tenant data export (JSON + CSV) via async Celery job; notify Owner when ready
    - Complete within 4 hours for up to 1M records; download available for 7 days
    - _Requirements: 43.1, 43.2, 43.3, 43.4_
  - [x] 61.2 Implement right-to-erasure: permanently remove all tenant records and revoke all active sessions within 24 hours
    - _Requirements: 43.5_

- [x] 62. Implement React Frontend Core
  - [x] 62.1 Set up React 18/Vite project in `/frontend/src/` with module structure: `components/`, `modules/`, `hooks/`, `lib/`
    - Implement `PermissionGate` component wrapping UI elements based on resolved permissions
    - _Requirements: 2.4, 2.5_
  - [x] 62.2 Implement React Query hooks per module (`useContacts`, `useDeals`, `useVouchers`, etc.)
    - Implement typed API client functions with standard envelope parsing
    - _Requirements: 3.1, 3.2, 3.3_
  - [x] 62.3 Implement SOAP legacy endpoints in `/soap/` and `/webservice.php` enforcing tenant_id and RBAC identically to REST
    - _Requirements: 5.1_

- [x] 63. Final Integration and Wiring
  - [x] 63.1 Wire all RabbitMQ event bus integrations per the integration patterns table
    - Events: `lead.captured`, `lead.score_request`, `deal.win_probability_request`, `inbox.message.received`, `workflow.execute`, `webhook.deliver`, `email.sync`, `payroll.run`, `fx.revalue`, `depreciation.run`, `embedding.update`
    - _Requirements: 8.1, 11.1, 12.3, 14.4, 31.2, 36.4, 47.7, 51.4, 38.5_
  - [x] 63.2 Wire all Celery scheduled tasks: deal rotting, overdue check, FX rate refresh, FX revaluation, depreciation, payroll, embedding updates, notification cleanup, churn recompute, forecast refresh, WIP stale check, recurring invoices, AR overdue alerts
    - _Requirements: 10.8, 19.6, 36.5, 47.5, 47.7, 51.4, 38.5, 27.5, 40.4_
  - [x] 63.3 Wire AI Engine PHP → Python internal API calls for all prediction endpoints
    - Implement dead-letter queue fallback: return cached last-known score when AI Engine unavailable
    - _Requirements: 35.1, 35.4_

- [x] 64. Final Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

---

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP delivery
- Each task references specific requirements for traceability
- Checkpoints at the end of each phase ensure incremental validation
- Property tests validate universal correctness invariants; unit tests validate specific examples and edge cases
- All monetary amounts must be DECIMAL(15,2) in DB and string/fixed-point in JSON — never float
- All queries must include explicit `tenant_id` and `company_code` filters; BaseModel enforces this automatically
- Bilingual output (Arabic RTL + English LTR) is required on all PDFs and Smarty templates
- The AI Engine is an internal microservice; PHP backend communicates via RabbitMQ and direct HTTP to FastAPI
