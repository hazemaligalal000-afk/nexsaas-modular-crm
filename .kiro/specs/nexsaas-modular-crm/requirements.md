# Requirements Document

## Introduction

NexSaaS is an enterprise-grade, multi-tenant AI Revenue Operating System combining a full CRM, ERP, and Accounting platform.
It is built on a modular PHP 8.3 MVC backend, a Python FastAPI AI microservice, a React 18/Vite frontend,
PostgreSQL 16, Redis 7, and RabbitMQ/Celery for async jobs. The platform supports RBAC roles
(Owner, Admin, Manager, Agent, Support, Accountant, Reviewer), strict multi-tenancy on every data layer, and a pluggable
module architecture. The Accounting module supports 6 companies under a single tenant (company codes 01–06),
6 currencies (EGP, USD, AED, SAR, EUR, GBP), a 35-field double-entry journal entry engine, multi-currency
exchange rate management, full AR/AP lifecycle, bank and cash management, cost center and project accounting,
fixed assets, payroll, partner profit distribution, financial statements, tax and compliance, and AI-powered
accounting analytics. This document covers all feature batches: CRM (Batches 1–6), ERP (Batches 7–12),
Platform Core (Batch 13), AI Engine (Batch 14), and Accounting (Batches A–M plus platform-wide accounting features).

---

## Glossary

- **System**: The NexSaaS platform as a whole.
- **Platform**: The NexSaaS application runtime including all modules.
- **Tenant**: An isolated organizational account sharing the platform infrastructure.
- **User**: An authenticated human actor operating within a Tenant.
- **Owner**: The highest-privilege RBAC role; one per Tenant.
- **Admin**: A Tenant-scoped administrator role below Owner.
- **Manager**: A role with read/write access to assigned modules.
- **Agent**: A role with limited write access focused on CRM operations.
- **Support**: A read-mostly role for customer-facing operations.
- **Module**: A self-contained feature unit under /modular_core/modules/[ModuleName]/.
- **Contact**: A person record stored in the CRM.
- **Lead**: An unqualified prospect record in the CRM.
- **Deal**: A qualified sales opportunity linked to a Contact or Account.
- **Account**: A company or organization record in the CRM.
- **Pipeline**: An ordered set of Stages through which Deals progress.
- **Stage**: A named step within a Pipeline.
- **Activity**: A logged interaction such as a call, email, meeting, or task.
- **Workflow**: An automated sequence of Actions triggered by Events or Conditions.
- **Trigger**: An Event or Condition that initiates a Workflow.
- **Action**: An automated operation executed by a Workflow step.
- **Inbox**: The unified omnichannel communication hub.
- **Channel**: A communication medium (email, SMS, WhatsApp, live chat, VoIP).
- **Invoice**: A financial document requesting payment from a customer.
- **Purchase_Order**: A financial document authorizing procurement from a supplier.
- **Inventory_Item**: A stockable product or raw material tracked in the warehouse.
- **Warehouse**: A physical or virtual storage location for Inventory_Items.
- **Employee**: A human resource record linked to a User or standalone HR record.
- **Payroll_Run**: A batch computation of Employee compensation for a pay period.
- **Project**: A time-bounded initiative composed of Tasks and Milestones.
- **Milestone**: A significant checkpoint within a Project.
- **BOM**: Bill of Materials — a structured list of components for a manufactured product.
- **Work_Order**: A manufacturing instruction to produce a quantity of a product.
- **AI_Engine**: The Python FastAPI microservice providing ML-powered predictions and NLP.
- **Lead_Score**: A numeric value (0–100) representing a Lead's conversion likelihood.
- **Churn_Score**: A numeric value (0–100) representing a customer's churn likelihood.
- **Win_Probability**: A numeric value (0–100) representing a Deal's likelihood of closing won.
- **Embedding**: A vector representation of text used for semantic search.
- **Redis_Cache**: The Redis 7 instance used for permissions, sessions, and queues.
- **Queue**: A RabbitMQ message queue consumed by Celery workers.
- **Tenant_ID**: The unique identifier scoping all data to a single Tenant.
- **RBAC**: Role-Based Access Control governing feature and data access.
- **Permission**: A module.action string controlling access to a specific operation.
- **JWT**: JSON Web Token used for stateless authentication.
- **2FA**: Two-Factor Authentication adding a second verification step at login.
- **SSO**: Single Sign-On allowing authentication via an external identity provider.
- **Webhook**: An HTTP callback delivering event notifications to external systems.
- **API_Response**: The standard envelope: { success, data, error, meta: { tenant_id, user_id, timestamp } }.
- **Soft_Delete**: Marking a record deleted_at rather than physically removing it.
- **Audit_Log**: An immutable record of who changed what and when.
- **i18n**: Internationalization support for multiple languages and locales.
- **WebSocket**: A persistent bidirectional connection for real-time UI updates.
- **Stripe**: The payment processor used for SaaS subscription billing.
- **Global_Search**: A cross-module full-text and semantic search capability.
- **Company_Code**: A two-digit identifier (01–06) scoping every financial record to one of the six companies under the tenant.
- **Fin_Period**: A six-digit financial period in YYYYMM format used to group journal entries by month.
- **Voucher_Code**: A numeric code (1–6 for currency-specific income/expense, 999 for settlements) identifying the voucher type.
- **Section_Code**: A two-digit code (01 = Income, 02 = Expense, 991–996 = Settlement by currency) classifying a voucher line.
- **Journal_Entry**: A double-entry accounting record composed of one or more debit and credit lines that balance to zero.
- **COA**: Chart of Accounts — the hierarchical list of all ledger accounts used by a company.
- **Cost_Center**: An organizational unit to which costs and revenues are allocated for management reporting.
- **AFE**: Authority for Expenditure — an approved budget authorization for a capital or exploration project.
- **WIP**: Work in Progress — an asset account accumulating costs for incomplete projects or assets not yet placed in service.
- **Partner_Code**: A unique identifier for a company partner (e.g., PG01, PG02) used to track equity, dues, and withdrawals.
- **Exchange_Rate**: The rate stored as DECIMAL(10,6) used to convert a foreign currency amount to the base currency (EGP).
- **Voucher_Sub**: A sub-number within a voucher grouping related lines of a single transaction.
- **Settlement_Voucher**: A Voucher_Code 999 entry used exclusively for inter-currency settlement; section codes 991–996 only.
- **Payslip**: A PDF document issued to an employee detailing gross pay, deductions, and net pay for a pay period.
- **ETA**: Egyptian Tax Authority — the government body responsible for VAT, withholding tax, and e-invoice compliance.
- **E-Invoice**: An electronic invoice submitted to the ETA API in the prescribed JSON schema for Company 01.
- **Realized_FX_Gain_Loss**: The exchange gain or loss recognized when a foreign-currency transaction is settled.
- **Unrealized_FX_Revaluation**: The period-end adjustment to restate open foreign-currency balances at the closing exchange rate.
- **Aging_Report**: A report grouping outstanding AR or AP balances into time buckets (0–30, 31–60, 61–90, 91–120, 120+ days).
- **Three_Way_Match**: The validation that a supplier invoice matches both the Purchase_Order and the goods receipt within tolerance.
- **Accountant**: An RBAC role within the Accounting module with permissions to create, edit, and submit vouchers and run payroll.
- **Reviewer**: An RBAC role within the Accounting module with permissions to approve vouchers and view financial statements.


---

## Requirements

---

### Requirement 1: Multi-Tenancy Data Isolation

**User Story:** As an Owner, I want all platform data strictly isolated per Tenant, so that no Tenant can access another Tenant's data.

#### Acceptance Criteria

1. THE System SHALL include a tenant_id column in every database table.
2. THE System SHALL include id, tenant_id, created_by, created_at, updated_at, and deleted_at columns in every new table.
3. WHEN a database query is executed, THE System SHALL automatically scope the query to the current Tenant_ID.
4. IF a query is constructed without a Tenant_ID, THEN THE System SHALL reject the query and return an error.
5. WHEN a record is deleted, THE System SHALL set deleted_at to the current timestamp rather than removing the row (Soft_Delete).
6. WHEN a query retrieves records, THE System SHALL exclude rows where deleted_at is not null unless explicitly requested.

---

### Requirement 2: Role-Based Access Control (RBAC)

**User Story:** As an Admin, I want fine-grained permission control per role, so that Users only access features appropriate to their role.

#### Acceptance Criteria

1. THE System SHALL enforce five platform-wide roles in descending privilege order: Owner, Admin, Manager, Agent, Support.
2. THE Accounting module SHALL enforce five accounting-specific roles in descending privilege order: Owner, Admin, Accountant, Reviewer, Viewer; these roles apply within the Accounting module in addition to the platform-wide roles.
3. THE System SHALL represent every controllable operation as a Permission string in the format module.action.
4. WHEN a User requests an operation, THE System SHALL verify the User's role holds the required Permission before executing the operation.
5. IF a User lacks the required Permission, THEN THE System SHALL return HTTP 403 with a descriptive error message.
6. THE System SHALL store resolved Permission sets per User in Redis_Cache with a TTL of 300 seconds.
7. WHEN a User's role or permissions change, THE System SHALL invalidate that User's Redis_Cache Permission entry within 5 seconds.
8. THE System SHALL provide an API endpoint allowing Admins to assign and revoke Permissions per role per Module.

