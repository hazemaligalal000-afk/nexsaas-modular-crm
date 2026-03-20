# Implementation Plan: AI Engine and Billing System

## Overview

This implementation plan covers Phase 1 of NexSaaS: the AI Engine (Python FastAPI), Billing System (Stripe integration), and Real-time WebSockets (Soketi). The plan is organized by component with clear dependencies and checkpoints. Tasks are designed to be executed incrementally, with each task building on previous work. The implementation follows a 21-day timeline with three major components executed in parallel where possible.

## Tasks

### Component 1: AI Engine Foundation (Python FastAPI)

- [ ] 1. Set up AI Engine project structure and authentication
  - [ ] 1.1 Initialize FastAPI project with Python 3.11+ and create directory structure
    - Create ai_engine/ directory with main.py, config.py, auth.py, rate_limiter.py
    - Set up virtual environment and install dependencies (fastapi, uvicorn, pydantic, openai, anthropic, python-jose, structlog)
    - Create models/, services/, clients/, utils/ subdirectories
    - _Requirements: 6.1, 6.2_

  - [ ] 1.2 Implement JWT authentication middleware
    - Create auth.py with JWT validation using python-jose
    - Validate JWT signature, issuer ("nexsaas-platform"), and audience ("ai-engine")
    - Extract tenant_id from JWT claims and attach to request state
    - Return HTTP 401 for invalid/missing tokens or tenant_id mismatch
    - _Requirements: 6.3, 6.4_

  - [ ]* 1.3 Write property test for JWT authentication enforcement
    - **Property 13: JWT Authentication Enforcement**
    - **Validates: Requirements 6.3, 6.4**

  - [ ] 1.4 Set up structured JSON logging with tenant_id, user_id, endpoint, duration_ms, status_code
    - Create utils/logger.py using structlog
    - Configure JSON formatter with timestamp, log level, and request context
    - Add logging middleware to capture all requests and responses
    - _Requirements: 6.8_

  - [ ]* 1.5 Write property test for request logging completeness
    - **Property 15: Request Logging Completeness**
    - **Validates: Requirements 6.8**


- [ ] 2. Implement external AI API clients with retry logic
  - [ ] 2.1 Create OpenAI client wrapper with timeout and retry configuration
    - Create clients/openai_client.py with OpenAI SDK integration
    - Read OPENAI_API_KEY from environment variables
    - Implement retry logic: 3 total attempts (initial + 2 retries) with exponential backoff (1s, 2s, 4s)
    - Set 10-second timeout for all API calls
    - Retry on transient errors: HTTP 429, 500, 503, TimeoutError, ConnectionError
    - _Requirements: 7.1, 7.3_

  - [ ] 2.2 Create Anthropic client wrapper with timeout and retry configuration
    - Create clients/anthropic_client.py with Anthropic SDK integration
    - Read ANTHROPIC_API_KEY from environment variables
    - Implement same retry logic as OpenAI client
    - _Requirements: 7.1, 7.3_

  - [ ]* 2.3 Write property test for external API retry behavior
    - **Property 16: External API Retry Behavior**
    - **Validates: Requirements 7.3**

  - [ ]* 2.4 Write property test for retry exhaustion error response
    - **Property 17: Retry Exhaustion Error Response**
    - **Validates: Requirements 7.4**

  - [ ] 2.5 Implement per-tenant rate limiting (100 requests per minute)
    - Create rate_limiter.py using slowapi library
    - Use tenant_id from request state as rate limit key
    - Return HTTP 429 with Retry-After header when limit exceeded
    - _Requirements: 6.6, 6.7_

  - [ ]* 2.6 Write property test for rate limiting enforcement
    - **Property 14: Rate Limiting Enforcement**
    - **Validates: Requirements 6.6, 6.7**

- [ ] 3. Implement lead scoring engine
  - [ ] 3.1 Create Pydantic models for lead scoring requests and responses
    - Create models/requests.py with LeadData and LeadScoreRequest models
    - Create models/responses.py with ScoringFactor and LeadScoreResponse models
    - Add field validation: score (0-100), confidence (0.0-1.0)
    - _Requirements: 1.1, 1.3_

  - [ ] 3.2 Implement lead scoring algorithm with weighted signals
    - Create services/lead_scorer.py with LeadScorer class
    - Implement demographic scoring (30% weight): email domain quality, company size, industry match
    - Implement behavioral scoring (40% weight): website visits, email engagement, form submissions
    - Implement engagement scoring (20% weight): recency decay based on days_since_last_activity
    - Implement pipeline scoring (10% weight): current stage weighting
    - Calculate final score with recency decay factor
    - Calculate confidence based on data completeness (available signals / total signals)
    - Return top 3 contributing factors with weights
    - _Requirements: 1.2, 1.4_

  - [ ]* 3.3 Write property test for lead score range invariant
    - **Property 2: Lead Score Range Invariant**
    - **Validates: Requirements 1.2**

  - [ ] 3.4 Create POST /api/v1/leads/score endpoint
    - Add endpoint in main.py with LeadScoreRequest validation
    - Call LeadScorer service and return LeadScoreResponse
    - Ensure response time < 3 seconds
    - Log request with tenant_id, lead_id, score, confidence, duration_ms
    - _Requirements: 1.1, 1.2, 1.3_

  - [ ]* 3.5 Write property test for AI Engine response schema completeness
    - **Property 1: AI Engine Response Schema Completeness**
    - **Validates: Requirements 1.3**

  - [ ]* 3.6 Write unit tests for lead scoring edge cases
    - Test high engagement scenario (score >= 80)
    - Test low engagement scenario (score <= 30)
    - Test missing data fields (confidence should decrease)
    - Test recency decay (old leads score lower)

- [ ] 4. Implement intent detection engine
  - [ ] 4.1 Create Pydantic models for intent detection requests and responses
    - Create IntentCategory enum: buying_intent, churn_risk, support_request, neutral
    - Create SenderContext and IntentDetectionRequest models
    - Create IntentDetectionResponse model with intent, confidence, reasoning, model_version
    - _Requirements: 3.1, 3.3_

  - [ ] 4.2 Implement intent classification using Anthropic Claude
    - Create services/intent_detector.py with IntentDetector class
    - Create prompt template with message text and sender context
    - Use Claude model: claude-3-sonnet-20240229 with temperature 0.1
    - Parse JSON response and validate intent enum
    - _Requirements: 3.2, 7.2_

  - [ ] 4.3 Create POST /api/v1/messages/detect-intent endpoint
    - Add endpoint in main.py with IntentDetectionRequest validation
    - Call IntentDetector service and return IntentDetectionResponse
    - Ensure response time < 2 seconds
    - Log request with tenant_id, message_id, intent, confidence, duration_ms
    - _Requirements: 3.1, 3.2, 3.3_

  - [ ]* 4.4 Write property test for intent classification enum constraint
    - **Property 7: Intent Classification Enum Constraint**
    - **Validates: Requirements 3.2**

  - [ ]* 4.5 Write unit tests for intent detection scenarios
    - Test buying_intent detection (explicit purchase language)
    - Test churn_risk detection (cancellation language)
    - Test support_request detection (help/question language)
    - Test neutral classification (general conversation)

