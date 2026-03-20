# Requirements Document

## Introduction

This specification covers Phase 1 of the NexSaaS AI Revenue Operating System: the AI Engine and Billing System. This phase implements the core differentiating features that enable revenue generation through intelligent lead scoring, intent detection, content generation, and subscription billing. The AI Engine is a Python FastAPI microservice that integrates with OpenAI/Anthropic APIs to provide ML-powered predictions and NLP capabilities. The Billing System integrates with Stripe to manage multi-tier subscriptions, usage metering, and payment processing. Real-time WebSocket communication via Soketi enables live notifications and status updates across the platform. This phase is estimated at 21 days and is critical for launching the product to market.

---

## Glossary

- **AI_Engine**: The Python FastAPI microservice providing ML-powered predictions, NLP, and content generation.
- **Lead_Score**: A numeric value (0–100) representing a Lead's conversion likelihood based on behavior, engagement, company data, and pipeline stage.
- **Intent_Detection**: NLP analysis of incoming messages to classify customer intent as buying_intent, churn_risk, support_request, or neutral.
- **Content_Generation**: AI-powered drafting of email responses, WhatsApp messages, and follow-up action suggestions.
- **Stripe**: The payment processor used for SaaS subscription billing and payment collection.
- **Subscription**: A recurring billing arrangement linking a Tenant to a pricing tier with automatic renewal.
- **Pricing_Tier**: One of three subscription plans: Starter ($49/mo, 5 users), Growth ($149/mo, 25 users), Enterprise ($499+/mo, unlimited users).
- **Stripe_Checkout**: The hosted payment page provided by Stripe for collecting payment method details and creating subscriptions.
- **Webhook**: An HTTP callback from Stripe delivering event notifications such as payment_succeeded or subscription_canceled.
- **Grace_Period**: A 7-day window after payment failure during which the Tenant retains access while payment is retried.
- **Tenant_Lockout**: The state where a Tenant's access is suspended due to subscription expiration or non-payment.
- **Usage_Metering**: Tracking consumption of metered resources (API calls, AI requests, storage) for billing purposes.
- **Invoice**: A Stripe-generated billing document sent to the customer detailing charges for a billing period.
- **VAT**: Value Added Tax applied to subscriptions for customers in the EU and MENA regions based on their billing address.
- **Soketi**: A self-hosted, open-source Pusher-compatible WebSocket server for real-time bidirectional communication.
- **WebSocket**: A persistent bidirectional connection enabling real-time UI updates without polling.
- **Channel**: A named WebSocket topic to which clients subscribe to receive specific event types.
- **Presence_Channel**: A special WebSocket channel tracking which users are currently connected and their status.
- **Typing_Indicator**: A real-time signal showing when another user is composing a message in a conversation.
- **Message_Seen_Status**: A timestamp indicating when a message was viewed by the recipient.
- **Cron_Job**: A scheduled background task that runs at fixed intervals to perform batch operations.
- **OpenAI_API**: The external API providing GPT models for text generation and embeddings.
- **Anthropic_API**: The external API providing Claude models for text generation and analysis.
- **Embedding**: A vector representation of text used for semantic similarity and search.
- **Confidence_Score**: A float (0.0–1.0) indicating the AI model's certainty in its prediction or classification.
- **Model_Version**: A string identifier tracking which AI model version produced a given result.
- **Tenant**: An isolated organizational account sharing the platform infrastructure.
- **User**: An authenticated human actor operating within a Tenant.
- **Lead**: An unqualified prospect record in the CRM.
- **Deal**: A qualified sales opportunity linked to a Contact or Account.
- **Contact**: A person record stored in the CRM.
- **Message**: An inbound or outbound communication via WhatsApp, Email, Telegram, or other channels.
- **Agent**: A User role with limited write access focused on CRM operations.
- **System**: The NexSaaS platform as a whole.

---

## Requirements

---

### Requirement 1: Lead Scoring Engine

**User Story:** As a Manager, I want Leads automatically scored by the AI Engine based on behavior, engagement, company data, and pipeline stage, so that Agents prioritize the highest-value prospects.

#### Acceptance Criteria