---

### Requirement 3: Standard API Response Envelope

**User Story:** As a frontend developer, I want all REST API responses in a consistent format, so that I can handle success and error cases uniformly.

#### Acceptance Criteria

1. THE System SHALL return every REST API response as an API_Response object containing success (boolean), data (mixed), error (string or null), and meta (object with tenant_id, user_id, timestamp).
2. WHEN an operation succeeds, THE System SHALL set success to true, populate data, and set error to null.
3. WHEN an operation fails, THE System SHALL set success to false, set data to null, and populate error with a human-readable message.
4. THE System SHALL include the current Tenant_ID, authenticated User ID, and UTC timestamp in every API_Response meta field.
5. THE AI_Engine SHALL return every response as an object containing result (any), confidence (float 0.0–1.0), and model_version (string).

---

### Requirement 4: Authentication and Session Management

**User Story:** As a User, I want secure login with session management, so that my account is protected from unauthorized access.

#### Acceptance Criteria

1. WHEN a User submits valid credentials, THE System SHALL issue a signed JWT with a 15-minute expiry and a refresh token with a 7-day expiry.
2. WHEN a JWT expires, THE System SHALL allow the client to exchange a valid refresh token for a new JWT without re-authentication.
3. IF a refresh token is expired or revoked, THEN THE System SHALL require the User to re-authenticate.
4. THE System SHALL store active session metadata in Redis_Cache keyed by User ID and Tenant_ID.
5. WHEN a User logs out, THE System SHALL revoke the refresh token and remove the session from Redis_Cache within 1 second.
6. WHERE 2FA is enabled for a Tenant, THE System SHALL require a valid TOTP or SMS code after password verification before issuing a JWT.
7. WHERE SSO is configured for a Tenant, THE System SHALL authenticate Users via the configured SAML 2.0 or OAuth 2.0 identity provider.

---

### Requirement 5: Module Structure and Registration

**User Story:** As a backend developer, I want a consistent module structure, so that new modules can be added without modifying core platform code.

#### Acceptance Criteria

1. THE System SHALL locate each Module's files under /modular_core/modules/[ModuleName]/ containing at minimum a Model, Controller, Service, views/, api/, and schema/ subdirectory.
2. THE System SHALL auto-discover and register Modules present in the modules directory at application bootstrap.
3. WHEN a Module is registered, THE System SHALL load its Permission definitions into the RBAC system.
4. THE System SHALL allow Modules to declare dependencies on other Modules and enforce load order accordingly.
5. IF a required dependency Module is absent, THEN THE System SHALL log an error and disable the dependent Module rather than halting the platform.


---

## CRM MODULE

---

### Requirement 6: Contact Management

**User Story:** As an Agent, I want to create, view, update, and search Contact records, so that I can maintain accurate customer information.

#### Acceptance Criteria

1. THE System SHALL store Contact records with at minimum: full name, email addresses, phone numbers, company, job title, tags, owner (User), and Tenant_ID.
2. WHEN a User creates a Contact, THE System SHALL validate that at least one email address or phone number is provided.
3. IF a duplicate email address is detected within the same Tenant, THEN THE System SHALL warn the User and offer to merge or link the records.
4. THE System SHALL support attaching custom fields to Contact records configurable per Tenant.
5. WHEN a User searches Contacts, THE System SHALL return results matching name, email, phone, or company within 500ms for datasets up to 1 million records.
6. THE System SHALL maintain a chronological Activity timeline on each Contact record.
7. WHEN a Contact is merged with another Contact, THE System SHALL transfer all linked Activities, Deals, and notes to the surviving record and Soft_Delete the duplicate.

---

### Requirement 7: Lead Management and Capture

**User Story:** As a Manager, I want to capture and qualify Leads from multiple sources, so that the sales team can prioritize follow-up.

#### Acceptance Criteria

1. THE System SHALL store Lead records with at minimum: name, email, phone, source, status, assigned owner, and Tenant_ID.
2. THE System SHALL provide a web-to-lead form builder that generates an embeddable HTML form posting submissions to the Lead capture API endpoint.
3. WHEN a Lead form is submitted, THE System SHALL create a Lead record and enqueue a lead.captured event on the Queue within 2 seconds.
4. THE System SHALL support Lead import via CSV with field mapping and duplicate detection.
5. WHEN a Lead is qualified, THE System SHALL allow conversion to a Contact, Account, and Deal in a single atomic operation.
6. IF Lead conversion fails partially, THEN THE System SHALL roll back all created records and return a descriptive error.
7. THE System SHALL track Lead source attribution (web form, API, import, manual) on every Lead record.

---

### Requirement 8: Lead Scoring

**User Story:** As a Manager, I want Leads automatically scored by the AI Engine, so that Agents focus on the highest-value prospects.

#### Acceptance Criteria

1. WHEN a Lead record is created or updated, THE System SHALL enqueue a lead.score_request event on the Queue within 5 seconds.
2. THE AI_Engine SHALL compute a Lead_Score (integer 0–100) for each Lead based on demographic, behavioral, and engagement signals.
3. WHEN the AI_Engine returns a Lead_Score, THE System SHALL persist the score and score_updated_at timestamp on the Lead record.
4. THE System SHALL display the Lead_Score and a human-readable score explanation on the Lead detail view.
5. THE System SHALL allow Managers to configure scoring model weights per Tenant via an Admin UI.
6. WHEN a Lead_Score changes by more than 20 points, THE System SHALL notify the Lead owner via in-app notification within 30 seconds.

---

### Requirement 9: Account Management

**User Story:** As an Agent, I want to manage Account records representing companies, so that I can track all interactions with an organization.

#### Acceptance Criteria

1. THE System SHALL store Account records with at minimum: company name, industry, website, billing address, assigned owner, and Tenant_ID.
2. THE System SHALL link multiple Contacts to a single Account.
3. WHEN an Account is viewed, THE System SHALL display all linked Contacts, Deals, Activities, and Invoices in a unified timeline.
4. THE System SHALL support Account hierarchy (parent/child relationships) up to 5 levels deep.
5. THE System SHALL compute and display aggregate Deal value and win rate per Account.

---

### Requirement 10: Sales Pipeline and Deal Management

**User Story:** As a Manager, I want to manage Deals through configurable Pipelines, so that I can track and forecast revenue accurately.

#### Acceptance Criteria

1. THE System SHALL allow Admins to create multiple Pipelines per Tenant, each with a configurable ordered list of Stages.
2. THE System SHALL store Deal records with at minimum: title, value (currency + amount), Pipeline, Stage, close date, probability, assigned owner, linked Contact, linked Account, and Tenant_ID.
3. WHEN a Deal is moved to a new Stage, THE System SHALL record the stage transition with a timestamp and the acting User.
4. THE System SHALL display Deals in a Kanban board view grouped by Stage with drag-and-drop stage transitions.
5. THE System SHALL display Deals in a list view with sortable and filterable columns.
6. WHEN a Deal's close date passes and the Deal is not in a closed Stage, THE System SHALL mark the Deal as overdue and notify the owner.
7. THE System SHALL compute a weighted pipeline forecast by multiplying each Deal's value by its Win_Probability and summing per Pipeline.
8. THE System SHALL support Deal rotting: WHEN a Deal has had no Activity for a configurable number of days, THE System SHALL flag the Deal as stale.

---

### Requirement 11: Deal Win Probability Prediction

**User Story:** As a Manager, I want AI-predicted win probabilities on Deals, so that I can prioritize the pipeline accurately.

#### Acceptance Criteria

1. WHEN a Deal is created or its Stage, value, or close date changes, THE System SHALL enqueue a deal.win_probability_request event on the Queue within 5 seconds.
2. THE AI_Engine SHALL return a Win_Probability (float 0.0–1.0) and confidence (float 0.0–1.0) for each Deal.
3. WHEN the AI_Engine returns a Win_Probability, THE System SHALL persist the value and win_probability_updated_at on the Deal record.
4. THE System SHALL display the AI-predicted Win_Probability alongside the manually set probability on the Deal detail view.
5. THE System SHALL retrain the win probability model per Tenant when the Tenant accumulates 500 or more closed Deals.

---

### Requirement 12: Omnichannel Inbox

**User Story:** As an Agent, I want a unified Inbox for all customer communications, so that I can respond across channels without switching tools.

#### Acceptance Criteria

1. THE System SHALL aggregate inbound and outbound messages from email, SMS, WhatsApp, live chat, and VoIP into a single Inbox per Tenant.
2. THE System SHALL link each Inbox conversation to a Contact or Lead record automatically when the sender's email or phone matches an existing record.
3. WHEN a new inbound message arrives, THE System SHALL deliver a real-time notification to the assigned Agent via WebSocket within 3 seconds.
4. THE System SHALL support threaded conversation views showing the full message history per contact per channel.
5. THE System SHALL allow Agents to send replies from within the Inbox without leaving the platform.
6. THE System SHALL track first response time, average handle time, and resolution time per conversation.
7. WHERE a live chat Channel is enabled, THE System SHALL serve a JavaScript chat widget embeddable on external websites.
8. THE System SHALL support canned responses: pre-written reply templates selectable by Agents during a conversation.