- [ ] 5. Checkpoint - Verify AI Engine core functionality
  - Ensure all tests pass (unit and property tests)
  - Test lead scoring endpoint with sample data
  - Test intent detection endpoint with sample messages
  - Verify JWT authentication blocks unauthorized requests
  - Verify rate limiting triggers at 101 requests/minute
  - Ask the user if questions arise.


### Component 2: AI Engine Content Generation

- [ ] 6. Implement content generation and action suggestions
  - [ ] 6.1 Create Pydantic models for content generation
    - Create TonePreset enum: professional, friendly, concise
    - Create ConversationMessage and ContentGenerationRequest models
    - Create ContentGenerationResponse model with draft_text, confidence, model_version
    - _Requirements: 4.1, 4.3_

  - [ ] 6.2 Implement AI reply generation using OpenAI GPT-4
    - Create services/content_generator.py with ContentGenerator class
    - Create prompt template with message text, conversation history, and tone
    - Use GPT-4 model: gpt-4-turbo-preview with temperature 0.7
    - Set max_tokens to 500
    - _Requirements: 4.2, 7.2_

  - [ ] 6.3 Create POST /api/v1/content/generate-reply endpoint
    - Add endpoint in main.py with ContentGenerationRequest validation
    - Call ContentGenerator service and return ContentGenerationResponse
    - Ensure response time < 4 seconds
    - Log request with tenant_id, message_id, tone, duration_ms
    - _Requirements: 4.1, 4.2, 4.3_

  - [ ]* 6.4 Write unit tests for content generation
    - Test professional tone output
    - Test friendly tone output
    - Test concise tone output
    - Test conversation history context usage

  - [ ] 6.5 Create Pydantic models for action suggestions
    - Create ActionType enum: schedule_demo, send_pricing, follow_up, create_deal, escalate
    - Create ActionPriority enum: high, medium, low
    - Create SuggestedAction, ContactContext, ActionSuggestionRequest, ActionSuggestionResponse models
    - _Requirements: 5.1, 5.3_

  - [ ] 6.6 Implement follow-up action suggestions using Claude
    - Create services/action_suggester.py with ActionSuggester class
    - Create prompt template with conversation summary and contact context
    - Use Claude model to generate up to 3 suggested actions
    - Include action_type, description, priority, due_date_suggestion for each action
    - _Requirements: 5.2, 5.3_

  - [ ] 6.7 Create POST /api/v1/content/suggest-actions endpoint
    - Add endpoint in main.py with ActionSuggestionRequest validation
    - Call ActionSuggester service and return ActionSuggestionResponse
    - Ensure response time < 3 seconds
    - _Requirements: 5.1, 5.2, 5.3_

  - [ ]* 6.8 Write property test for action suggestion count limit
    - **Property 11: Action Suggestion Count Limit**
    - **Validates: Requirements 5.2**

- [ ] 7. Implement AI usage tracking
  - [ ] 7.1 Create usage tracking service
    - Create utils/metrics.py with UsageTracker class
    - Track per-tenant: total_requests, requests_by_endpoint, total_tokens_consumed, estimated_cost_usd
    - Store usage data in Redis with billing_period_start and billing_period_end keys
    - _Requirements: 7.5_

  - [ ] 7.2 Add usage tracking to all AI Engine endpoints
    - Increment request counter after each successful request
    - Track token usage from OpenAI/Anthropic API responses
    - Calculate cost estimate based on model pricing
    - _Requirements: 7.5_

  - [ ] 7.3 Create GET /api/v1/usage endpoint
    - Add endpoint in main.py to retrieve usage statistics
    - Return per-tenant usage for current billing period
    - Include breakdown by endpoint and total cost estimate
    - _Requirements: 7.6_

  - [ ]* 7.4 Write property test for AI usage tracking
    - **Property 18: AI Usage Tracking**
    - **Validates: Requirements 7.5**

- [ ] 8. Implement error handling and circuit breaker
  - [ ] 8.1 Create standardized error response format
    - Create models/errors.py with ErrorResponse model
    - Include error code, message, details, optional retry_after
    - Add exception handlers for all error types (401, 403, 400, 429, 503, 500)
    - _Requirements: 6.4, 6.7, 7.4_

  - [ ] 8.2 Implement circuit breaker for external AI APIs
    - Add circuit breaker logic in clients/openai_client.py and clients/anthropic_client.py
    - Open circuit if error rate > 50% over 5 minutes
    - Half-open after 60 seconds to test recovery
    - Close circuit if test request succeeds
    - _Requirements: 7.3, 7.4_

  - [ ]* 8.3 Write unit tests for error handling
    - Test 401 response for invalid JWT
    - Test 429 response for rate limit exceeded
    - Test 503 response for external API failure
    - Test circuit breaker opens after repeated failures

- [ ] 9. Checkpoint - Verify AI Engine completeness
  - Ensure all 4 endpoints are functional: /leads/score, /messages/detect-intent, /content/generate-reply, /content/suggest-actions
  - Verify usage tracking records requests and tokens
  - Test error handling for all error scenarios
  - Run full property test suite for AI Engine (Properties 1, 2, 7, 11, 13-18)
  - Ask the user if questions arise.


### Component 3: Billing System Core (PHP Laravel)

- [ ] 10. Set up billing database schema and Stripe integration
  - [ ] 10.1 Create database migrations for billing tables
    - Create migration to add Stripe fields to tenants table: stripe_customer_id, stripe_subscription_id, current_tier, billing_period_start, billing_period_end, subscription_status, grace_period_start, grace_period_end, access_locked_at, last_score_run
    - Create subscriptions table migration with tenant_id, stripe_subscription_id, stripe_customer_id, tier, status, current_period_start, current_period_end, cancel_at_period_end, canceled_at
    - Create invoices table migration with tenant_id, stripe_invoice_id, invoice_number, amount_due, amount_paid, tax_amount, currency, status, invoice_pdf_url, due_date, paid_at
    - Create usage_records table migration with tenant_id, resource_type, quantity, recorded_at, billing_period_start, billing_period_end, synced_to_stripe
    - Create webhook_events table migration with stripe_event_id, event_type, payload, processed, processing_error, processed_at
    - Run migrations
    - _Requirements: 8.7, 11.9, 12.2_

  - [ ] 10.2 Create Eloquent models for billing entities
    - Create Subscription model with relationships, casts, and helper methods (isActive, isInGracePeriod, daysUntilExpiration)
    - Create Invoice model with amount formatting methods
    - Create UsageRecord model with tenant relationship
    - Create WebhookEvent model
    - _Requirements: 8.7, 11.9_

  - [ ] 10.3 Configure Stripe SDK and pricing tiers
    - Install Stripe PHP SDK via Composer
    - Create config/stripe.php with API keys and webhook secret
    - Create PricingTier class with tier definitions: Starter ($49/mo, 5 users, 10k AI requests), Growth ($149/mo, 25 users, 50k AI requests), Enterprise ($499/mo, unlimited users, 200k AI requests)
    - Store Stripe Price IDs in .env for each tier
    - _Requirements: 8.1_

  - [ ]* 10.4 Write unit tests for Eloquent models
    - Test Subscription isActive() method
    - Test Subscription isInGracePeriod() method
    - Test Invoice amount formatting
    - Test model relationships