1. THE AI_Engine SHALL expose a POST /api/v1/leads/score endpoint accepting lead_id, tenant_id, and lead_data (JSON object containing demographic, behavioral, and engagement signals).
2. WHEN the endpoint receives a valid request, THE AI_Engine SHALL compute a Lead_Score (integer 0–100) within 3 seconds.
3. THE AI_Engine SHALL return a response containing score (integer 0–100), confidence (float 0.0–1.0), factors (array of contributing factors with weights), and model_version (string).
4. THE AI_Engine SHALL base the Lead_Score on the following signals: email domain quality, company size, industry match, website visits, email opens, link clicks, form submissions, time since last activity, and current pipeline stage.
5. THE System SHALL persist the returned Lead_Score, confidence, factors, and score_updated_at timestamp on the Lead record.
6. THE System SHALL display the Lead_Score and top 3 contributing factors on the Lead detail view.
7. WHEN a Lead_Score changes by more than 20 points, THE System SHALL send a real-time notification to the Lead owner via WebSocket within 5 seconds.

---

### Requirement 2: Daily Lead Score Updates

**User Story:** As a Manager, I want Lead scores refreshed daily, so that scores reflect the latest engagement data.

#### Acceptance Criteria

1. THE System SHALL run a Cron_Job daily at 02:00 UTC to update Lead_Score for all active Leads.
2. WHEN the Cron_Job executes, THE System SHALL batch Leads into groups of 100 and enqueue a lead.score_batch event per batch on the Queue.
3. THE AI_Engine SHALL process each batch and return updated scores within 60 seconds per batch.
4. IF the AI_Engine fails to score a Lead after 3 retry attempts, THEN THE System SHALL log the error and skip that Lead without halting the batch.
5. THE System SHALL complete the daily scoring run for up to 100,000 Leads within 2 hours.
6. THE System SHALL record the last_score_run timestamp per Tenant after each daily run completes.

---

### Requirement 3: Intent Detection on Incoming Messages

**User Story:** As an Agent, I want incoming WhatsApp and Email messages automatically analyzed for customer intent, so that I can respond appropriately and prioritize urgent requests.

#### Acceptance Criteria

1. THE AI_Engine SHALL expose a POST /api/v1/messages/detect-intent endpoint accepting message_id, tenant_id, message_text, sender_context (JSON object with sender history and relationship data).
2. WHEN the endpoint receives a valid request, THE AI_Engine SHALL classify the message intent as one of: buying_intent, churn_risk, support_request, or neutral within 2 seconds.
3. THE AI_Engine SHALL return a response containing intent (string enum), confidence (float 0.0–1.0), reasoning (string explanation), and model_version (string).
4. WHEN a new inbound message arrives via WhatsApp or Email, THE System SHALL enqueue a message.intent_detection event on the Queue within 3 seconds.
5. THE System SHALL persist the returned intent, confidence, and reasoning on the Message record.
6. THE System SHALL display the detected intent and confidence as a badge on the Message in the Inbox UI.
7. IF the detected intent is buying_intent with confidence > 0.7, THEN THE System SHALL send a real-time notification to the assigned Agent via WebSocket within 5 seconds.
8. IF the detected intent is churn_risk with confidence > 0.7, THEN THE System SHALL create a high-priority Task for the Account owner and send a notification within 5 seconds.

---

### Requirement 4: AI-Powered Content Generation

**User Story:** As an Agent, I want AI-generated draft responses for emails and WhatsApp messages, so that I can reply faster while maintaining quality.

#### Acceptance Criteria

1. THE AI_Engine SHALL expose a POST /api/v1/content/generate-reply endpoint accepting message_id, tenant_id, message_text, conversation_history (array of prior messages), and tone (enum: professional, friendly, concise).
2. WHEN the endpoint receives a valid request, THE AI_Engine SHALL generate a draft reply within 4 seconds.
3. THE AI_Engine SHALL return a response containing draft_text (string), confidence (float 0.0–1.0), and model_version (string).
4. THE System SHALL display a "Generate AI Reply" button on the Message detail view in the Inbox.
5. WHEN an Agent clicks "Generate AI Reply", THE System SHALL call the AI_Engine endpoint and display the draft_text in an editable text area within 5 seconds.
6. THE Agent SHALL be able to edit, approve, or discard the AI-generated draft before sending.
7. THE System SHALL log whether the Agent sent the AI draft unmodified, modified, or discarded for model improvement tracking.

---

### Requirement 5: Follow-Up Action Suggestions

