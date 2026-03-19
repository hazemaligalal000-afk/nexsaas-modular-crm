# NexSaaS - Complete Testing Guide for All Phases

## Overview
This document provides a comprehensive testing checklist for all 7 phases of the NexSaaS platform.

---

## Phase 1: Platform Foundation ✅

### Test 1.1: Database Connection & Schema
```bash
# Connect to database
docker compose exec postgres psql -U nexsaas -d nexsaas

# Check tables exist
\dt

# Expected tables: tenants, users, role_permissions, etc.
```

### Test 1.2: Tenant Isolation
```bash
# Run property test
docker compose exec php-fpm php modular_core/tests/Properties/TenantIsolationTest.php
```

### Test 1.3: Soft Delete
```bash
# Run property test
docker compose exec php-fpm php modular_core/tests/Properties/SoftDeleteRoundTripTest.php
```

### Test 1.4: API Response Envelope
```bash
# Test API endpoint
curl http://localhost:8080/api/v1/health

# Expected: {"success":true,"data":{},"error":null,"meta":{...}}
```

### Test 1.5: RBAC Permission Enforcement
```bash
# Run property test
docker compose exec php-fpm php modular_core/tests/Properties/RBACPermissionTest.php
```

### Test 1.6: Authentication
```bash
# Test login endpoint
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Expected: JWT token in response
```

---

## Phase 2: CRM Module ✅

### Test 2.1: Contact Management
```bash
# Create contact
curl -X POST http://localhost:8080/api/v1/crm/contacts \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com","phone":"+1234567890"}'

# List contacts
curl http://localhost:8080/api/v1/crm/contacts \
  -H "Authorization: Bearer <token>"
```

### Test 2.2: Contact Validation
```bash
# Run property test
docker compose exec php-fpm php modular_core/tests/Properties/ContactValidationTest.php
```

### Test 2.3: Lead Management
```bash
# Create lead
curl -X POST http://localhost:8080/api/v1/crm/leads \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane Smith","email":"jane@example.com","source":"website"}'

# Convert lead
curl -X POST http://localhost:8080/api/v1/crm/leads/1/convert \
  -H "Authorization: Bearer <token>"
```

### Test 2.4: Lead Scoring (AI)
```bash
# Test AI endpoint
curl -X POST http://localhost:8000/predict/lead-score \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":"test-tenant","lead_data":{"source":"website","engagement":5}}'

# Expected: {"result":{"score":75},"confidence":0.85,"model_version":"1.0"}
```

### Test 2.5: Deal Management
```bash
# Create deal
curl -X POST http://localhost:8080/api/v1/crm/deals \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name":"Big Deal","value":50000,"stage":"qualification"}'

# Get forecast
curl http://localhost:8080/api/v1/crm/pipelines/1/forecast \
  -H "Authorization: Bearer <token>"
```

### Test 2.6: Workflow Automation
```bash
# Create workflow
curl -X POST http://localhost:8080/api/v1/crm/workflows \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name":"Welcome Email","trigger":"lead.created","actions":[{"type":"send_email"}]}'
```

---

## Phase 3: ERP Module ✅

### Test 3.1: General Ledger
```bash
# Run double-entry balance test
docker compose exec php-fpm php modular_core/tests/Properties/DoubleEntryBalanceTest.php

# Create journal entry
curl -X POST http://localhost:8080/api/v1/erp/journal-entries \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"lines":[{"account":"1000","debit":1000},{"account":"2000","credit":1000}]}'
```

### Test 3.2: Voucher Code Assignment
```bash
# Run property test
docker compose exec php-fpm php modular_core/tests/Properties/VoucherCodeAssignmentTest.php
```

### Test 3.3: Company Code Isolation
```bash
# Run property test
docker compose exec php-fpm php modular_core/tests/Properties/CompanyCodeQueryIsolationTest.php
```

### Test 3.4: Period Close
```bash
# Run property test
docker compose exec php-fpm php modular_core/tests/Properties/ClosedPeriodImmutabilityTest.php

# Close period
curl -X POST http://localhost:8080/api/v1/erp/periods/202403/close \
  -H "Authorization: Bearer <token>"
```

### Test 3.5: Invoicing
```bash
# Create invoice
curl -X POST http://localhost:8080/api/v1/erp/invoices \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"customer_id":1,"items":[{"description":"Service","amount":1000}]}'
```

### Test 3.6: Inventory Management
```bash
# Record stock movement
curl -X POST http://localhost:8080/api/v1/erp/inventory/movements \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"item_id":1,"quantity":10,"type":"in","warehouse_id":1}'
```

---

## Phase 4: Accounting Module ✅