---

### Requirement 13: Email Integration

**User Story:** As an Agent, I want two-way email sync with my mailbox, so that all customer emails are captured in the CRM automatically.

#### Acceptance Criteria

1. THE System SHALL support connecting Gmail and Microsoft 365 mailboxes via OAuth 2.0 per User.
2. WHEN an email is sent or received on a connected mailbox, THE System SHALL sync the email to the Inbox and link it to the matching Contact within 60 seconds.
3. THE System SHALL support sending emails from within the platform using the connected mailbox's SMTP credentials.
4. THE System SHALL track email open events and link click events per email sent from the platform.
5. IF email sync fails for a connected mailbox, THEN THE System SHALL notify the owning User and log the error with retry details.


---

### Requirement 14: Workflow Automation Engine

**User Story:** As a Manager, I want to automate repetitive CRM actions with configurable workflows, so that the team spends time on high-value work.

#### Acceptance Criteria

1. THE System SHALL allow Admins and Managers to create Workflows composed of one Trigger and one or more sequential Actions.
2. THE System SHALL support the following Trigger types: record created, record updated, field value changed, date/time reached, inbound message received, and manual execution.
3. THE System SHALL support the following Action types: send email, send SMS, create Task, update field value, assign owner, add tag, create Deal, move Deal stage, call Webhook, and wait (delay).
4. WHEN a Trigger condition is met, THE System SHALL enqueue the Workflow execution on the Queue within 5 seconds.
5. THE System SHALL execute Workflow Actions in declared order and record the execution result (success/failure) of each Action step.
6. IF a Workflow Action fails, THEN THE System SHALL retry the Action up to 3 times with exponential backoff before marking the execution as failed.
7. THE System SHALL provide an execution history log per Workflow showing trigger time, actions executed, and outcomes.
8. THE System SHALL allow Workflows to be enabled, disabled, and cloned by Managers.
9. WHILE a Workflow is disabled, THE System SHALL not execute it even if its Trigger condition is met.

---

### Requirement 15: Task and Activity Management

**User Story:** As an Agent, I want to create and track Tasks and Activities linked to CRM records, so that I never miss a follow-up.

#### Acceptance Criteria

1. THE System SHALL store Task records with at minimum: title, description, due date, priority, status, assigned User, linked record (Contact/Lead/Deal/Account), and Tenant_ID.
2. THE System SHALL support Activity types: call, email, meeting, note, and task.
3. WHEN a Task's due date is reached and the Task status is not completed, THE System SHALL send a reminder notification to the assigned User.
4. THE System SHALL display all Tasks assigned to the current User in a personal task list sortable by due date and priority.
5. THE System SHALL log completed Activities automatically to the linked record's timeline.
6. THE System SHALL support bulk Task assignment by Managers to reassign Tasks across Users.

---

### Requirement 16: Calendar Integration

**User Story:** As an Agent, I want a calendar view of my scheduled Activities, so that I can manage my time effectively.

#### Acceptance Criteria

1. THE System SHALL display a calendar view (day, week, month) showing all Activities and Tasks with due dates for the current User.
2. THE System SHALL support two-way sync with Google Calendar and Microsoft Outlook Calendar via OAuth 2.0.
3. WHEN a meeting Activity is created in the platform, THE System SHALL create a corresponding calendar event on the connected external calendar within 30 seconds.
4. WHEN an external calendar event is updated or deleted, THE System SHALL reflect the change on the platform Activity within 60 seconds.
5. THE System SHALL support meeting scheduling links: a public URL allowing external contacts to book available time slots on an Agent's calendar.

---

### Requirement 17: CRM Analytics and Reporting

**User Story:** As a Manager, I want pre-built and custom CRM reports, so that I can monitor team performance and pipeline health.

#### Acceptance Criteria

1. THE System SHALL provide pre-built reports: pipeline summary, deal velocity, lead conversion rate, activity summary, and revenue forecast.
2. THE System SHALL allow Users to build custom reports by selecting a data source, dimensions, metrics, and filters via a drag-and-drop report builder.
3. WHEN a report is executed, THE System SHALL return results within 10 seconds for datasets up to 500,000 rows.
4. THE System SHALL support scheduling reports to run at a configured interval and deliver results via email.
5. THE System SHALL allow reports to be exported as CSV and PDF.
6. THE System SHALL provide a dashboard builder allowing Users to arrange report widgets on a configurable grid layout.
7. THE System SHALL support real-time dashboard widgets that refresh via WebSocket when underlying data changes.


---

## ERP MODULE

---

### Requirement 18: Chart of Accounts and General Ledger

**User Story:** As an Owner, I want a full chart of accounts and general ledger supporting multiple companies and currencies, so that I have accurate financial records for all entities in the business.

#### Acceptance Criteria

1. THE System SHALL store a Chart of Accounts (COA) per Company_Code with account types: Asset, Liability, Equity, Income, Expense, Cost, and Allocation, organized in a 5-level hierarchy: Category → Group → Sub-group → Account → Sub-account.
2. THE System SHALL record every financial transaction as a double-entry Journal_Entry with at least one debit line and one credit line; the sum of all debit amounts SHALL equal the sum of all credit amounts.
3. IF a Journal_Entry's total debits do not equal its total credits, THEN THE System SHALL reject the entry and return a descriptive error.
4. THE System SHALL store each journal entry line with the following 35 fields: company_code, area_code, area_desc, fin_period (YYYYMM), voucher_date, service_date (YYYYMM), voucher_no, section_code, voucher_sub, line_no, account_code, account_desc, cost_identifier, cost_center_code, cost_center_name, vendor_code, vendor_name, check_transfer_no, exchange_rate (DECIMAL(10,6)), currency_code, dr_value (DECIMAL(15,2) transaction currency), cr_value (DECIMAL(15,2) transaction currency), line_desc, asset_no, transaction_no, profit_loss_flag, customer_invoice_no, income_stmt_flag, internal_invoice_no, employee_no, partner_no, vendor_word_count, translator_word_count, agent_name.
5. THE System SHALL auto-assign Voucher_Code based on transaction currency: EGP → 1, USD → 2, AED → 3, SAR → 4, EUR → 5, GBP → 6, Settlement → 999.
6. THE System SHALL auto-assign Section_Code based on transaction type: Income → 01, Expense → 02; Settlement entries on Voucher 999 SHALL use section codes 991 (EGP), 992 (USD), 993 (AED), 994 (SAR), 995 (EUR), 996 (GBP).
7. IF a journal entry uses Voucher_Code 999 with Section_Code 01 or 02, THEN THE System SHALL reject the entry and return a descriptive error.
8. THE System SHALL store every monetary amount as DECIMAL(15,2) in transaction currency and DECIMAL(15,2) in base currency (EGP), with the Exchange_Rate stored as DECIMAL(10,6).
9. WHEN a database query retrieves journal entries, THE System SHALL require an explicit Company_Code filter and SHALL NOT return records from multiple companies in a single unfiltered query.
10. THE System SHALL generate a real-time trial balance, income statement, and balance sheet per Company_Code per Fin_Period.
11. THE System SHALL support the 6 configured currencies: EGP (01), USD (02), AED (03), SAR (04), EUR (05), GBP (06).
12. WHEN a multi-currency transaction is recorded, THE System SHALL convert amounts to EGP using the Exchange_Rate at the voucher date and store both the transaction-currency amount and the EGP equivalent.
13. WHEN a Fin_Period is closed for a Company_Code, THE System SHALL prevent new journal entries from being posted to that period for that company.

---

### Requirement 19: Invoicing and Accounts Receivable

**User Story:** As a Manager, I want to create and send Invoices to customers, so that I can collect payment efficiently.

#### Acceptance Criteria

1. THE System SHALL allow Users to create Invoice records linked to an Account with line items, quantities, unit prices, tax rates, and discounts.
2. THE System SHALL auto-number Invoices with a configurable prefix and sequential number per Tenant.
3. WHEN an Invoice is finalized, THE System SHALL generate a PDF and send it to the customer's email address.
4. THE System SHALL support partial payments: WHEN a payment is recorded against an Invoice, THE System SHALL update the Invoice's outstanding balance.
5. WHEN an Invoice's due date passes and the outstanding balance is greater than zero, THE System SHALL mark the Invoice as overdue.
6. THE System SHALL support recurring Invoice schedules: WHEN a schedule's next_run date is reached, THE System SHALL auto-generate and send the Invoice.
7. THE System SHALL integrate with Stripe to accept online card payments against Invoices via a hosted payment link.
8. WHEN a Stripe payment succeeds, THE System SHALL automatically record the payment and update the Invoice status to paid within 60 seconds.

---

### Requirement 20: Expense Management and Accounts Payable

**User Story:** As an Employee, I want to submit expense claims and manage supplier bills, so that company spending is tracked and approved.

#### Acceptance Criteria

1. THE System SHALL allow Users to submit expense claims with amount, currency, category, date, description, and receipt attachment.
2. THE System SHALL route expense claims through a configurable approval workflow based on amount thresholds and department.
3. WHEN an expense claim is approved, THE System SHALL post the corresponding journal entry to the General Ledger.
4. THE System SHALL store Purchase_Order records with supplier, line items, expected delivery date, and approval status.
5. WHEN a Purchase_Order is approved, THE System SHALL allow goods receipt recording that updates Inventory_Item quantities.
6. THE System SHALL support three-way matching: WHEN a supplier invoice is received, THE System SHALL match it against the Purchase_Order and goods receipt before allowing payment.
7. IF three-way matching finds a discrepancy greater than 5%, THEN THE System SHALL flag the invoice for manual review.