- [ ] 11. Implement subscription checkout and activation
  - [ ] 11.1 Create StripeService wrapper for Stripe API calls
    - Create modular_core/modules/Platform/Billing/StripeService.php
    - Implement createCheckoutSession method
    - Implement updateSubscription method
    - Implement cancelSubscription method
    - Add error handling for all Stripe exception types
    - _Requirements: 8.3, 8.4, 9.2, 10.2_

  - [ ] 11.2 Create POST /api/v1/billing/checkout endpoint
    - Create BillingController with checkout method
    - Validate tenant_id and tier (enum: starter, growth, enterprise)
    - Call StripeService to create Checkout Session with selected tier's Price ID
    - Include tenant_id and tier in session metadata
    - Return checkout URL and session_id
    - _Requirements: 8.3, 8.4_

  - [ ]* 11.3 Write property test for checkout session URL generation
    - **Property 20: Checkout Session URL Generation**
    - **Validates: Requirements 8.4**

  - [ ] 11.4 Create Stripe webhook endpoint POST /webhooks/stripe
    - Create WebhookHandler with handle method
    - Verify webhook signature using Stripe webhook secret
    - Return HTTP 400 for invalid signatures
    - Check for duplicate events using webhook_events table (idempotency)
    - Return HTTP 200 with "already_processed" for duplicates
    - Route events to specific handlers based on event type
    - _Requirements: 11.1, 11.2, 11.3, 11.8_

  - [ ]* 11.5 Write property test for webhook signature validation
    - **Property 28: Webhook Signature Validation**
    - **Validates: Requirements 11.2, 11.3**

  - [ ]* 11.6 Write property test for webhook event idempotency
    - **Property 29: Webhook Event Idempotency**
    - **Validates: Requirements 11.4**

  - [ ] 11.7 Implement checkout.session.completed webhook handler
    - Create handleCheckoutCompleted method in WebhookHandler
    - Retrieve session data from Stripe
    - Create Stripe Customer record if first subscription
    - Create Subscription record with tenant_id, stripe_subscription_id, stripe_customer_id, tier, status='active'
    - Update tenant with stripe_customer_id, stripe_subscription_id, current_tier, subscription_status='active', access_locked_at=null
    - Send welcome email to tenant owner
    - _Requirements: 8.2, 8.5, 8.6, 8.7_

  - [ ]* 11.8 Write property test for Stripe customer creation
    - **Property 19: Stripe Customer Creation**
    - **Validates: Requirements 8.2**

  - [ ]* 11.9 Write property test for subscription activation on checkout completion
    - **Property 21: Subscription Activation on Checkout Completion**
    - **Validates: Requirements 8.6**

- [ ] 12. Checkpoint - Verify subscription creation flow
  - Test checkout endpoint creates valid Stripe Checkout Session
  - Simulate checkout.session.completed webhook and verify subscription created
  - Verify tenant access activated (access_locked_at = null)
  - Run property tests for checkout and activation (Properties 19-21, 28-29)
  - Ask the user if questions arise.


### Component 4: Subscription Management

- [ ] 13. Implement subscription tier changes
  - [ ] 13.1 Create SubscriptionManager service for subscription lifecycle
    - Create modular_core/modules/Platform/Billing/SubscriptionManager.php
    - Implement upgradeTier method with immediate proration
    - Implement downgradeTier method with end-of-period scheduling
    - Implement cancelSubscription method with cancel_at_period_end option
    - _Requirements: 9.2, 9.3, 10.2, 10.3_

  - [ ] 13.2 Create POST /api/v1/billing/change-tier endpoint
    - Add changeTier method to BillingController
    - Validate new_tier parameter
    - Check if upgrade or downgrade
    - For upgrades: call SubscriptionManager.upgradeTier and update tenant.current_tier immediately
    - For downgrades: check user count against new tier limit, reject if over limit
    - For downgrades: schedule tier change for end of billing period
    - Return updated subscription details with effective_date
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [ ]* 13.3 Write property test for tier upgrade immediate effect
    - **Property 22: Tier Upgrade Immediate Effect**
    - **Validates: Requirements 9.2**

  - [ ]* 13.4 Write property test for tier downgrade deferred effect
    - **Property 23: Tier Downgrade Deferred Effect**
    - **Validates: Requirements 9.3**

  - [ ]* 13.5 Write property test for downgrade user limit enforcement
    - **Property 24: Downgrade User Limit Enforcement**
    - **Validates: Requirements 9.4**

  - [ ] 13.6 Create POST /api/v1/billing/cancel endpoint
    - Add cancel method to BillingController
    - Validate cancel_at_period_end parameter (boolean)
    - If true: schedule cancellation at end of period, keep subscription active
    - If false: cancel immediately, set subscription status to 'canceled', set access_locked_at
    - Update tenant subscription_status and access_locked_at
    - _Requirements: 10.1, 10.2, 10.3, 10.4_

  - [ ]* 13.7 Write property test for scheduled cancellation
    - **Property 25: Scheduled Cancellation**
    - **Validates: Requirements 10.2**

  - [ ]* 13.8 Write property test for immediate cancellation and lockout
    - **Property 26: Immediate Cancellation and Lockout**
    - **Validates: Requirements 10.3**

- [ ] 14. Implement webhook handlers for subscription events
  - [ ] 14.1 Implement invoice.payment_succeeded webhook handler
    - Create handlePaymentSucceeded method in WebhookHandler
    - Update subscription status to 'active'
    - Clear grace_period_start and grace_period_end on tenant
    - Clear access_locked_at on tenant
    - _Requirements: 11.5_

  - [ ]* 14.2 Write property test for payment success state transition
    - **Property 30: Payment Success State Transition**
    - **Validates: Requirements 11.5**

  - [ ] 14.3 Implement invoice.payment_failed webhook handler
    - Create handlePaymentFailed method in WebhookHandler
    - Set subscription status to 'past_due'
    - Set grace_period_start to current time
    - Set grace_period_end to 7 days later
    - Send payment failed email to tenant owner
    - Schedule payment retry jobs for days 3, 5, 7
    - _Requirements: 11.6, 13.1_

  - [ ]* 14.4 Write property test for payment failure grace period initiation
    - **Property 31: Payment Failure Grace Period Initiation**
    - **Validates: Requirements 11.6, 13.1**

  - [ ] 14.5 Implement customer.subscription.updated webhook handler
    - Create handleSubscriptionUpdated method in WebhookHandler
    - Update subscription record with new status, current_period_start, current_period_end
    - Update tenant current_tier if tier changed
    - _Requirements: 11.4_

  - [ ] 14.6 Implement customer.subscription.deleted webhook handler
    - Create handleSubscriptionDeleted method in WebhookHandler
    - Set subscription status to 'canceled'
    - Set tenant subscription_status to 'canceled'
    - Set tenant access_locked_at to current time
    - _Requirements: 11.7_

  - [ ]* 14.7 Write property test for subscription deletion lockout
    - **Property 32: Subscription Deletion Lockout**
    - **Validates: Requirements 11.7**

  - [ ] 14.8 Implement invoice.created webhook handler
    - Create handleInvoiceCreated method in WebhookHandler
    - Retrieve invoice PDF URL from Stripe
    - Create Invoice record with tenant_id, stripe_invoice_id, invoice_number, amounts, status, invoice_pdf_url
    - Send invoice email to tenant owner with PDF attachment
    - _Requirements: 12.1, 12.2, 12.3_

  - [ ]* 14.9 Write property test for invoice PDF storage
    - **Property 34: Invoice PDF Storage**
    - **Validates: Requirements 12.2**

  - [ ]* 14.10 Write property test for invoice email delivery
    - **Property 35: Invoice Email Delivery**
    - **Validates: Requirements 12.3, 12.4**

  - [ ]* 14.11 Write property test for webhook event audit logging
    - **Property 33: Webhook Event Audit Logging**
    - **Validates: Requirements 11.9**