### Test 4.1: Chart of Accounts
```bash
# List COA
curl http://localhost:8080/api/v1/accounting/coa \
  -H "Authorization: Bearer <token>"

# Create account
curl -X POST http://localhost:8080/api/v1/accounting/coa \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"code":"1000","name":"Cash","type":"asset"}'
```

### Test 4.2: Voucher Engine
```bash
# Create voucher
curl -X POST http://localhost:8080/api/v1/accounting/vouchers \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"fin_period":"202403","lines":[{"account":"1000","debit":1000},{"account":"2000","credit":1000}]}'
```

### Test 4.3: Multi-Currency & FX
```bash
# Run FX tests
docker compose exec php-fpm php modular_core/tests/Properties/RealizedFXGainLossTest.php
docker compose exec php-fpm php modular_core/tests/Properties/FXRevaluationRoundTripTest.php

# Get exchange rate
curl http://localhost:8080/api/v1/accounting/fx/rates/USD/2024-03-19 \
  -H "Authorization: Bearer <token>"
```

### Test 4.4: Monetary Precision
```bash
# Run property test
docker compose exec php-fpm php modular_core/tests/Properties/MonetaryAmountPrecisionTest.php
```

### Test 4.5: Fixed Assets
```bash
# Create asset
curl -X POST http://localhost:8080/api/v1/accounting/assets \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name":"Computer","cost":2000,"category":"equipment","useful_life":5}'

# Run depreciation
curl -X POST http://localhost:8080/api/v1/accounting/assets/depreciation/run \
  -H "Authorization: Bearer <token>"
```

### Test 4.6: Payroll
```bash
# Run payroll
curl -X POST http://localhost:8080/api/v1/accounting/payroll/run \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"period":"202403","company_code":"01"}'
```

### Test 4.7: Financial Statements
```bash
# Trial balance
curl http://localhost:8080/api/v1/accounting/reports/trial-balance?period=202403 \
  -H "Authorization: Bearer <token>"

# P&L Statement
curl http://localhost:8080/api/v1/accounting/reports/profit-loss?period=202403 \
  -H "Authorization: Bearer <token>"

# Balance Sheet
curl http://localhost:8080/api/v1/accounting/reports/balance-sheet?period=202403 \
  -H "Authorization: Bearer <token>"
```

---

## Phase 5: Platform Core Services ✅

### Test 5.1: WebSocket Notifications
```bash
# Check WebSocket server
# Open browser console at http://localhost
# Run: new WebSocket('ws://localhost:8080/ws?token=<your-token>')

# Send test notification
curl -X POST http://localhost:8080/api/v1/platform/notifications/test \
  -H "Authorization: Bearer <token>"
```

### Test 5.2: Audit Logging
```bash
# View audit log
curl http://localhost:8080/api/v1/platform/audit \
  -H "Authorization: Bearer <token>"

# Filter by user
curl "http://localhost:8080/api/v1/platform/audit?user_id=1&date_from=2024-03-01" \
  -H "Authorization: Bearer <token>"

# Export as PDF
curl http://localhost:8080/api/v1/platform/audit/export \
  -H "Authorization: Bearer <token>" \
  -o audit_log.pdf
```

### Test 5.3: Webhook Management
```bash
# Create webhook
curl -X POST http://localhost:8080/api/v1/platform/webhooks \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Webhook","url":"https://webhook.site/unique-id","events":["lead.created","deal.won"]}'

# List webhooks
curl http://localhost:8080/api/v1/platform/webhooks \
  -H "Authorization: Bearer <token>"

# View delivery history
curl http://localhost:8080/api/v1/platform/webhooks/1/deliveries \
  -H "Authorization: Bearer <token>"
```

### Test 5.4: Global Search
```bash
# Search across all modules
curl "http://localhost:8080/api/v1/platform/search?q=john" \
  -H "Authorization: Bearer <token>"
```

### Test 5.5: Internationalization
```bash
# Test Arabic UI
# Open http://localhost
# Change language to Arabic in settings
# Verify RTL layout and Arabic text

# Test bilingual PDF
curl http://localhost:8080/api/v1/accounting/vouchers/1/pdf \
  -H "Authorization: Bearer <token>" \
  -o voucher_bilingual.pdf
```

### Test 5.6: SaaS Billing
```bash
# Create subscription
curl -X POST http://localhost:8080/api/v1/platform/billing/subscribe \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"plan_id":"price_xxx","seats":5}'

# Check subscription status
curl http://localhost:8080/api/v1/platform/billing/subscription \
  -H "Authorization: Bearer <token>"
```

---

## Phase 6: AI Engine ✅

### Test 6.1: Lead Scoring
```bash
curl -X POST http://localhost:8000/predict/lead-score \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":"test","lead_data":{"source":"referral","engagement":8}}'
```