**User Story:** As an Agent, I want AI-suggested follow-up actions after closing a conversation, so that I never miss a critical next step.

#### Acceptance Criteria

1. THE AI_Engine SHALL expose a POST /api/v1/content/suggest-actions endpoint accepting message_id, tenant_id, conversation_summary, and contact_context.
2. WHEN the endpoint receives a valid request, THE AI_Engine SHALL return up to 3 suggested follow-up actions within 3 seconds.
3. THE AI_Engine SHALL return a response containing actions (array of objects with action_type, description, priority, due_date_suggestion) and model_version (string).
4. THE System SHALL display suggested actions as clickable cards on the Message detail view after the conversation is marked resolved.
5. WHEN an Agent clicks a suggested action card, THE System SHALL pre-fill a Task creation form with the action details.
6. THE Agent SHALL be able to create the Task with one click or dismiss the suggestion.

---

### Requirement 6: FastAPI Service Architecture

**User Story:** As a backend developer, I want the AI Engine built as a FastAPI microservice, so that it scales independently and integrates cleanly with the PHP backend.

#### Acceptance Criteria

1. THE AI_Engine SHALL be implemented as a Python 3.11+ FastAPI application.
2. THE AI_Engine SHALL expose a REST API on port 8001 with OpenAPI documentation at /docs.
3. THE AI_Engine SHALL authenticate incoming requests using a shared JWT secret validated against the iss and aud claims.
4. IF an incoming request lacks a valid JWT or the tenant_id claim does not match the request body, THEN THE AI_Engine SHALL return HTTP 401 Unauthorized.
5. THE AI_Engine SHALL integrate with OpenAI API for GPT-4 models and Anthropic API for Claude models via configurable environment variables.
6. THE AI_Engine SHALL implement request rate limiting: 100 requests per minute per Tenant.
7. IF a Tenant exceeds the rate limit, THEN THE AI_Engine SHALL return HTTP 429 Too Many Requests with a Retry-After header.
8. THE AI_Engine SHALL log all requests, responses, and errors to structured JSON logs with tenant_id, user_id, endpoint, duration_ms, and status_code.

---

### Requirement 7: OpenAI and Anthropic Integration

**User Story:** As a backend developer, I want the AI Engine to support both OpenAI and Anthropic models, so that we can choose the best model per use case and avoid vendor lock-in.

#### Acceptance Criteria

1. THE AI_Engine SHALL read OPENAI_API_KEY and ANTHROPIC_API_KEY from environment variables at startup.
2. THE AI_Engine SHALL default to OpenAI GPT-4 for content generation and Anthropic Claude for intent detection unless overridden by configuration.
3. WHEN calling an external AI API, THE AI_Engine SHALL set a timeout of 10 seconds and retry up to 2 times with exponential backoff on transient errors (HTTP 429, 500, 503).
4. IF all retry attempts fail, THEN THE AI_Engine SHALL return HTTP 503 Service Unavailable with a descriptive error message.
5. THE AI_Engine SHALL track API usage per Tenant: total requests, total tokens consumed, and cost estimate.
6. THE AI_Engine SHALL expose a GET /api/v1/usage endpoint returning per-Tenant usage statistics for the current billing period.

---

### Requirement 8: Stripe Subscription Management

**User Story:** As an Owner, I want to subscribe to a pricing tier via Stripe Checkout, so that I can access the platform and be billed automatically.

#### Acceptance Criteria

1. THE System SHALL offer three Pricing_Tiers: Starter ($49/mo, 5 users, 10,000 AI requests/mo), Growth ($149/mo, 25 users, 50,000 AI requests/mo), Enterprise ($499/mo base, unlimited users, 200,000 AI requests/mo + $0.01 per additional request).
2. THE System SHALL create a Stripe Customer record for each Tenant on first subscription.
3. THE System SHALL expose a POST /api/v1/billing/checkout endpoint accepting tenant_id and tier (enum: starter, growth, enterprise).
4. WHEN the endpoint is called, THE System SHALL create a Stripe Checkout Session with the selected tier's Price ID and return the session URL within 2 seconds.
5. WHEN a customer completes Stripe Checkout, Stripe SHALL redirect them to the platform success URL with session_id as a query parameter.
6. THE System SHALL retrieve the Checkout Session from Stripe, create a Subscription record linked to the Tenant, and activate the Tenant's access within 10 seconds.
7. THE System SHALL store the Stripe subscription_id, customer_id, current_tier, billing_period_start, billing_period_end, and status on the Tenant record.