---

### Requirement 21: Inventory and Warehouse Management

**User Story:** As a Manager, I want real-time inventory tracking across warehouses, so that stock levels are always accurate.

#### Acceptance Criteria

1. THE System SHALL store Inventory_Item records with SKU, name, description, unit of measure, reorder point, reorder quantity, cost, and Tenant_ID.
2. THE System SHALL track stock quantities per Inventory_Item per Warehouse location.
3. WHEN a stock movement (receipt, shipment, transfer, adjustment) is recorded, THE System SHALL update the on-hand quantity and create an immutable stock ledger entry.
4. WHEN an Inventory_Item's on-hand quantity falls below its reorder point, THE System SHALL create a reorder alert and optionally auto-generate a Purchase_Order.
5. THE System SHALL support batch and serial number tracking per Inventory_Item where enabled.
6. THE System SHALL support stock takes: WHEN a stock take is initiated, THE System SHALL lock the affected Inventory_Items for counting and compute variance on completion.
7. THE System SHALL compute inventory valuation using FIFO, LIFO, or weighted average cost per Tenant configuration.

---

### Requirement 22: Procurement Management

**User Story:** As a Manager, I want a structured procurement process, so that purchasing is controlled and auditable.

#### Acceptance Criteria

1. THE System SHALL allow Users to create Purchase Requisitions that are routed for approval before becoming Purchase_Orders.
2. THE System SHALL support a supplier catalog: Inventory_Items linked to preferred suppliers with negotiated prices and lead times.
3. WHEN a Purchase_Order is sent to a supplier, THE System SHALL record the sent timestamp and delivery due date.
4. THE System SHALL track Purchase_Order fulfillment status: pending, partially received, fully received, and cancelled.
5. THE System SHALL generate a procurement spend report by supplier, category, and period.
6. THE System SHALL support RFQ (Request for Quotation): WHEN an RFQ is issued, THE System SHALL allow multiple supplier quotes to be recorded and compared.

---

### Requirement 23: HR and Employee Management

**User Story:** As an Admin, I want to manage Employee records and organizational structure, so that HR data is centralized and accurate.

#### Acceptance Criteria

1. THE System SHALL store Employee records with at minimum: full name, employee ID, department, job title, employment type, start date, manager, and Tenant_ID.
2. THE System SHALL support an organizational chart view derived from manager relationships.
3. THE System SHALL manage the employee lifecycle: onboarding, active, on leave, and offboarding states.
4. WHEN an Employee's status changes, THE System SHALL trigger the configured Workflow for that transition.
5. THE System SHALL store and version employee documents (contracts, certificates) with access restricted to HR role and above.
6. THE System SHALL support configurable leave types with accrual rules and balance tracking per Employee.
7. WHEN a leave request is submitted, THE System SHALL route it for manager approval and update the Employee's leave balance upon approval.

---

### Requirement 24: Payroll Processing

**User Story:** As an Admin, I want to run payroll for all Employees, so that compensation is calculated accurately and on time.

#### Acceptance Criteria

1. THE System SHALL support configurable pay components: base salary, allowances, bonuses, and deductions per Employee.
2. WHEN a Payroll_Run is initiated for a pay period, THE System SHALL compute gross pay, statutory deductions, and net pay for every active Employee in the Tenant.
3. THE System SHALL apply configurable tax tables per jurisdiction to compute income tax deductions.
4. WHEN a Payroll_Run is finalized, THE System SHALL generate payslips in PDF format and post the payroll journal entry to the General Ledger.
5. THE System SHALL support payroll export in bank transfer file formats (CSV, NACHA, BACS) for bulk payment processing.
6. IF a Payroll_Run computation produces a negative net pay for any Employee, THEN THE System SHALL flag that Employee's record and exclude it from finalization until corrected.


---

### Requirement 25: Project Management

**User Story:** As a Manager, I want to plan and track Projects with Tasks and Milestones, so that deliverables are completed on time.

#### Acceptance Criteria

1. THE System SHALL store Project records with name, description, start date, end date, status, budget, assigned team members, and Tenant_ID.
2. THE System SHALL support a hierarchical Task structure within Projects: Tasks may have sub-Tasks up to 3 levels deep.
3. THE System SHALL display Projects in a Gantt chart view showing Tasks, dependencies, and Milestones on a timeline.
4. THE System SHALL support Task dependencies (finish-to-start, start-to-start) and automatically flag scheduling conflicts.
5. WHEN a Milestone's due date is reached and the Milestone is not marked complete, THE System SHALL notify the Project Manager.
6. THE System SHALL track time logged against Project Tasks per User and compute actual vs. budgeted hours.
7. THE System SHALL compute Project completion percentage based on the ratio of completed Tasks to total Tasks.

---

### Requirement 26: Manufacturing and Bill of Materials

**User Story:** As a Manager, I want to manage Bills of Materials and Work Orders, so that production is planned and tracked accurately.

#### Acceptance Criteria

1. THE System SHALL store BOM records linking a finished product to its component Inventory_Items with quantities and units of measure.
2. THE System SHALL support multi-level BOMs where a component may itself have a BOM.
3. WHEN a Work_Order is created for a quantity of a finished product, THE System SHALL compute the required component quantities from the BOM and check stock availability.
4. IF required components are insufficient, THEN THE System SHALL list the shortfall per component and optionally generate Purchase_Orders for the deficit.
5. WHEN a Work_Order is completed, THE System SHALL consume the component quantities from inventory and add the finished product quantity to inventory.
6. THE System SHALL track Work_Order status: planned, in progress, completed, and cancelled.
7. THE System SHALL compute production cost per Work_Order based on component costs and configured labor rates.


---

## PLATFORM CORE

---

### Requirement 27: Real-Time WebSocket Notifications

**User Story:** As a User, I want real-time in-app notifications, so that I am immediately aware of events relevant to me.

#### Acceptance Criteria

1. THE System SHALL maintain a persistent WebSocket connection per authenticated User session.
2. WHEN a platform event relevant to a User occurs (new message, Task due, Deal stage change, Workflow completion), THE System SHALL push a notification payload to that User's WebSocket connection within 3 seconds.
3. THE System SHALL store undelivered notifications for Users who are offline and deliver them upon reconnection.
4. THE System SHALL allow Users to mark notifications as read individually or in bulk.
5. THE System SHALL retain notification history per User for 90 days.

---

### Requirement 28: SaaS Subscription Billing via Stripe

**User Story:** As an Owner, I want to manage my subscription plan and billing through the platform, so that I can control costs and upgrade as needed.

#### Acceptance Criteria

1. THE System SHALL integrate with Stripe Billing to manage Tenant subscription plans, pricing tiers, and billing cycles.
2. WHEN a Tenant signs up, THE System SHALL create a Stripe Customer and attach a default payment method.
3. WHEN a Stripe subscription invoice is paid, THE System SHALL update the Tenant's plan status to active within 60 seconds via Stripe webhook.
4. WHEN a Stripe subscription invoice payment fails, THE System SHALL notify the Tenant Owner and restrict access to paid features after a 7-day grace period.
5. THE System SHALL allow Owners to upgrade, downgrade, or cancel their subscription plan from within the platform.
6. THE System SHALL enforce per-plan feature flags and User seat limits per Tenant.
7. WHEN a Tenant exceeds its seat limit, THE System SHALL prevent new User creation and notify the Owner.

---

### Requirement 29: Global Search

**User Story:** As a User, I want to search across all modules from a single search bar, so that I can find any record quickly.

#### Acceptance Criteria

1. THE System SHALL provide a Global_Search bar accessible from every page in the platform.
2. WHEN a User types a query of 2 or more characters, THE System SHALL return matching records across Contacts, Leads, Deals, Accounts, Invoices, and Projects within 500ms.
3. THE System SHALL scope Global_Search results to the current Tenant_ID and the User's RBAC permissions.
4. THE System SHALL support semantic search using Embeddings for natural language queries in addition to keyword matching.
5. THE System SHALL display search results grouped by record type with a direct navigation link to each result.

---

### Requirement 30: Audit Logging

**User Story:** As an Admin, I want an immutable audit log of all data changes, so that I can investigate issues and meet compliance requirements.

#### Acceptance Criteria

1. THE System SHALL record an Audit_Log entry for every create, update, delete, and permission change operation.
2. THE Audit_Log entry SHALL capture: timestamp, Tenant_ID, User ID, operation type, affected table, record ID, previous values, and new values.
3. THE System SHALL store Audit_Log entries in an append-only store that prevents modification or deletion by any User role including Owner.
4. THE System SHALL provide an Audit_Log search interface for Admins filterable by User, date range, record type, and operation type.
5. WHEN an Audit_Log search is executed, THE System SHALL return results within 5 seconds for log datasets up to 10 million entries.

---

### Requirement 31: Webhook Management

**User Story:** As a developer, I want to configure outbound Webhooks for platform events, so that I can integrate NexSaaS with external systems.