### Test 6.2: Win Probability
```bash
curl -X POST http://localhost:8000/predict/win-probability \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":"test","deal_data":{"stage":"proposal","value":50000,"age_days":30}}'
```

### Test 6.3: Churn Prediction
```bash
curl -X POST http://localhost:8000/predict/churn \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":"test","account_data":{"last_activity_days":45,"support_tickets":3}}'
```

### Test 6.4: Sentiment Analysis
```bash
curl -X POST http://localhost:8000/predict/sentiment \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":"test","text":"This product is amazing! Very satisfied."}'
```

### Test 6.5: Semantic Search
```bash
curl -X POST http://localhost:8000/search/semantic \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":"test","query":"customer complaints about billing"}'
```

### Test 6.6: Accounting AI
```bash
# Anomaly detection
curl -X POST http://localhost:8000/accounting/anomaly-detect \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":"test","voucher_data":{"amount":1000000,"account":"1000"}}'

# Duplicate check
curl -X POST http://localhost:8000/accounting/duplicate-check \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":"test","vendor":"ABC Corp","amount":5000,"date":"2024-03-19"}'

# Account suggestion
curl -X POST http://localhost:8000/accounting/account-suggest \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":"test","description":"Office rent payment"}'
```

---

## Phase 7: Cross-Cutting Concerns ✅

### Test 7.1: Performance & Caching
```bash
# Check Redis cache
docker compose exec redis redis-cli -a secret

# Get cached permissions
GET permissions:test-tenant:1

# Check cache hit rate
INFO stats
```

### Test 7.2: Security
```bash
# Test CSRF protection
curl -X POST http://localhost:8080/api/v1/crm/contacts \
  -H "Content-Type: application/json" \
  -d '{"name":"Test"}'
# Expected: 403 Forbidden (no CSRF token)

# Test XSS prevention
curl -X POST http://localhost:8080/api/v1/crm/contacts \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name":"<script>alert(1)</script>"}'
# Expected: Input sanitized
```

### Test 7.3: Data Export
```bash
# Request full export
curl -X POST http://localhost:8080/api/v1/platform/export \
  -H "Authorization: Bearer <token>"

# Check export status
curl http://localhost:8080/api/v1/platform/export/status \
  -H "Authorization: Bearer <token>"
```

---

## Celery Background Tasks

### Check Celery Worker
```bash
# View Celery logs
docker compose logs celery-worker

# List active tasks
docker compose exec celery-worker celery -A app.celery_app inspect active

# List scheduled tasks
docker compose exec celery-worker celery -A app.celery_app inspect scheduled
```

### Test Background Jobs
```bash
# Trigger depreciation task
docker compose exec celery-worker celery -A app.celery_app call depreciation.run

# Trigger notification cleanup
docker compose exec celery-worker celery -A app.celery_app call notification.cleanup

# Trigger webhook delivery
docker compose exec celery-worker celery -A app.celery_app call webhook.deliver
```

---

## RabbitMQ Message Queue

### Check RabbitMQ
```bash
# List queues
docker compose exec rabbitmq rabbitmqctl list_queues

# List exchanges
docker compose exec rabbitmq rabbitmqctl list_exchanges

# View RabbitMQ UI
# Open http://localhost:15672
# Login: nexsaas / secret
```

---

## Integration Tests

### End-to-End Workflow Test
```bash
# 1. Create lead
# 2. Score lead (AI)
# 3. Convert to contact
# 4. Create deal
# 5. Predict win probability (AI)
# 6. Move through pipeline
# 7. Win deal
# 8. Create invoice
# 9. Post to GL
# 10. Generate financial statements

# Run integration test script
./run_integration_tests.sh
```

---

## Performance Tests

### Load Testing
```bash
# Install Apache Bench
sudo apt-get install apache2-utils

# Test API endpoint
ab -n 1000 -c 10 http://localhost:8080/api/v1/health

# Test with authentication
ab -n 1000 -c 10 -H "Authorization: Bearer <token>" \
  http://localhost:8080/api/v1/crm/contacts
```

---

## Summary Checklist

- [ ] Phase 1: Platform Foundation (6 tests)
- [ ] Phase 2: CRM Module (6 tests)
- [ ] Phase 3: ERP Module (6 tests)
- [ ] Phase 4: Accounting Module (7 tests)
- [ ] Phase 5: Platform Core Services (6 tests)
- [ ] Phase 6: AI Engine (6 tests)
- [ ] Phase 7: Cross-Cutting Concerns (3 tests)
- [ ] Celery Background Tasks (3 tests)
- [ ] RabbitMQ Message Queue (2 tests)
- [ ] Integration Tests (1 test)
- [ ] Performance Tests (2 tests)

**Total: 48 test scenarios**

---

## Automated Test Runner

See `run_tests.sh` for automated test execution.