---

### Requirement 9: Subscription Upgrade and Downgrade

**User Story:** As an Owner, I want to upgrade or downgrade my subscription tier, so that I can adjust my plan as my team grows or shrinks.

#### Acceptance Criteria

1. THE System SHALL expose a POST /api/v1/billing/change-tier endpoint accepting tenant_id and new_tier.
2. WHEN an Owner requests an upgrade, THE System SHALL call the Stripe API to update the subscription to the new tier with proration and return the updated subscription details within 3 seconds.
3. WHEN an Owner requests a downgrade, THE System SHALL schedule the tier change to take effect at the end of the current billing period and notify the Owner of the effective date.
4. THE System SHALL enforce user limits per tier: IF a Tenant downgrades to a tier with fewer user seats than currently active users, THEN THE System SHALL prevent the downgrade and return an error instructing the Owner to deactivate users first.
5. THE System SHALL update the Tenant's current_tier, billing_period_end, and status fields after a successful tier change.

---

### Requirement 10: Subscription Cancellation

**User Story:** As an Owner, I want to cancel my subscription, so that I stop being charged when I no longer need the platform.

#### Acceptance Criteria

1. THE System SHALL expose a POST /api/v1/billing/cancel endpoint accepting tenant_id and cancel_at_period_end (boolean).
2. WHEN cancel_at_period_end is true, THE System SHALL call the Stripe API to schedule cancellation at the end of the current billing period and notify the Owner of the cancellation date.
3. WHEN cancel_at_period_end is false, THE System SHALL call the Stripe API to cancel the subscription immediately and lock the Tenant's access within 60 seconds.
4. THE System SHALL update the Tenant's subscription status to canceled and set access_locked_at timestamp.
5. WHILE a Tenant's subscription status is canceled, THE System SHALL return HTTP 403 Forbidden with message "Subscription expired" for all API requests except billing endpoints.

---

### Requirement 11: Stripe Webhook Handling

**User Story:** As a backend developer, I want to handle Stripe webhooks reliably, so that subscription state changes are reflected in the platform automatically.

#### Acceptance Criteria

1. THE System SHALL expose a POST /webhooks/stripe endpoint accepting Stripe webhook events.
2. THE System SHALL verify the webhook signature using the Stripe webhook secret before processing.
3. IF the webhook signature is invalid, THEN THE System SHALL return HTTP 400 Bad Request and log the attempt.
4. THE System SHALL handle the following event types: invoice.payment_succeeded, invoice.payment_failed, customer.subscription.updated, customer.subscription.deleted, checkout.session.completed.
5. WHEN invoice.payment_succeeded is received, THE System SHALL update the Tenant's subscription status to active and clear any Grace_Period flags within 30 seconds.
6. WHEN invoice.payment_failed is received, THE System SHALL start a 7-day Grace_Period, send an email notification to the Owner, and schedule payment retry.
7. WHEN customer.subscription.deleted is received, THE System SHALL set the Tenant's subscription status to canceled and lock access within 60 seconds.
8. THE System SHALL return HTTP 200 OK to Stripe within 5 seconds for all webhook events to prevent retries.
9. THE System SHALL log all webhook events with event_id, type, tenant_id, and processing_status for audit purposes.

---

### Requirement 12: Automatic Invoice Generation and Delivery

**User Story:** As an Owner, I want invoices automatically generated and emailed each billing period, so that I have records for accounting.

#### Acceptance Criteria

1. WHEN Stripe generates an invoice for a subscription, THE System SHALL receive an invoice.created webhook event.
2. THE System SHALL retrieve the invoice PDF URL from Stripe and store it on the Tenant's billing history.
3. THE System SHALL send an email to the Tenant Owner's email address with the invoice PDF attached within 5 minutes of invoice creation.
4. THE email SHALL include the invoice number, amount, due date, and a link to view billing history in the platform.
5. THE System SHALL display all past invoices in a Billing History page accessible to Owners and Admins.

---

### Requirement 13: Payment Failure and Grace Period

**User Story:** As an Owner, I want a 7-day grace period after payment failure, so that I have time to update my payment method without losing access.

#### Acceptance Criteria