#### Acceptance Criteria

1. THE System SHALL allow Admins to register Webhook endpoints with a target URL, secret key, and list of subscribed event types.
2. WHEN a subscribed event occurs, THE System SHALL deliver an HTTP POST to the registered URL with a signed payload within 10 seconds.
3. THE System SHALL sign Webhook payloads using HMAC-SHA256 with the registered secret key.
4. IF a Webhook delivery fails (non-2xx response or timeout), THEN THE System SHALL retry delivery up to 5 times with exponential backoff over 24 hours.
5. THE System SHALL record Webhook delivery attempts with status, response code, and response body for the last 30 days.

---

### Requirement 32: Internationalization (i18n)

**User Story:** As an Owner, I want the platform UI available in multiple languages, so that my team can work in their preferred language.

#### Acceptance Criteria

1. THE System SHALL support UI language selection per User from a set of configured locales.
2. THE System SHALL externalize all user-facing strings into locale resource files.
3. WHEN a User selects a locale, THE System SHALL render all UI labels, messages, and date/number formats according to that locale within one page load.
4. THE System SHALL support right-to-left (RTL) text rendering for Arabic and Hebrew locales.
5. WHERE a translation is missing for a selected locale, THE System SHALL fall back to English without displaying a raw translation key.

---

### Requirement 33: Two-Factor Authentication (2FA)

**User Story:** As an Owner, I want to enforce 2FA for my Tenant, so that accounts are protected against credential compromise.

#### Acceptance Criteria

1. THE System SHALL support TOTP-based 2FA compatible with authenticator apps (RFC 6238).
2. THE System SHALL support SMS-based 2FA as an alternative to TOTP.
3. WHERE 2FA is enforced at the Tenant level, THE System SHALL require all Users to enroll in 2FA before accessing the platform.
4. WHEN a User enrolls in TOTP 2FA, THE System SHALL display a QR code and 10 single-use backup codes.
5. WHEN a backup code is used for authentication, THE System SHALL invalidate that code and notify the User to generate new codes.

---

### Requirement 34: Single Sign-On (SSO)

**User Story:** As an Admin, I want to configure SSO for my Tenant, so that Users can authenticate with our corporate identity provider.

#### Acceptance Criteria

1. THE System SHALL support SAML 2.0 SP-initiated SSO per Tenant.
2. THE System SHALL support OAuth 2.0 / OpenID Connect SSO per Tenant.
3. WHEN a User authenticates via SSO, THE System SHALL provision a platform User account if one does not exist, using attributes from the identity provider.
4. THE System SHALL allow Admins to map identity provider groups to platform RBAC roles.
5. WHEN an SSO-provisioned User's identity provider account is deactivated, THE System SHALL deactivate the platform User account within 24 hours.


---

## AI ENGINE

---

### Requirement 35: AI Engine API Contract

**User Story:** As a backend developer, I want a consistent AI Engine API contract, so that all platform modules can consume AI features uniformly.

#### Acceptance Criteria

1. THE AI_Engine SHALL expose all prediction endpoints via FastAPI accepting a JSON body with tenant_id (string) and payload (object).
2. THE AI_Engine SHALL return all responses as a JSON object with result (any), confidence (float 0.0–1.0), and model_version (string).
3. WHEN an AI_Engine request is received without a valid tenant_id, THE AI_Engine SHALL return HTTP 400 with a descriptive error.
4. THE AI_Engine SHALL process prediction requests within 2 seconds at the 95th percentile under normal load.
5. THE AI_Engine SHALL version all deployed models and include the active model_version in every response.

---

### Requirement 36: Churn Prediction

**User Story:** As a Manager, I want AI-predicted churn scores for customers, so that I can proactively retain at-risk accounts.

#### Acceptance Criteria

1. THE AI_Engine SHALL compute a Churn_Score (integer 0–100) for each Account based on engagement, purchase recency, support ticket volume, and contract renewal proximity.
2. WHEN a Churn_Score is computed, THE System SHALL persist the score and churn_score_updated_at on the Account record.
3. THE System SHALL display the Churn_Score with a risk tier label (low: 0–33, medium: 34–66, high: 67–100) on the Account detail view.
4. WHEN an Account's Churn_Score enters the high tier, THE System SHALL create a Task for the Account owner to schedule a retention call.
5. THE System SHALL recompute Churn_Scores for all Accounts in a Tenant on a daily schedule via the Queue.

---

### Requirement 37: NLP and Sentiment Analysis

**User Story:** As a Manager, I want sentiment analysis on customer communications, so that I can identify dissatisfied customers before they churn.

#### Acceptance Criteria

1. WHEN an inbound message is received in the Inbox, THE AI_Engine SHALL analyze the message text and return a sentiment label (positive, neutral, negative) and a confidence score.
2. THE System SHALL store the sentiment label and confidence on the Inbox message record.
3. WHEN a message is classified as negative with confidence above 0.75, THE System SHALL flag the conversation for supervisor review.
4. THE System SHALL display sentiment indicators on the Inbox conversation list view.
5. THE System SHALL provide a sentiment trend report per Account showing sentiment distribution over a configurable time period.

---

### Requirement 38: AI-Powered Global Search with Embeddings

**User Story:** As a User, I want semantic search across all records, so that I can find relevant information using natural language.

#### Acceptance Criteria

1. THE AI_Engine SHALL generate an Embedding vector for each searchable record (Contact, Lead, Deal, Account, note) when the record is created or updated.
2. THE System SHALL store Embedding vectors in a vector index scoped per Tenant.
3. WHEN a Global_Search query is submitted, THE AI_Engine SHALL compute the query Embedding and return the top 20 semantically similar records within 500ms.
4. THE System SHALL combine semantic search results with keyword search results and deduplicate before returning to the User.
5. THE System SHALL update a record's Embedding within 30 seconds of the record being created or updated.

---

### Requirement 39: AI Email and Response Suggestions

**User Story:** As an Agent, I want AI-suggested email replies, so that I can respond to customers faster and more consistently.

#### Acceptance Criteria

1. WHEN an Agent opens an inbound email in the Inbox, THE AI_Engine SHALL generate up to 3 suggested reply drafts based on the email content and conversation history.
2. THE System SHALL display suggested replies as selectable options in the reply composer.
3. WHEN an Agent selects a suggested reply, THE System SHALL populate the reply composer with the suggestion text, which the Agent may edit before sending.
4. THE AI_Engine SHALL personalize suggestions using the linked Contact's name and recent interaction history.
5. THE System SHALL collect implicit feedback: WHEN an Agent edits a suggestion before sending, THE System SHALL log the original and edited text for model improvement.

---

### Requirement 40: Forecasting and Revenue Intelligence

**User Story:** As an Owner, I want AI-generated revenue forecasts, so that I can plan resources and set realistic targets.

#### Acceptance Criteria

1. THE AI_Engine SHALL generate a monthly revenue forecast for the next 3 months per Tenant based on historical closed revenue, pipeline Win_Probability, and seasonal patterns.
2. THE System SHALL display the AI forecast alongside the manually computed weighted pipeline forecast on the revenue dashboard.
3. THE AI_Engine SHALL provide a confidence interval (lower bound, upper bound) for each monthly forecast figure.
4. THE System SHALL refresh the revenue forecast daily via the Queue.
5. THE System SHALL track forecast accuracy: WHEN a forecast month closes, THE System SHALL record the actual revenue and compute the forecast error percentage.


---

## CROSS-CUTTING REQUIREMENTS

---

### Requirement 41: Performance and Scalability

**User Story:** As an Owner, I want the platform to remain responsive under load, so that my team's productivity is not impacted by system slowness.

#### Acceptance Criteria

1. THE System SHALL respond to all interactive API requests within 500ms at the 95th percentile under a load of 500 concurrent Users per Tenant.
2. THE System SHALL support horizontal scaling of the PHP backend and AI_Engine via Kubernetes pod autoscaling.
3. THE System SHALL cache frequently read, rarely written data (permissions, tenant config, lookup tables) in Redis_Cache with appropriate TTLs.
4. WHEN Redis_Cache is unavailable, THE System SHALL fall back to database reads and log a cache unavailability warning.
5. THE System SHALL use database connection pooling with a minimum of 10 and maximum of 100 connections per application instance.

---

### Requirement 42: Security

**User Story:** As an Owner, I want the platform to follow security best practices, so that customer data is protected.

#### Acceptance Criteria

1. THE System SHALL hash all User passwords using bcrypt with a minimum cost factor of 12 before storage.
2. THE System SHALL enforce HTTPS for all client-server communication.
3. THE System SHALL sanitize all user-supplied input to prevent SQL injection and XSS attacks.
4. THE System SHALL implement CSRF protection on all state-changing HTTP endpoints.
5. THE System SHALL rate-limit authentication endpoints to 10 attempts per minute per IP address.
6. IF an IP address exceeds the authentication rate limit, THEN THE System SHALL block further attempts from that IP for 15 minutes and log the event.
7. THE System SHALL rotate JWT signing keys on a 90-day schedule without disrupting active sessions.

---

### Requirement 43: Data Export and Portability

**User Story:** As an Owner, I want to export all my Tenant's data, so that I can migrate or archive records as needed.

#### Acceptance Criteria