- [ ] 15. Checkpoint - Verify subscription management
  - Test tier upgrade applies immediately with proration
  - Test tier downgrade schedules for end of period
  - Test downgrade rejection when user count exceeds new limit
  - Test immediate and scheduled cancellation
  - Test all webhook handlers with sample Stripe events
  - Run property tests for subscription management (Properties 22-26, 30-35)
  - Ask the user if questions arise.


### Component 5: Grace Period and Tenant Lockout

- [ ] 16. Implement grace period and payment retry logic
  - [ ] 16.1 Create GracePeriodService for grace period management
    - Create modular_core/modules/Platform/Billing/GracePeriodService.php
    - Implement startGracePeriod method (set grace_period_start, grace_period_end = +7 days)
    - Implement clearGracePeriod method (set both to null)
    - Implement checkGracePeriodExpiration method (compare current time to grace_period_end)
    - Implement scheduleGracePeriodReminders method (schedule emails for days 1, 3, 5, 7)
    - _Requirements: 13.1, 13.2, 13.3, 13.5, 13.6_

  - [ ] 16.2 Create grace period email notification templates
    - Create email template for payment failed notification (day 1)
    - Create email template for grace period reminders (days 3, 5, 7)
    - Include days remaining, payment update link, and support contact
    - _Requirements: 13.3_

  - [ ] 16.3 Implement payment retry job
    - Create RetryPayment job class
    - Call Stripe API to retry invoice payment
    - If successful: trigger handlePaymentSucceeded logic
    - If failed: log error and continue grace period
    - Schedule retries for days 3, 5, 7 after payment failure
    - _Requirements: 13.4_

  - [ ]* 16.4 Write property test for grace period email reminders
    - **Property 36: Grace Period Email Reminders**
    - **Validates: Requirements 13.3**

  - [ ]* 16.5 Write property test for grace period payment success recovery
    - **Property 37: Grace Period Payment Success Recovery**
    - **Validates: Requirements 13.5**

  - [ ] 16.6 Create cron job to check for expired grace periods
    - Create CheckExpiredGracePeriods command
    - Query tenants where grace_period_end < now() and subscription_status = 'past_due'
    - For each: set subscription_status to 'unpaid', set access_locked_at to now()
    - Send access locked email notification
    - Schedule to run every hour
    - _Requirements: 13.6_

  - [ ]* 16.7 Write property test for grace period expiration lockout
    - **Property 38: Grace Period Expiration Lockout**
    - **Validates: Requirements 13.6**