1. WHEN an invoice.payment_failed webhook is received, THE System SHALL set the Tenant's grace_period_start timestamp to the current time and grace_period_end to 7 days later.
2. WHILE a Tenant is in the Grace_Period, THE System SHALL display a banner on all pages warning the Owner that payment failed and access will be suspended in X days.
3. THE System SHALL send email reminders to the Owner on days 1, 3, 5, and 7 of the Grace_Period.
4. THE System SHALL allow Stripe to retry payment automatically on days 3, 5, and 7 of the Grace_Period.
5. IF payment succeeds during the Grace_Period, THEN THE System SHALL clear the grace_period_start and grace_period_end timestamps and remove the warning banner.
6. IF the Grace_Period expires without successful payment, THEN THE System SHALL lock the Tenant's access and set subscription status to past_due.

---

### Requirement 14: Tenant Lockout on Subscription Expiration

**User Story:** As a backend developer, I want Tenants locked out when their subscription expires, so that only paying customers access the platform.

#### Acceptance Criteria

1. THE System SHALL check the Tenant's subscription status on every API request before processing.
2. IF the subscription status is canceled, past_due, or unpaid, THEN THE System SHALL return HTTP 403 Forbidden with message "Subscription expired. Please update billing." for all requests except GET /api/v1/billing/*.
3. THE System SHALL allow Owners and Admins of locked Tenants to access billing endpoints to reactivate their subscription.
4. WHEN a locked Tenant's subscription is reactivated, THE System SHALL clear the access_locked_at timestamp and restore full access within 60 seconds.

---

### Requirement 15: Tax and VAT Compliance

**User Story:** As an Owner in the EU or MENA region, I want VAT automatically calculated and applied to my subscription, so that invoices are tax-compliant.

#### Acceptance Criteria

1. THE System SHALL collect the customer's billing address including country during Stripe Checkout.
2. THE System SHALL configure Stripe Tax to automatically calculate VAT, GST, or sales tax based on the customer's billing address.
3. WHEN an invoice is generated, Stripe SHALL include the applicable tax amount and tax rate on the invoice.
4. THE System SHALL display the tax amount separately from the subscription price on the Billing History page.
5. THE System SHALL support tax-exempt customers: IF a customer provides a valid VAT ID during checkout, THEN Stripe SHALL apply the reverse charge mechanism and not collect VAT.

---

### Requirement 16: Soketi WebSocket Server Setup

**User Story:** As a backend developer, I want a self-hosted WebSocket server using Soketi, so that we avoid Pusher's recurring costs while maintaining compatibility.

#### Acceptance Criteria

1. THE System SHALL deploy Soketi as a Docker container on port 6001 with SSL termination via Nginx.
2. THE System SHALL configure Soketi with app_id, app_key, and app_secret matching the platform's WebSocket credentials.
3. THE System SHALL enable Soketi's HTTP API on port 6002 for server-side event publishing.
4. THE System SHALL configure CORS on Soketi to allow connections from the platform's frontend domain.
5. THE System SHALL monitor Soketi's health via GET /health and restart the container if the endpoint returns non-200 status.

---

### Requirement 17: Real-Time Message Notifications

**User Story:** As an Agent, I want real-time notifications when new messages arrive, so that I can respond immediately without refreshing the page.

#### Acceptance Criteria

1. WHEN a new inbound message arrives via WhatsApp, Email, or Telegram, THE System SHALL publish a message.received event to the WebSocket Channel tenant.{tenant_id}.messages within 3 seconds.
2. THE event payload SHALL include message_id, sender_name, sender_channel, preview_text (first 100 characters), and assigned_agent_id.
3. THE frontend SHALL subscribe to the tenant.{tenant_id}.messages Channel on page load for authenticated users.
4. WHEN the frontend receives a message.received event, THE System SHALL display a browser notification (if permitted) and update the Inbox message list without a page refresh.
5. THE System SHALL play a notification sound (configurable per user) when a new message arrives.

---

### Requirement 18: Live Lead Status Updates

**User Story:** As a Manager, I want to see Lead status changes in real-time across all agents, so that the team stays coordinated.

#### Acceptance Criteria

1. WHEN a Lead's status, score, or assigned owner changes, THE System SHALL publish a lead.updated event to the WebSocket Channel tenant.{tenant_id}.leads within 2 seconds.
2. THE event payload SHALL include lead_id, updated_fields (object with changed field names and new values), and updated_by_user_id.
3. THE frontend SHALL subscribe to the tenant.{tenant_id}.leads Channel when viewing the Leads list or Kanban board.
4. WHEN the frontend receives a lead.updated event, THE System SHALL update the affected Lead's display without a page refresh.
5. IF the updated Lead is currently open in a detail view, THE System SHALL display a toast notification "This lead was updated by [User Name]" with a "Refresh" button.

---

### Requirement 19: Typing Indicators and Message Seen Status

**User Story:** As an Agent, I want to see when another agent is typing a reply and when a customer has seen my message, so that I avoid duplicate responses.

#### Acceptance Criteria

1. THE System SHALL use a Presence_Channel named presence-conversation.{conversation_id} for each active conversation.
2. WHEN an Agent focuses the reply text area, THE frontend SHALL trigger a typing.start event on the Presence_Channel.
3. WHEN an Agent stops typing for 3 seconds or sends the message, THE frontend SHALL trigger a typing.stop event.
4. THE frontend SHALL display "[Agent Name] is typing..." below the conversation when a typing.start event is received.
5. WHEN a message is displayed in the recipient's viewport for 2 seconds, THE frontend SHALL call POST /api/v1/messages/{message_id}/mark-seen.
6. THE System SHALL publish a message.seen event to the Presence_Channel with message_id and seen_by_user_id.
7. THE frontend SHALL display a "Seen" indicator with timestamp on the sender's message view when a message.seen event is received.

---

### Requirement 20: Online/Offline Agent Status

**User Story:** As a Manager, I want to see which agents are currently online, so that I can route urgent inquiries to available team members.

#### Acceptance Criteria

1. THE System SHALL use a Presence_Channel named presence-tenant.{tenant_id} to track online users.
2. WHEN a User logs in and the frontend loads, THE frontend SHALL subscribe to the presence-tenant.{tenant_id} Channel.
3. THE Presence_Channel SHALL broadcast a member_added event when a User connects and a member_removed event when a User disconnects.
4. THE frontend SHALL display a green "Online" indicator next to User names in the team list when they are present in the Presence_Channel.
5. THE frontend SHALL display a gray "Offline" indicator for Users not present in the Presence_Channel.
6. THE System SHALL automatically unsubscribe Users from the Presence_Channel after 60 seconds of inactivity (no mouse/keyboard events).
7. THE System SHALL display the count of online agents on the Manager dashboard.

---

## Parser and Serializer Requirements

---

### Requirement 21: Stripe Webhook Event Parser

**User Story:** As a backend developer, I want a robust parser for Stripe webhook events, so that subscription state changes are processed reliably.

#### Acceptance Criteria

1. THE System SHALL parse incoming Stripe webhook JSON payloads into typed Event objects.
2. WHEN a webhook payload is received, THE Parser SHALL validate the JSON schema against the Stripe Event API specification.
3. IF the JSON schema is invalid or missing required fields, THEN THE Parser SHALL return a descriptive error and log the raw payload.
4. THE System SHALL provide a Pretty_Printer that formats Event objects back into valid JSON matching the Stripe schema.
5. FOR ALL valid Event objects, parsing then printing then parsing SHALL produce an equivalent object (round-trip property).

---

### Requirement 22: AI Engine Request/Response Serializer

**User Story:** As a backend developer, I want consistent serialization of AI Engine requests and responses, so that the PHP backend and Python AI Engine communicate reliably.

#### Acceptance Criteria

1. THE System SHALL define JSON schemas for all AI_Engine request and response payloads.
2. THE AI_Engine SHALL validate incoming request JSON against the schema and return HTTP 400 Bad Request with validation errors if invalid.
3. THE AI_Engine SHALL serialize all response objects to JSON matching the defined schema.
4. THE System SHALL provide a Pretty_Printer that formats AI response objects back into valid JSON.
5. FOR ALL valid AI response objects, parsing then printing then parsing SHALL produce an equivalent object (round-trip property).

---

## Iteration and Feedback

This requirements document is the initial version based on the Phase 1 specification. Please review each requirement for:

- Clarity and testability of acceptance criteria
- Completeness of functional coverage
- Alignment with the 21-day implementation timeline
- Any missing edge cases or error conditions

I'm ready to iterate on any requirement based on your feedback. Once you approve this document, we'll proceed to the design phase.