1. THE System SHALL allow Owners to request a full data export of all Tenant records in JSON and CSV formats.
2. WHEN a data export is requested, THE System SHALL generate the export asynchronously via the Queue and notify the Owner when the download is ready.
3. THE System SHALL complete data export generation within 4 hours for Tenants with up to 1 million records.
4. THE System SHALL make the export download available for 7 days before deleting the export file.
5. THE System SHALL allow Owners to delete all Tenant data (right to erasure) which SHALL permanently remove all records and revoke all active sessions within 24 hours.

---

### Requirement 44: API Parser and Serializer Integrity

**User Story:** As a developer, I want all API data serialization to be lossless and round-trippable, so that data is never corrupted in transit.

#### Acceptance Criteria

1. THE System SHALL serialize all API request and response payloads as UTF-8 encoded JSON.
2. THE System SHALL parse incoming JSON request bodies and validate them against the declared schema before processing.
3. IF an incoming request body fails schema validation, THEN THE System SHALL return HTTP 422 with a field-level error map.
4. THE Pretty_Printer SHALL format all JSON API responses with consistent key ordering and no trailing whitespace.
5. FOR ALL valid platform records, serializing a record to JSON then deserializing the JSON SHALL produce a record equal to the original (round-trip property).
6. THE System SHALL preserve numeric precision for monetary amounts by representing them as strings or fixed-point decimals in JSON, never as floating-point numbers.



---

## ACCOUNTING MODULE

---

### Requirement 45: Chart of Accounts Management (Batch A)

**User Story:** As an Accountant, I want to manage a structured chart of accounts per company, so that all financial transactions are classified consistently.

#### Acceptance Criteria

1. THE System SHALL store COA accounts in a 5-level hierarchy: Category → Group → Sub-group → Account → Sub-account, scoped per Company_Code.
2. THE System SHALL classify each account with one of the following types: Asset, Liability, Equity, Income, Expense, Cost, or Allocation.
3. WHEN an account is created, THE System SHALL allow restricting the account to one or more specific currency codes from the 6 configured currencies.
4. WHEN an account is created, THE System SHALL allow restricting the account to one or more specific Company_Codes.
5. THE System SHALL support a blocked flag per account; WHEN an account is blocked, THE System SHALL prevent new journal entry lines from being posted to that account.
6. THE System SHALL provide an account balance viewer showing current debit total, credit total, and net balance per account per Company_Code per Fin_Period.
7. THE System SHALL support COA import from Excel and export to Excel, preserving the 5-level hierarchy and all account attributes.
8. THE System SHALL provide an account merge tool that transfers all historical journal lines from a source account to a target account and blocks the source account.
9. THE System SHALL provide an account usage report listing every journal entry line posted to a selected account within a date range.
10. THE System SHALL support opening balance journal entries per account per Company_Code at the start of a fiscal year.
11. THE System SHALL support an allocation accounts engine: WHEN an allocation account receives a posting, THE System SHALL distribute the amount to configured target accounts by configured percentage ratios.
12. WHEN a WIP account has had no movement for more than 90 days, THE System SHALL flag the account for review and notify the Accountant.

---

### Requirement 46: Journal Entry and Voucher Engine (Batch B)

**User Story:** As an Accountant, I want a full-featured journal entry form with automated voucher coding and approval workflow, so that all financial transactions are recorded accurately and auditably.

#### Acceptance Criteria

1. THE System SHALL provide a journal entry form displaying all 35 fields with bilingual labels (Arabic and English).
2. WHEN a User selects a transaction currency, THE System SHALL auto-assign the Voucher_Code (EGP→1, USD→2, AED→3, SAR→4, EUR→5, GBP→6, Settlement→999) and auto-select the Section_Code (Income→01, Expense→02).
3. THE System SHALL validate that the selected Fin_Period is open for the selected Company_Code before allowing the entry to be saved.
4. THE System SHALL allow the service_date (YYYYMM) to differ from the voucher_date to support prepaid and accrual entries.
5. THE System SHALL support multi-line journal entries with an unlimited number of debit and credit lines per voucher.
6. THE System SHALL enforce double-entry balance in both the PHP backend and the React frontend: WHEN a User attempts to save a journal entry where Σ Dr ≠ Σ Cr, THE System SHALL display an inline error and prevent submission.
7. WHEN a User enters a voucher date, THE System SHALL auto-fill the Exchange_Rate from the Redis FX cache (key fx:rate:{currency_code}:{date}) and allow the User to override the rate.
8. THE System SHALL display both the transaction-currency amount and the EGP equivalent on every journal entry line.
9. THE System SHALL provide a vendor/client lookup typeahead on the vendor_code and vendor_name fields, searching the vendor master within the selected Company_Code.
10. WHEN the account_code on a journal line is a fixed asset account, THE System SHALL require the asset_no field to be populated.
11. WHEN the account_code on a journal line is a bank account, THE System SHALL require the check_transfer_no field to be populated.
12. THE System SHALL support word count fields: vendor_word_count and translator_word_count; WHEN a per-word rate is configured, THE System SHALL auto-calculate the line amount as word_count × per_word_rate.
13. THE System SHALL enforce a voucher approval workflow with states: Draft → Submitted → Approved → Posted → Reversed.
14. WHEN a posted voucher is reversed, THE System SHALL create a new voucher with equal and opposite debit/credit amounts and link it to the original voucher.
15. THE System SHALL support voucher copy: WHEN a User copies a voucher, THE System SHALL create a new Draft voucher pre-populated with the same lines.
16. THE System SHALL support bulk voucher import from Excel, validating all 35 fields and reporting row-level errors before committing.
17. THE System SHALL provide a voucher search interface filterable by Company_Code, Fin_Period, Voucher_Code, Section_Code, account_code, vendor_code, and date range.
18. THE System SHALL generate a bilingual PDF for each posted voucher displaying the company letterhead, all journal lines, and Arabic and English labels.
19. WHEN a Fin_Period is locked for a Company_Code, THE System SHALL prevent any new or modified vouchers from being posted to that period for that company.
20. THE System SHALL support an opening balance journal type that bypasses the Σ Dr = Σ Cr balance check, restricted to the Owner role.

---

### Requirement 47: Multi-Currency and Exchange Rate Engine (Batch C)

**User Story:** As an Accountant, I want automated exchange rate management and FX gain/loss calculation, so that multi-currency transactions are recorded at accurate rates.

#### Acceptance Criteria

1. THE System SHALL provide a currency master UI displaying the 6 configured currencies (EGP, USD, AED, SAR, EUR, GBP) with bilingual names and ISO codes.
2. THE System SHALL maintain a daily exchange rate table storing the EGP rate for each of the 5 foreign currencies (USD, AED, SAR, EUR, GBP) per calendar date.
3. THE System SHALL provide an exchange rate history viewer displaying a line chart of rate movements per currency over a configurable date range.
4. THE System SHALL support a rate source toggle per currency: manual entry OR auto-fetch from the Central Bank of Egypt API.
5. THE System SHALL cache exchange rates in Redis using the key pattern fx:rate:{currency_code}:{date} with a TTL of 24 hours, refreshed automatically at midnight via a Celery task.
6. WHEN a foreign-currency invoice or bill is settled, THE System SHALL calculate the Realized_FX_Gain_Loss as the difference between the rate at invoice date and the rate at settlement date, and post the gain/loss to the configured FX gain/loss account.
7. WHEN a Fin_Period is closed, THE System SHALL perform Unrealized_FX_Revaluation: restate all open foreign-currency balances at the closing exchange rate and post the difference to the configured revaluation accounts (DIFF. L.E. / DIFF. $).
8. WHEN a new Fin_Period opens, THE System SHALL auto-reverse the prior period's Unrealized_FX_Revaluation entries on the first day of the new period.
9. THE System SHALL provide a currency translation report converting financial statement balances from a foreign currency to EGP using the closing rate method.
10. THE System SHALL provide a Settlement_Voucher engine: WHEN a settlement entry is created, THE System SHALL enforce Voucher_Code 999 with Section_Codes 991–996 and calculate the net settlement amount.
11. THE System SHALL provide a multi-currency trial balance showing debit, credit, and net columns in both transaction currency and EGP equivalent per Company_Code per Fin_Period.

---

### Requirement 48: Accounts Receivable and Payable (Batch D)

**User Story:** As an Accountant, I want full AR and AP lifecycle management with aging reports and payment matching, so that outstanding balances are tracked and collected efficiently.

#### Acceptance Criteria