- [ ] 17. Implement tenant lockout middleware
  - [ ] 17.1 Create TenantLockoutMiddleware
    - Create modular_core/modules/Platform/Billing/TenantLockoutMiddleware.php
    - Check tenant subscription_status on every request
    - Allow requests to /api/v1/billing/* endpoints even when locked
    - For locked tenants (status = canceled, past_due, unpaid): return HTTP 403 with error message and billing_url
    - Check if grace_period_end has passed: if yes, update status to 'unpaid' and set access_locked_at
    - _Requirements: 10.5, 14.1, 14.2, 14.3_

  - [ ] 17.2 Register TenantLockoutMiddleware in HTTP kernel
    - Add middleware to api middleware group
    - Ensure it runs after authentication middleware
    - _Requirements: 14.1_

  - [ ]* 17.3 Write property test for subscription status check on every request
    - **Property 39: Subscription Status Check on Every Request**
    - **Validates: Requirements 14.1**

  - [ ]* 17.4 Write property test for canceled tenant access restriction
    - **Property 27: Canceled Tenant Access Restriction**
    - **Validates: Requirements 10.5, 14.2**

  - [ ]* 17.5 Write property test for locked tenant billing endpoint access
    - **Property 40: Locked Tenant Billing Endpoint Access**
    - **Validates: Requirements 14.3**

  - [ ] 17.6 Implement subscription reactivation logic
    - Add reactivateSubscription method to SubscriptionManager
    - Clear access_locked_at when subscription status changes to 'active'
    - Update tenant subscription_status to 'active'
    - _Requirements: 14.4_

  - [ ]* 17.7 Write property test for subscription reactivation access restoration
    - **Property 41: Subscription Reactivation Access Restoration**
    - **Validates: Requirements 14.4**

- [ ] 18. Implement usage metering and billing history
  - [ ] 18.1 Create UsageMeteringService for tracking AI usage
    - Create modular_core/modules/Platform/Billing/UsageMeteringService.php
    - Implement recordAIRequest method (create UsageRecord with resource_type='ai_requests', quantity=1)
    - Implement getCurrentUsage method (sum usage for current billing period)
    - Implement reportUsageToStripe method (for Enterprise tier overage)
    - _Requirements: 7.5_

  - [ ] 18.2 Add usage tracking to AI Engine integration
    - After each successful AI Engine API call, call UsageMeteringService.recordAIRequest
    - Check if tenant is on Enterprise tier and over included limit
    - If over limit: report overage to Stripe for metered billing
    - _Requirements: 7.5_

  - [ ] 18.3 Create GET /api/v1/billing/usage endpoint
    - Add usage method to BillingController
    - Call UsageMeteringService.getCurrentUsage
    - Return usage breakdown: ai_requests (included, used, remaining, overage), storage_gb, active_users
    - Include estimated_overage_charges for Enterprise tier
    - _Requirements: 7.6_

  - [ ] 18.4 Create GET /api/v1/billing/invoices endpoint
    - Add invoices method to BillingController
    - Query Invoice model for tenant with pagination
    - Return list of invoices with invoice_number, amounts, status, invoice_pdf_url, dates
    - _Requirements: 12.5_

  - [ ] 18.5 Create InvoiceService for invoice operations
    - Create modular_core/modules/Platform/Billing/InvoiceService.php
    - Implement sendInvoiceEmail method with PDF attachment
    - Include invoice number, amount, due date, billing history link in email
    - _Requirements: 12.3, 12.4_

- [ ] 19. Checkpoint - Verify grace period and lockout
  - Test grace period starts on payment failure
  - Test grace period reminders sent on days 1, 3, 5, 7
  - Test payment retry on days 3, 5, 7
  - Test grace period clears on successful payment
  - Test tenant lockout after grace period expires
  - Test locked tenants can still access billing endpoints
  - Test subscription reactivation restores access
  - Run property tests for grace period and lockout (Properties 27, 36-41)
  - Ask the user if questions arise.


### Component 6: Real-time WebSockets (Soketi)

- [ ] 20. Set up Soketi WebSocket server
  - [ ] 20.1 Create Soketi Docker configuration
    - Add Soketi service to docker-compose.yml
    - Configure ports 6001 (WebSocket) and 6002 (HTTP API)
    - Set environment variables: SOKETI_DEFAULT_APP_ID, SOKETI_DEFAULT_APP_KEY, SOKETI_DEFAULT_APP_SECRET
    - Configure CORS to allow platform frontend domain
    - _Requirements: 16.1, 16.2, 16.3, 16.4_

  - [ ] 20.2 Configure Nginx SSL termination for WebSocket
    - Create Nginx configuration for ws.nexsaas.com
    - Configure SSL certificate and key
    - Set up WebSocket proxy to Soketi port 6001
    - Add WebSocket upgrade headers (Upgrade, Connection)
    - Set proxy_read_timeout to 86400 (24 hours)
    - _Requirements: 16.1_

  - [ ] 20.3 Configure Laravel Broadcasting for Soketi
    - Update config/broadcasting.php with Pusher driver
    - Set PUSHER_HOST, PUSHER_PORT, PUSHER_SCHEME in .env
    - Configure pusher connection with Soketi host and port
    - Set encrypted=true and useTLS based on scheme
    - _Requirements: 16.2, 16.3_

  - [ ] 20.4 Create health check endpoint for Soketi
    - Add monitoring script to check GET /health on Soketi
    - Configure Docker restart policy if health check fails
    - _Requirements: 16.5_

  - [ ]* 20.5 Write unit tests for Soketi configuration
    - Test Soketi container starts successfully
    - Test WebSocket connection to Soketi
    - Test SSL termination works

- [ ] 21. Implement WebSocket channel authorization
  - [ ] 21.1 Create channel authorization routes
    - Add routes in routes/channels.php for all channel types
    - Implement authorization for tenant.{tenantId}.messages (check user.tenant_id matches)
    - Implement authorization for tenant.{tenantId}.leads (check user.tenant_id matches)
    - Implement authorization for private-user.{userId} (check user.id matches)
    - Implement authorization for presence-tenant.{tenantId} (return user info: id, name, role, avatar_url)
    - Implement authorization for presence-conversation.{conversationId} (check conversation.tenant_id matches user.tenant_id)
    - _Requirements: 17.3, 18.3, 19.2, 20.2_

  - [ ]* 21.2 Write unit tests for channel authorization
    - Test authorized user can subscribe to tenant channels
    - Test unauthorized user cannot subscribe to other tenant's channels
    - Test presence channel returns correct user info

- [ ] 22. Implement message notification events
  - [ ] 22.1 Create MessageReceived broadcast event
    - Create app/Events/MessageReceived.php implementing ShouldBroadcast
    - Set channel to tenant.{tenant_id}.messages
    - Set event name to 'message.received'
    - Include payload: message_id, sender_name, sender_channel, preview_text, assigned_agent_id, timestamp
    - _Requirements: 17.1, 17.2_

  - [ ] 22.2 Trigger MessageReceived event on inbound message
    - In message processing logic, after creating Message record, dispatch MessageReceived event
    - Ensure event is published within 3 seconds of message arrival
    - _Requirements: 17.1_

  - [ ]* 22.3 Write property test for message notification event publishing
    - **Property 43: Message Notification Event Publishing**
    - **Validates: Requirements 17.1, 17.2**

  - [ ]* 22.4 Write unit tests for MessageReceived event
    - Test event broadcasts to correct channel
    - Test event payload contains all required fields
    - Test event is triggered when message is created

- [ ] 23. Implement lead status update events
  - [ ] 23.1 Create LeadUpdated broadcast event
    - Create app/Events/LeadUpdated.php implementing ShouldBroadcast
    - Set channel to tenant.{tenant_id}.leads
    - Set event name to 'lead.updated'
    - Include payload: lead_id, updated_fields (object with changed fields), updated_by_user_id, timestamp
    - _Requirements: 18.1, 18.2_

  - [ ] 23.2 Trigger LeadUpdated event on lead changes
    - In Lead model, add observer to detect status, score, or owner changes
    - Dispatch LeadUpdated event within 2 seconds of change
    - Include only changed fields in updated_fields payload
    - _Requirements: 1.7, 18.1_

  - [ ]* 23.3 Write property test for WebSocket event publishing on entity changes
    - **Property 42: WebSocket Event Publishing on Entity Changes**
    - **Validates: Requirements 1.7, 18.1, 18.2**

  - [ ]* 23.4 Write unit tests for LeadUpdated event
    - Test event broadcasts when lead status changes
    - Test event broadcasts when lead score changes
    - Test event broadcasts when lead owner changes
    - Test event includes only changed fields

- [ ] 24. Checkpoint - Verify WebSocket infrastructure
  - Test Soketi container is running and healthy
  - Test WebSocket connection from frontend to Soketi
  - Test channel authorization for all channel types
  - Test MessageReceived event publishes to correct channel
  - Test LeadUpdated event publishes to correct channel
  - Run property tests for WebSocket events (Properties 42-43)
  - Ask the user if questions arise.


### Component 7: Real-time Features (Typing, Presence, Seen Status)

- [ ] 25. Implement typing indicators
  - [ ] 25.1 Create TypingStarted and TypingStopped broadcast events
    - Create app/Events/TypingStarted.php for presence-conversation.{conversation_id} channel
    - Create app/Events/TypingStopped.php for presence-conversation.{conversation_id} channel
    - Include user_id and user_name in TypingStarted payload
    - Include user_id in TypingStopped payload
    - _Requirements: 19.2, 19.3_

  - [ ] 25.2 Create POST /api/v1/conversations/{conversationId}/typing endpoint
    - Add typing method to ConversationController
    - Accept status parameter: 'start' or 'stop'
    - Dispatch TypingStarted or TypingStopped event based on status
    - _Requirements: 19.2, 19.3_

  - [ ]* 25.3 Write unit tests for typing indicators
    - Test TypingStarted event broadcasts to presence channel
    - Test TypingStopped event broadcasts to presence channel
    - Test typing endpoint dispatches correct event

- [ ] 26. Implement message seen status
  - [ ] 26.1 Create MessageSeen broadcast event
    - Create app/Events/MessageSeen.php for presence-conversation.{conversation_id} channel
    - Set event name to 'message.seen'
    - Include payload: message_id, seen_by_user_id, seen_at (timestamp)
    - _Requirements: 19.6_

  - [ ] 26.2 Create POST /api/v1/messages/{messageId}/mark-seen endpoint
    - Add markSeen method to MessageController
    - Update message seen_at timestamp
    - Dispatch MessageSeen event to presence channel
    - _Requirements: 19.5, 19.6_

  - [ ]* 26.3 Write property test for message seen event publishing
    - **Property 44: Message Seen Event Publishing**
    - **Validates: Requirements 19.6**

  - [ ]* 26.4 Write unit tests for message seen status
    - Test MessageSeen event broadcasts to presence channel
    - Test mark-seen endpoint updates message timestamp
    - Test mark-seen endpoint dispatches event

- [ ] 27. Implement online/offline agent status
  - [ ] 27.1 Configure presence-tenant.{tenantId} channel
    - Channel already configured in task 21.1
    - Verify presence channel returns user info on subscription
    - _Requirements: 20.1, 20.2_

  - [ ] 27.2 Create GET /api/v1/team/online endpoint
    - Add onlineUsers method to TeamController
    - Query presence channel members via Soketi HTTP API
    - Return list of online users with id, name, role, avatar_url
    - _Requirements: 20.7_

  - [ ]* 27.3 Write unit tests for online status
    - Test presence channel tracks connected users
    - Test member_added event when user connects
    - Test member_removed event when user disconnects
    - Test online users endpoint returns correct data

- [ ] 28. Implement frontend WebSocket integration
  - [ ] 28.1 Create Pusher client setup in React frontend
    - Create frontend/src/lib/pusher.ts with Pusher client configuration
    - Read REACT_APP_PUSHER_KEY, REACT_APP_PUSHER_HOST, REACT_APP_PUSHER_PORT, REACT_APP_PUSHER_SCHEME from env
    - Configure authEndpoint to /api/broadcasting/auth with JWT token
    - _Requirements: 17.3, 18.3, 19.2, 20.2_

  - [ ] 28.2 Create useMessageNotifications React hook
    - Create frontend/src/hooks/useMessageNotifications.ts
    - Subscribe to tenant.{tenant_id}.messages channel
    - Listen for message.received events
    - Display browser notification (if permitted)
    - Play notification sound
    - Invalidate messages query to refresh inbox
    - _Requirements: 17.4, 17.5_

  - [ ] 28.3 Create useLeadUpdates React hook
    - Create frontend/src/hooks/useLeadUpdates.ts
    - Subscribe to tenant.{tenant_id}.leads channel
    - Listen for lead.updated events
    - Update lead display without page refresh
    - Show toast notification if lead is currently open
    - _Requirements: 18.4, 18.5_

  - [ ] 28.4 Create useTypingIndicator React hook
    - Create frontend/src/hooks/useTypingIndicator.ts
    - Subscribe to presence-conversation.{conversation_id} channel
    - Listen for typing.start and typing.stop events
    - Maintain list of typing users
    - Provide handleTyping function to trigger typing events
    - Auto-stop typing after 3 seconds of inactivity
    - _Requirements: 19.2, 19.3, 19.4_

  - [ ] 28.5 Create useOnlineStatus React hook
    - Create frontend/src/hooks/useOnlineStatus.ts
    - Subscribe to presence-tenant.{tenant_id} channel
    - Listen for pusher:subscription_succeeded, pusher:member_added, pusher:member_removed events
    - Maintain list of online users
    - _Requirements: 20.2, 20.3, 20.4, 20.5_

  - [ ]* 28.6 Write integration tests for frontend WebSocket hooks
    - Test useMessageNotifications displays notification on event
    - Test useLeadUpdates updates UI on event
    - Test useTypingIndicator tracks typing users
    - Test useOnlineStatus tracks online users

- [ ] 29. Checkpoint - Verify real-time features
  - Test typing indicators show when user types
  - Test typing indicators stop after 3 seconds
  - Test message seen status updates in real-time
  - Test online/offline status updates when users connect/disconnect
  - Test frontend hooks receive and handle WebSocket events
  - Run property test for message seen (Property 44)
  - Ask the user if questions arise.


### Component 8: Background Jobs and Cron Tasks

- [ ] 30. Implement daily lead scoring cron job
  - [ ] 30.1 Create ScoreLeadsBatch queue job
    - Create app/Jobs/ScoreLeadsBatch.php
    - Accept array of lead IDs (max 100)
    - For each lead: call AI Engine POST /api/v1/leads/score
    - Update Lead record with returned score, confidence, factors, score_updated_at
    - If score changes by > 20 points: dispatch LeadUpdated event
    - If AI Engine fails after 3 retries: log error and skip lead
    - _Requirements: 2.3, 2.4_

  - [ ]* 30.2 Write property test for batch processing resilience
    - **Property 5: Batch Processing Resilience**
    - **Validates: Requirements 2.4**

  - [ ] 30.3 Create DailyLeadScoring command
    - Create app/Console/Commands/DailyLeadScoring.php
    - Query all active leads for tenant
    - Batch leads into groups of 100
    - Enqueue ScoreLeadsBatch job for each batch
    - Update tenant.last_score_run timestamp after completion
    - _Requirements: 2.1, 2.2, 2.6_

  - [ ]* 30.4 Write property test for lead batching correctness
    - **Property 4: Lead Batching Correctness**
    - **Validates: Requirements 2.2**

  - [ ]* 30.5 Write property test for scoring run timestamp update
    - **Property 6: Scoring Run Timestamp Update**
    - **Validates: Requirements 2.6**

  - [ ] 30.6 Schedule DailyLeadScoring command in Kernel
    - Add command to app/Console/Kernel.php schedule
    - Schedule to run daily at 02:00 UTC
    - _Requirements: 2.1_

  - [ ]* 30.7 Write unit tests for daily lead scoring
    - Test command batches leads correctly
    - Test job calls AI Engine for each lead
    - Test job updates lead records
    - Test job dispatches LeadUpdated event for significant score changes
    - Test job handles AI Engine failures gracefully

- [ ] 31. Implement intent detection queue job
  - [ ] 31.1 Create DetectMessageIntent queue job
    - Create app/Jobs/DetectMessageIntent.php
    - Accept message_id parameter
    - Retrieve message and sender context
    - Call AI Engine POST /api/v1/messages/detect-intent
    - Update Message record with intent, confidence, reasoning
    - If intent is buying_intent with confidence > 0.7: send notification to assigned agent
    - If intent is churn_risk with confidence > 0.7: create high-priority Task for account owner
    - _Requirements: 3.4, 3.5, 3.7, 3.8_

  - [ ]* 31.2 Write property test for intent detection event enqueueing
    - **Property 8: Intent Detection Event Enqueueing**
    - **Validates: Requirements 3.4**

  - [ ]* 31.3 Write property test for high-confidence intent notification
    - **Property 9: High-Confidence Intent Notification**
    - **Validates: Requirements 3.7**

  - [ ]* 31.4 Write property test for churn risk task creation
    - **Property 10: Churn Risk Task Creation**
    - **Validates: Requirements 3.8**

  - [ ] 31.5 Trigger DetectMessageIntent job on inbound message
    - In message processing logic, after creating Message record, dispatch DetectMessageIntent job
    - Ensure job is enqueued within 3 seconds of message arrival
    - _Requirements: 3.4_

  - [ ]* 31.6 Write unit tests for intent detection job
    - Test job calls AI Engine with correct payload
    - Test job updates message record
    - Test job sends notification for buying_intent
    - Test job creates task for churn_risk

- [ ] 32. Implement AI draft usage logging
  - [ ] 32.1 Add draft_usage field to messages table
    - Create migration to add draft_usage enum: 'unmodified', 'modified', 'discarded', null
    - _Requirements: 4.7_

  - [ ] 32.2 Create POST /api/v1/messages/{messageId}/log-draft-usage endpoint
    - Add logDraftUsage method to MessageController
    - Accept usage parameter: 'unmodified', 'modified', 'discarded'
    - Update message.draft_usage field
    - _Requirements: 4.7_

  - [ ]* 32.3 Write property test for AI draft usage logging
    - **Property 12: AI Draft Usage Logging**
    - **Validates: Requirements 4.7**

- [ ] 33. Implement data persistence round-trip validation
  - [ ]* 33.1 Write property test for data persistence round-trip
    - **Property 3: Data Persistence Round-Trip**
    - **Validates: Requirements 1.5, 3.5, 8.7, 9.5, 10.4**
    - Test lead scoring: persist score, confidence, factors, then retrieve and verify
    - Test intent detection: persist intent, confidence, reasoning, then retrieve and verify
    - Test subscription: persist subscription data, then retrieve and verify

- [ ] 34. Checkpoint - Verify background jobs
  - Test daily lead scoring command batches and processes leads
  - Test intent detection job processes messages and triggers notifications
  - Test AI draft usage logging records usage correctly
  - Run property tests for background jobs (Properties 3-6, 8-10, 12)
  - Ask the user if questions arise.


### Component 9: Serialization and Validation

- [ ] 35. Implement Stripe webhook serialization
  - [ ] 35.1 Create Stripe Event parser
    - Create modular_core/modules/Platform/Billing/StripeEventParser.php
    - Implement parseEvent method to convert JSON to typed Event object
    - Validate JSON schema against Stripe Event API specification
    - Return descriptive error for invalid schema or missing required fields
    - _Requirements: 21.1, 21.2, 21.3_

  - [ ] 35.2 Create Stripe Event pretty printer
    - Add formatEvent method to StripeEventParser
    - Serialize Event object back to JSON matching Stripe schema
    - _Requirements: 21.4_

  - [ ]* 35.3 Write property test for Stripe webhook parsing round-trip
    - **Property 45: Stripe Webhook Parsing Round-Trip**
    - **Validates: Requirements 21.5**
    - Test: parse JSON → Event object → serialize JSON → parse again → verify equivalent

- [ ] 36. Implement AI Engine request/response validation
  - [ ] 36.1 Add Pydantic schema validation to all AI Engine endpoints
    - Ensure all endpoints validate request JSON against Pydantic models
    - Return HTTP 400 with validation errors for schema violations
    - Already implemented in tasks 3.1, 4.1, 6.1, 6.5 - verify completeness
    - _Requirements: 22.1, 22.2_

  - [ ]* 36.2 Write property test for AI Engine request validation
    - **Property 46: AI Engine Request Validation**
    - **Validates: Requirements 22.2**
    - Test: send invalid JSON (missing fields, wrong types) → verify HTTP 400 with errors

  - [ ] 36.3 Create AI response serializer
    - Add to_json methods to all response models (LeadScoreResponse, IntentDetectionResponse, etc.)
    - Ensure JSON output matches defined schemas
    - _Requirements: 22.3, 22.4_

  - [ ]* 36.4 Write property test for AI Engine response serialization round-trip
    - **Property 47: AI Engine Response Serialization Round-Trip**
    - **Validates: Requirements 22.5**
    - Test: create response object → serialize JSON → parse back → verify equivalent

- [ ] 37. Checkpoint - Verify serialization and validation
  - Test Stripe webhook parser handles valid and invalid JSON
  - Test Stripe webhook round-trip preserves data
  - Test AI Engine request validation rejects invalid requests
  - Test AI Engine response serialization round-trip preserves data
  - Run property tests for serialization (Properties 45-47)
  - Ask the user if questions arise.


### Component 10: Integration and End-to-End Testing

- [ ] 38. Integrate AI Engine with PHP backend
  - [ ] 38.1 Create AIEngineClient service in PHP
    - Create modular_core/modules/Platform/AI/AIEngineClient.php
    - Implement methods for all AI Engine endpoints: scoreLeads, detectIntent, generateReply, suggestActions, getUsage
    - Generate JWT token with tenant_id claim for authentication
    - Set timeout to match AI Engine endpoint timeouts
    - Handle HTTP errors and return appropriate responses
    - _Requirements: 6.3, 6.4_

  - [ ] 38.2 Integrate AIEngineClient with Lead scoring
    - Update ScoreLeadsBatch job to use AIEngineClient.scoreLeads
    - Update Lead model to store score, confidence, factors, score_updated_at
    - _Requirements: 1.5_

  - [ ] 38.3 Integrate AIEngineClient with Message intent detection
    - Update DetectMessageIntent job to use AIEngineClient.detectIntent
    - Update Message model to store intent, confidence, reasoning
    - _Requirements: 3.5_

  - [ ] 38.4 Create AI reply generation UI endpoint
    - Create POST /api/v1/messages/{messageId}/generate-reply endpoint
    - Call AIEngineClient.generateReply with message text, conversation history, tone
    - Return draft_text to frontend
    - _Requirements: 4.5_

  - [ ] 38.5 Create action suggestion UI endpoint
    - Create POST /api/v1/messages/{messageId}/suggest-actions endpoint
    - Call AIEngineClient.suggestActions with conversation summary, contact context
    - Return suggested actions to frontend
    - _Requirements: 5.4_

  - [ ]* 38.6 Write integration tests for AI Engine integration
    - Test PHP backend can authenticate with AI Engine
    - Test lead scoring integration end-to-end
    - Test intent detection integration end-to-end
    - Test content generation integration end-to-end
    - Test action suggestions integration end-to-end

- [ ] 39. Implement end-to-end subscription flow test
  - [ ]* 39.1 Write integration test for complete subscription flow
    - Test: create checkout session → simulate checkout.session.completed webhook → verify subscription created and tenant activated
    - Test: simulate invoice.payment_failed webhook → verify grace period started
    - Test: simulate invoice.payment_succeeded webhook → verify grace period cleared
    - Test: simulate customer.subscription.deleted webhook → verify tenant locked
    - _Requirements: 8.6, 11.5, 11.6, 11.7_

- [ ] 40. Implement end-to-end WebSocket flow test
  - [ ]* 40.1 Write integration test for message notification flow
    - Test: create inbound message → verify MessageReceived event published → verify frontend receives event
    - _Requirements: 17.1, 17.2, 17.4_

  - [ ]* 40.2 Write integration test for lead update flow
    - Test: update lead score → verify LeadUpdated event published → verify frontend receives event
    - _Requirements: 18.1, 18.2, 18.4_

- [ ] 41. Run complete property test suite
  - [ ] 41.1 Execute all AI Engine property tests (Python/Hypothesis)
    - Run tests for Properties 1, 2, 7, 11, 13-18
    - Verify 100 iterations per test
    - Fix any discovered edge cases
    - _Requirements: All AI Engine requirements_

  - [ ] 41.2 Execute all Billing System property tests (PHP/Eris)
    - Run tests for Properties 3-6, 8-10, 12, 19-41, 45-47
    - Verify 100 iterations per test
    - Fix any discovered edge cases
    - _Requirements: All Billing System requirements_

  - [ ] 41.3 Execute all WebSocket property tests
    - Run tests for Properties 42-44
    - Verify 100 iterations per test
    - Fix any discovered edge cases
    - _Requirements: All WebSocket requirements_

- [ ] 42. Checkpoint - Verify complete integration
  - Test AI Engine integrates with PHP backend for all endpoints
  - Test complete subscription flow from checkout to activation to cancellation
  - Test complete WebSocket flow from event trigger to frontend display
  - Verify all 47 property tests pass with 100 iterations each
  - Ask the user if questions arise.


### Component 11: Performance Testing and Optimization

- [ ] 43. Implement performance testing for AI Engine
  - [ ] 43.1 Create Locust load test script for AI Engine
    - Create ai_engine/tests/load/locustfile.py
    - Define test scenarios for all endpoints: /leads/score, /messages/detect-intent, /content/generate-reply, /content/suggest-actions
    - Configure concurrent users and request rate
    - _Requirements: 1.2, 3.2, 4.2, 5.2_

  - [ ] 43.2 Run load tests and verify performance benchmarks
    - Test lead scoring: < 3 seconds per request under load
    - Test intent detection: < 2 seconds per request under load
    - Test content generation: < 4 seconds per request under load
    - Test action suggestions: < 3 seconds per request under load
    - Identify and fix performance bottlenecks
    - _Requirements: 1.2, 3.2, 4.2, 5.2_

  - [ ]* 43.3 Write unit tests for performance monitoring
    - Test request duration logging
    - Test timeout enforcement
    - Test rate limiting under load

- [ ] 44. Implement performance testing for Billing System
  - [ ] 44.1 Create load test for webhook processing
    - Create test script to simulate concurrent Stripe webhook events
    - Test webhook processing time < 5 seconds per event
    - Test idempotency under concurrent duplicate events
    - _Requirements: 11.8_

  - [ ] 44.2 Optimize webhook processing if needed
    - Ensure webhook signature verification is fast
    - Ensure database queries are optimized
    - Add indexes if needed for webhook_events table
    - _Requirements: 11.8_

- [ ] 45. Implement performance testing for WebSocket
  - [ ] 45.1 Create load test for WebSocket connections
    - Test concurrent WebSocket connections (target: 1000+ concurrent)
    - Test message throughput (target: 100+ messages/second)
    - Test event delivery latency < 3 seconds
    - _Requirements: 17.1, 18.1_

  - [ ] 45.2 Optimize Soketi configuration if needed
    - Adjust Soketi memory limits
    - Configure connection limits
    - Optimize Nginx WebSocket proxy settings
    - _Requirements: 16.1_

- [ ] 46. Checkpoint - Verify performance requirements
  - Verify AI Engine meets all response time requirements under load
  - Verify webhook processing meets 5-second requirement
  - Verify WebSocket event delivery meets 3-second requirement
  - Document performance test results
  - Ask the user if questions arise.


### Component 12: Documentation and Deployment

- [ ] 47. Create API documentation
  - [ ] 47.1 Generate OpenAPI documentation for AI Engine
    - FastAPI automatically generates OpenAPI docs at /docs
    - Review and enhance endpoint descriptions
    - Add request/response examples for all endpoints
    - Document authentication requirements
    - _Requirements: 6.2_

  - [ ] 47.2 Document Billing API endpoints
    - Create API documentation for /api/v1/billing/* endpoints
    - Include request/response examples
    - Document webhook event types and payloads
    - Document error responses
    - _Requirements: 8.3, 9.1, 10.1, 11.1_

  - [ ] 47.3 Document WebSocket channels and events
    - Document all channel types and authorization requirements
    - Document all event types and payloads
    - Provide frontend integration examples
    - _Requirements: 17.1, 18.1, 19.2, 20.2_

- [ ] 48. Create deployment configuration
  - [ ] 48.1 Create production Docker Compose configuration
    - Update docker-compose.yml for production
    - Configure environment variables for production
    - Set up health checks for all services
    - Configure restart policies
    - _Requirements: 6.1, 16.1_

  - [ ] 48.2 Create Nginx production configuration
    - Configure SSL certificates for production domains
    - Set up rate limiting at Nginx level
    - Configure caching for static assets
    - Set up logging and monitoring
    - _Requirements: 16.1_

  - [ ] 48.3 Create environment variable templates
    - Create .env.example for AI Engine with all required variables
    - Create .env.example for PHP backend with all required variables
    - Document all environment variables
    - _Requirements: 6.1, 7.1, 8.1, 16.2_

  - [ ] 48.4 Create deployment guide
    - Document deployment steps for all services
    - Document database migration steps
    - Document Stripe webhook configuration
    - Document monitoring and alerting setup
    - _Requirements: All_

- [ ] 49. Set up monitoring and alerting
  - [ ] 49.1 Configure structured logging for all services
    - Ensure AI Engine logs to stdout in JSON format
    - Ensure PHP backend logs to stdout in JSON format
    - Configure log aggregation (ELK stack or CloudWatch)
    - Set log retention policies
    - _Requirements: 6.8_

  - [ ] 49.2 Configure metrics collection
    - Set up metrics for AI Engine: request rate, latency, error rate, token usage
    - Set up metrics for Billing: subscription creation rate, webhook processing time, failed payments
    - Set up metrics for WebSocket: connected clients, message throughput, connection errors
    - _Requirements: 7.5_

  - [ ] 49.3 Configure alerts
    - Alert on AI Engine error rate > 5%
    - Alert on webhook processing time > 10 seconds
    - Alert on WebSocket connection failures > 10%
    - Alert on Stripe API errors
    - Alert on grace period expirations without payment
    - _Requirements: All_

- [ ] 50. Final checkpoint - Production readiness
  - Verify all services are running in production configuration
  - Verify all API documentation is complete and accurate
  - Verify all environment variables are documented
  - Verify monitoring and alerting is configured
  - Verify deployment guide is complete
  - Run smoke tests on production-like environment
  - Ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional property-based tests and unit tests that can be skipped for faster MVP delivery
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties (47 total properties)
- Unit tests validate specific examples and edge cases
- The implementation is organized by component to enable parallel development where possible
- AI Engine (Component 1-2) can be developed in parallel with Billing System (Component 3-5)
- WebSocket infrastructure (Component 6-7) depends on both AI Engine and Billing System for event triggers
- Background jobs (Component 8) integrate AI Engine with the platform
- Integration testing (Component 10) validates all components work together
- Performance testing (Component 11) ensures requirements are met under load
- Documentation and deployment (Component 12) prepare for production launch

## Timeline Estimate

- Component 1-2 (AI Engine): 5 days
- Component 3-5 (Billing System): 7 days
- Component 6-7 (WebSockets): 4 days
- Component 8 (Background Jobs): 2 days
- Component 9 (Serialization): 1 day
- Component 10 (Integration): 1 day
- Component 11 (Performance): 1 day
- Component 12 (Documentation): 1 day

Total: 22 days (includes 1 day buffer beyond 21-day target)