1. THE System SHALL allow creation of AR invoices linked to a customer, Company_Code, currency, and COA receivable account.
2. THE System SHALL allow creation of AP bills linked to a vendor, Company_Code, currency, and COA payable account.
3. THE System SHALL support payment matching: WHEN a payment is recorded, THE System SHALL allow allocation to one or more invoices or bills, supporting both partial and full allocation.
4. WHEN a customer payment receipt is entered, THE System SHALL post a journal entry debiting the bank account (selected by currency) and crediting the AR account.
5. WHEN a vendor payment disbursement is entered, THE System SHALL post a journal entry debiting the AP account and crediting the bank account (selected by currency).
6. THE System SHALL generate an AR aging report grouping outstanding customer balances into buckets: 0–30, 31–60, 61–90, 91–120, and 120+ days, per vendor per currency per Company_Code.
7. THE System SHALL generate an AP aging report with the same bucket structure as the AR aging report.
8. THE System SHALL support withholding tax auto-deduction: WHEN a payment is disbursed to a vendor with a configured withholding rate, THE System SHALL calculate and post the withholding tax to the Withholding Taxes account.
9. THE System SHALL track retention amounts (Retention LE and Retention $ accounts) per contract and release retention upon configured milestone completion.
10. THE System SHALL support an accruals module: WHEN an accrual entry is posted to the current year accruals account, THE System SHALL auto-reverse the entry at the start of the next Fin_Period.
11. THE System SHALL provide a sister-company intercompany reconciliation view showing net AR/AP balances between each pair of the 6 companies.
12. THE System SHALL track partner dues and withdrawals per Partner_Code per Company_Code.
13. THE System SHALL generate a customer statement showing all transactions, payments, and running balance per vendor per Fin_Period.
14. WHEN an AR invoice is overdue by more than a configurable number of days, THE System SHALL send an overdue payment alert to the assigned Accountant via a Celery-scheduled task.
15. WHERE a Company_Code has e_invoice_active = YES (Company 01), THE System SHALL format the invoice as JSON per the ETA schema, sign it, submit it to the ETA API, and track the submission status.

---

### Requirement 49: Bank and Cash Management (Batch E)

**User Story:** As an Accountant, I want to manage bank accounts, petty cash, and reconciliation, so that cash positions are always accurate and reconciled.

#### Acceptance Criteria

1. THE System SHALL maintain a bank account master with each account's name, currency, Company_Code, and linked COA account code (covering accounts such as GLOBALIZE SAIB LE/EGP, PAYPALL USD, AMARAT BANK AED, QNB E-WALLET EGP, INSTAPAY NBE EGP).
2. THE System SHALL support petty cash fund management: issue, replenish, and reconcile petty cash funds per Company_Code.
3. THE System SHALL support cash call management: track cash calls issued per Company_Code and record receipts against each call.
4. THE System SHALL track cash in transit: WHEN an inter-bank transfer is initiated, THE System SHALL record it as in-transit until the receiving bank confirms receipt.
5. THE System SHALL support bank statement import via CSV: WHEN a statement is imported, THE System SHALL match each line to posted vouchers and highlight unmatched items.
6. THE System SHALL provide a bank reconciliation UI showing book balance, bank statement balance, outstanding deposits, outstanding payments, and the reconciling difference per bank account per period.
7. WHEN bank charges appear on an imported statement, THE System SHALL auto-post a journal entry to the configured bank charges account.
8. THE System SHALL display the PAYPALL USD balance with its EGP equivalent calculated at the current exchange rate.
9. THE System SHALL provide a mobile wallet tracker for QNB E-WALLET and INSTAPAY showing transaction history and current balance.
10. THE System SHALL provide a cash position dashboard displaying all bank and cash balances per currency with a total EGP equivalent.
11. THE System SHALL generate a cash flow forecast projecting inflows and outflows for 30, 60, and 90 days based on open AR invoices and AP bills per Company_Code.
12. WHEN a month ends, THE System SHALL calculate bank interest accrual for interest-bearing accounts and post the accrual journal entry.

---

### Requirement 50: Cost Centers and Project Accounting (Batch F)

**User Story:** As a Manager, I want cost center budgets and project cost tracking with AFE management, so that spending is controlled and allocated accurately.

#### Acceptance Criteria

1. THE System SHALL store a cost center master with a full hierarchy and link each cost center to a Company_Code.
2. THE System SHALL support annual budgets per cost center per expense account per Company_Code.
3. THE System SHALL provide a budget vs. actual report with drill-down to individual voucher lines per cost center per Fin_Period.
4. THE System SHALL support a cost allocation engine: WHEN indirect expenses are posted to an allocation account, THE System SHALL distribute the amount to configured target cost centers (Exploration, Development, Operating) by configured percentage ratios.
5. THE System SHALL track exploration costs in WIP EXP accounts per AFE number.
6. THE System SHALL track development costs in WIP DEV accounts per AFE number.
7. THE System SHALL track construction costs in WIP CONSTR accounts per AFE number.
8. THE System SHALL support AFE management: create an AFE with an approved budget, track actual spend against the AFE budget, and alert the Accountant when spend exceeds 90% of the AFE budget.
9. THE System SHALL support an AFE closing workflow: WHEN an AFE is closed, THE System SHALL transfer the WIP balance from the WIP EXP/DEV AFE CLOSING ACCOUNT to either a capitalized asset account or an expense account based on the closing decision.
10. THE System SHALL support department time allocation: WHEN a payroll journal is posted, THE System SHALL distribute salary and benefits costs across cost centers according to configured time allocation percentages.
11. WHEN an exploration well is declared a dry hole, THE System SHALL transfer the associated WIP EXP balance to the Dry Hole Expenses account.
12. THE System SHALL generate a production expense report summarizing costs by cost center, account, and Fin_Period.

---

### Requirement 51: Fixed Assets (Batch G)

**User Story:** As an Accountant, I want to manage the full fixed asset lifecycle including acquisition, depreciation, and disposal, so that asset values are accurately reported.

#### Acceptance Criteria

1. THE System SHALL store an asset master with categories including: Buildings, Fences, Porta Cabins, Plant Equipment, Marine Equipment, Furniture, Computer Hardware, Software, Vehicles, Cranes, and other configurable categories, scoped per Company_Code.
2. WHEN an asset is acquired, THE System SHALL link the acquisition to the tangible cost COA account and record the asset cost, acquisition date, and Company_Code.
3. THE System SHALL support depreciation setup per asset category: straight-line or declining balance method, useful life in months, and salvage value.
4. WHEN the monthly depreciation Celery task runs, THE System SHALL calculate and post depreciation journal entries for all active assets per Company_Code.
5. WHEN an asset is disposed of, THE System SHALL calculate the gain or loss as the difference between the disposal proceeds and the net book value, post the disposal journal entry to the RETIRED ASSETS & EQUIPMENT and ASSETS CLEARING ACCOUNT accounts, and mark the asset as retired.
6. THE System SHALL support asset revaluation: WHEN an asset is revalued, THE System SHALL post the revaluation difference to the equity revaluation reserve account.
7. THE System SHALL support asset overhaul/CAPEX entries: WHEN an overhaul cost is recorded, THE System SHALL allow the User to decide whether to capitalize the cost (add to asset cost) or expense it based on a configurable threshold.
8. THE System SHALL generate an asset register report showing cost, accumulated depreciation, and net book value per asset per Company_Code.
9. THE System SHALL generate an asset movement report showing transfers of assets between companies.
10. WHEN salvage materials are recovered from a retired asset, THE System SHALL post the gain or loss on salvaged material to the configured gain/loss account.

---

### Requirement 52: Payroll and Salary Module (Batch H)

**User Story:** As an Admin, I want to run monthly payroll with full allowance and deduction structures, so that employee compensation is calculated accurately and posted to the general ledger.

#### Acceptance Criteria

1. THE System SHALL support a salary structure with at minimum the following allowance components: Basic Salary, Production, Incentive Bonus, Overtime, Annual Profit, Monthly Bonus, H/Cost of Living, Nature of Work, Represent, Desert, Labour Day, Shift, Experience, Science Grade, Garage, Special Increase, Fulltime, Cashier, Merit, Offshore, Radio, Dangers, Drilling, Expatriation, Tender, Committee, Meals, Transportation.
2. THE System SHALL support the following deduction components: Income Tax, Social Insurance, Supp. Pension, Provident Fund, Retirement Pension, Journey, Employee Loan, School Loan, Mobile Bills, Amusement Park, Summer Resorts, Disability Fund, Martyrs Fund, Raya Install, Premium Install, Law 170/2020, RISKALLA, Other.
3. THE System SHALL link each employee profile to a salary grade and a Company_Code.
4. WHEN a monthly Payroll_Run is initiated for a Company_Code and Fin_Period, THE System SHALL compute gross pay, all deductions, and net pay for every active employee in that company and post the resulting journal entries to the COA automatically.
5. THE System SHALL generate a bilingual Payslip PDF (Arabic and English) for each employee per Payroll_Run.
6. THE System SHALL generate an ATM salary payment file in a configurable bank transfer format listing employee bank account numbers and net pay amounts.
7. THE System SHALL track on-loan employees: WHEN an employee is on loan to or from another company, THE System SHALL post the salary cost to the Loanes Sal. From Other / To Other / Onloan Epsco accounts accordingly.
8. THE System SHALL support a separate board member compensation run with its own journal entry posting.
9. THE System SHALL generate a social insurance report showing employer and employee share per employee per month.
10. THE System SHALL distribute payroll costs across cost centers according to configured time allocation percentages per employee.
11. THE System SHALL calculate end-of-service (EOS) bonus per employee based on configured rules and post the provision journal entry.
12. WHEN a translator's payment is calculated, THE System SHALL compute the amount as translator_word_count × configured per-word rate and include it as a payroll line.
13. IF a Payroll_Run computation produces a negative net pay for any employee, THEN THE System SHALL flag that employee's record and exclude it from finalization until corrected.

---

### Requirement 53: Partner Profit Distribution (Batch I)

**User Story:** As an Owner, I want automated partner profit calculation and distribution tracking, so that each partner's share is computed accurately and transparently.

#### Acceptance Criteria

1. WHEN a Fin_Period is closed for a Company_Code, THE System SHALL aggregate the net income for that company and calculate each partner's share based on the configured partner_share_pct per Partner_Code.
2. THE System SHALL post a partner distribution journal entry debiting the ANNUAL PROFIT account and crediting the PARTNER DUES account for each Partner_Code.
3. WHEN a partner withdrawal is entered, THE System SHALL post a journal entry debiting the PARTNER DUES account and crediting the bank account, and record the withdrawal against the Partner_Code.
4. THE System SHALL track partner capital per Partner_Code in the SHARE CAPITAL account per Company_Code.
5. THE System SHALL generate a partner account statement showing all dues, withdrawals, and running balance per Partner_Code per Company_Code per Fin_Period.
6. THE System SHALL provide a multi-company partner roll-up view showing one partner's total equity stake and dues across all 6 companies.
7. WHEN a partner withdrawal amount exceeds a configurable threshold, THE System SHALL require approval from both partners before the withdrawal journal is posted.
8. THE System SHALL generate a partner profit forecast based on projected net income for the next Fin_Period per Company_Code.

---

### Requirement 54: Financial Statements (Batch J)

**User Story:** As an Owner, I want automated financial statements with multi-company consolidation and comparative periods, so that I can make informed business decisions.

#### Acceptance Criteria

1. THE System SHALL generate a Trial Balance per Company_Code per Fin_Period showing debit total, credit total, and net balance per account, exportable to Excel.
2. THE System SHALL auto-generate a Profit and Loss Statement from the COA by computing Income − Cost − Expenses per Company_Code per Fin_Period.
3. THE System SHALL auto-generate a Balance Sheet verifying Assets = Liabilities + Equity per Company_Code per Fin_Period, with comparative prior-period columns.
4. THE System SHALL generate a Cash Flow Statement using the direct method derived from bank account movements per Company_Code per Fin_Period.
5. THE System SHALL generate consolidated financial statements aggregating all 6 companies and eliminating inter-company balances.
6. THE System SHALL generate a department P&L by cost center using time-allocation entries to attribute revenue and costs to production, exploration, development, and admin departments.
7. THE System SHALL generate currency-translated financial statements converting foreign-currency balances to EGP using the closing rate method.
8. THE System SHALL generate a comparative period report showing current period figures, same-period prior-year figures, variance amount, and variance percentage per account.
9. THE System SHALL provide a financial period close checklist with the following steps: reconcile AR/AP, revalue FX, post depreciation, allocate indirect expenses, post partner profit; each step SHALL display a completion status.
10. THE System SHALL generate an audit trail report showing every posted journal entry with user, timestamp, IP address, Company_Code, and Fin_Period; the report SHALL be immutable and exportable as PDF.

---

### Requirement 55: Tax and Compliance (Batch K)

**User Story:** As an Accountant, I want tax tracking, compliance reporting, and e-invoice integration, so that the business meets all Egyptian tax obligations.

#### Acceptance Criteria

1. THE System SHALL maintain a withholding tax ledger tracking all movements in the Withholding Taxes account per Company_Code, with a monthly reconciliation report.
2. THE System SHALL calculate a monthly income tax provision and post the provision journal entry to the configured income tax provision account.
3. THE System SHALL generate a social insurance Form 2 report showing monthly totals per employee per Company_Code.
4. THE System SHALL tag VAT on applicable invoices when a company's VAT registration is activated, and generate a VAT report per Fin_Period.
5. WHERE a Company_Code has e_invoice_active = YES, THE System SHALL format each finalized invoice as JSON per the ETA schema, sign the payload, submit it to the ETA API, and store the submission status and ETA response on the invoice record.
6. WHEN the tax card expiry date for a company is within 90, 60, or 30 days, THE System SHALL send an alert to the Owner and Admin.
7. WHEN the commercial registry expiry date for a company is within 90, 60, or 30 days, THE System SHALL send an alert to the Owner and Admin.
8. THE System SHALL provide a tax filing calendar displaying Egyptian VAT, withholding tax, and social insurance monthly due dates on a dashboard calendar.
9. THE System SHALL generate an annual tax return summary per Company_Code aggregating income, deductions, and tax liability for the fiscal year.

---

### Requirement 56: Accounting Reporting and Analytics Dashboard (Batch L)

**User Story:** As an Owner, I want a comprehensive accounting dashboard with custom report builder and scheduled reports, so that financial insights are always accessible.

#### Acceptance Criteria

1. THE System SHALL provide an accounting home dashboard displaying: cash position across all banks and currencies, AR aging summary, AP aging summary, count of vouchers pending approval, and financial period close checklist progress.
2. THE System SHALL display a voucher volume bar chart showing the number of vouchers posted per Fin_Period per Company_Code.
3. THE System SHALL display a currency exposure donut chart showing the distribution of balances by currency expressed in EGP equivalent.
4. THE System SHALL display a partner equity dashboard showing each partner's capital, dues, and net equity per Company_Code.
5. THE System SHALL display a cost center spend heatmap comparing actual spend to budget per cost center, color-coded by variance percentage.
6. THE System SHALL display a ranked bar chart of the top expense accounts by total amount per Company_Code per Fin_Period.
7. THE System SHALL display an inter-company balances monitor showing the net AR/AP position between each pair of the 6 companies; the sum of all inter-company balances SHALL equal zero.
8. THE System SHALL display a cash call tracker widget showing outstanding cash calls per Company_Code.
9. THE System SHALL display WIP account balances for all WIP EXP, WIP DEV, and WIP CONSTR accounts per Company_Code.
10. THE System SHALL provide a custom report builder allowing Users to select any combination of journal entry line fields, apply group-by, filter, and sort operations, and export results as CSV, Excel, or PDF.
11. THE System SHALL support scheduled reports: WHEN a schedule is configured, THE System SHALL auto-generate and email the Trial Balance, P&L, and Cash Position reports at month-end.

---

### Requirement 57: AI Engine Accounting Features (Batch M)

**User Story:** As an Accountant, I want AI-powered anomaly detection and predictive analytics on accounting data, so that errors are caught early and cash flow is forecasted accurately.

#### Acceptance Criteria

1. WHEN a User enters an exchange rate on a journal entry, THE AI_Engine SHALL compare the entered rate to the Redis-cached market rate and flag the entry if the deviation exceeds a configurable percentage threshold.
2. BEFORE a voucher is posted, THE AI_Engine SHALL check for duplicate vouchers by searching for existing posted vouchers with the same vendor, amount, and date within a ±3-day window, and warn the Accountant if a potential duplicate is found.
3. WHEN a User enters a cost_identifier free-text value on a journal line, THE AI_Engine SHALL suggest the top 3 matching account codes from the COA using text similarity scoring.
4. WHEN the monthly WIP scan Celery task runs, THE AI_Engine SHALL flag any WIP account balances that have had no movement for more than 90 days and notify the Accountant.
5. THE AI_Engine SHALL generate a cash flow prediction for the next 3 months per Company_Code per currency based on historical monthly patterns, and display the prediction on the cash position dashboard.
6. THE AI_Engine SHALL aggregate vendor_word_count × configured per-word rate by Fin_Period and generate a translation revenue forecast for the translation business companies.
7. WHEN a journal entry line is submitted, THE AI_Engine SHALL compare the Dr/Cr amount to historical entries on the same account and flag the line if the amount is a statistical outlier.

---

### Requirement 58: Platform-Wide Accounting Features

**User Story:** As a User, I want platform-level accounting controls including company switching, period management, and bilingual output, so that multi-company accounting operations are seamless and compliant.

#### Acceptance Criteria

1. THE System SHALL provide a company switcher in the top navigation bar allowing Users to switch the active Company_Code (01–06); WHEN the active company changes, THE System SHALL re-scope all accounting queries to the selected Company_Code.
2. THE System SHALL provide a financial period manager allowing Admins to open, close, or lock YYYYMM periods per Company_Code; WHEN a period is locked, THE System SHALL prevent any backdated posting to that period.
3. THE System SHALL support multi-company journal entries: WHEN a single entry is designated as intercompany, THE System SHALL post the corresponding lines to each involved Company_Code simultaneously in a single atomic transaction.
4. THE System SHALL provide an accounting audit log UI filterable by User, Company_Code, account_code, Fin_Period, and action type; the log SHALL be immutable and exportable.
5. THE System SHALL enforce the following accounting RBAC permissions: accounting.voucher.create, accounting.voucher.approve, accounting.voucher.reverse, accounting.period.close, accounting.statements.view, accounting.payroll.run, accounting.partner.distribute.
6. THE System SHALL render every accounting report and voucher PDF with an Arabic RTL section and an English LTR section using the bilingual PDF engine.
7. THE System SHALL format all monetary amounts as #,##0.00 with the currency symbol; WHEN the User locale is Arabic, THE System SHALL render amounts using Arabic-Indic numerals.
8. THE System SHALL support full accounting data export per Company_Code per fiscal year as an Excel workbook with one sheet per accounting module (Journal Entries, AR, AP, Bank, Assets, Payroll, Partners).
