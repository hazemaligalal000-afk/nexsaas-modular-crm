# Design Document: AI Engine and Billing System

## Overview

This design document specifies the architecture and implementation approach for Phase 1 of the NexSaaS AI Revenue Operating System. This phase delivers three critical subsystems:

1. **AI Engine**: A Python FastAPI microservice providing ML-powered lead scoring, intent detection, and content generation via OpenAI/Anthropic APIs
2. **Billing System**: Stripe-integrated subscription management with multi-tier pricing, usage metering, and webhook-driven state synchronization
3. **Real-time WebSockets**: Soketi-powered bidirectional communication for live notifications, typing indicators, and presence tracking

These systems work together to enable the core revenue-generating features of the platform while maintaining clean separation of concerns and independent scalability.

### Design Goals

- **Microservice Independence**: AI Engine scales independently from the PHP monolith
- **Vendor Flexibility**: Support both OpenAI and Anthropic to avoid lock-in
- **Billing Reliability**: Webhook-driven state machine ensures subscription consistency
- **Real-time UX**: Sub-second notification delivery via persistent WebSocket connections
- **Cost Efficiency**: Self-hosted Soketi eliminates Pusher recurring costs
- **Observability**: Structured logging and usage tracking across all services

### Technology Stack

- **AI Engine**: Python 3.11+, FastAPI, Pydantic, OpenAI SDK, Anthropic SDK
- **Billing**: Stripe API v2023-10, PHP Stripe SDK, Laravel Queue
- **WebSockets**: Soketi (Pusher-compatible), Laravel Broadcasting, pusher-js client
- **Infrastructure**: Docker, Nginx (SSL termination), Redis (queue/cache)

---

## Architecture

### System Context Diagram

```mermaid
graph TB
    Frontend[React Frontend]
    PHP[PHP Laravel Backend]
    AI[Python AI Engine]
    Stripe[Stripe API]
    Soketi[Soketi WebSocket Server]
    
    Frontend -->|REST API| PHP
    Frontend -->|WebSocket| Soketi
    PHP -->|REST API| AI
    PHP -->|Webhook| Stripe
    Stripe -->|Webhook| PHP
    PHP -->|Publish Events| Soketi
    
    subgraph "NexSaaS Platform"
        Frontend
        PHP
        AI
        Soketi
    end
    
    subgraph "External Services"
        Stripe
        OpenAI[OpenAI API]
        Anthropic[Anthropic API]
    end
    
    AI -->|Generate| OpenAI
    AI -->|Analyze| Anthropic
```

### Component Interaction Flow

**Lead Scoring Flow:**
1. Cron job triggers daily at 02:00 UTC
2. PHP backend batches leads (100 per batch) and enqueues jobs
3. Queue worker calls AI Engine POST /api/v1/leads/score
4. AI Engine computes score using OpenAI embeddings + heuristics
5. PHP persists score and publishes lead.updated event to Soketi
6. Frontend receives WebSocket event and updates UI

**Payment Flow:**
1. Owner clicks "Subscribe" in frontend
2. Frontend calls PHP POST /api/v1/billing/checkout
3. PHP creates Stripe Checkout Session and returns URL
4. Frontend redirects to Stripe hosted page
5. Customer completes payment
6. Stripe sends checkout.session.completed webhook to PHP
7. PHP activates subscription and unlocks tenant access

**Real-time Notification Flow:**
1. Inbound WhatsApp message arrives via webhook
2. PHP creates Message record and enqueues intent detection job
3. Queue worker calls AI Engine POST /api/v1/messages/detect-intent
4. PHP publishes message.received event to Soketi channel
5. Soketi broadcasts to all subscribed frontend clients
6. Frontend displays notification and updates inbox

---

## Components and Interfaces

### 1. AI Engine (Python FastAPI)

#### 1.1 Service Structure

```
ai_engine/
├── main.py                 # FastAPI app entry point
├── config.py               # Environment configuration
├── auth.py                 # JWT validation middleware
├── rate_limiter.py         # Per-tenant rate limiting
├── models/
│   ├── requests.py         # Pydantic request models
│   └── responses.py        # Pydantic response models
├── services/
│   ├── lead_scorer.py      # Lead scoring logic
│   ├── intent_detector.py  # Intent classification
│   ├── content_generator.py # Reply generation
│   └── action_suggester.py # Follow-up suggestions
├── clients/
│   ├── openai_client.py    # OpenAI API wrapper
│   └── anthropic_client.py # Anthropic API wrapper
└── utils/
    ├── logger.py           # Structured JSON logging
    └── metrics.py          # Usage tracking
```

#### 1.2 API Endpoints

**POST /api/v1/leads/score**
- **Purpose**: Compute lead score based on behavioral and demographic signals
- **Authentication**: JWT with tenant_id claim
- **Rate Limit**: 100 req/min per tenant
- **Timeout**: 3 seconds
- **Request**:
```json
{
  "lead_id": "uuid",
  "tenant_id": "uuid",
  "lead_data": {
    "email_domain": "acme.com",
    "company_size": 250,
    "industry": "SaaS",
    "website_visits": 12,
    "email_opens": 8,
    "link_clicks": 3,
    "form_submissions": 2,
    "days_since_last_activity": 2,
    "pipeline_stage": "qualified"
  }
}
```
- **Response**:
```json
{
  "score": 87,
  "confidence": 0.92,
  "factors": [
    {"name": "High engagement", "weight": 0.35},
    {"name": "Enterprise company size", "weight": 0.28},
    {"name": "Recent activity", "weight": 0.22}
  ],
  "model_version": "lead-scorer-v1.2"
}
```

**POST /api/v1/messages/detect-intent**
- **Purpose**: Classify customer intent from message text
- **Authentication**: JWT with tenant_id claim
- **Rate Limit**: 100 req/min per tenant
- **Timeout**: 2 seconds
- **Request**:
```json
{
  "message_id": "uuid",
  "tenant_id": "uuid",
  "message_text": "I'm interested in upgrading to the Enterprise plan. Can we schedule a demo?",
  "sender_context": {
    "relationship_age_days": 45,
    "previous_purchases": 2,
    "support_tickets_count": 1,
    "last_interaction_days_ago": 7
  }
}
```
- **Response**:
```json
{
  "intent": "buying_intent",
  "confidence": 0.89,
  "reasoning": "Customer explicitly mentions upgrading and requests demo, indicating purchase readiness",
  "model_version": "intent-detector-v2.1"
}
```

**POST /api/v1/content/generate-reply**
- **Purpose**: Generate AI draft response to customer message
- **Authentication**: JWT with tenant_id claim
- **Rate Limit**: 100 req/min per tenant
- **Timeout**: 4 seconds
- **Request**:
```json
{
  "message_id": "uuid",
  "tenant_id": "uuid",
  "message_text": "What's your pricing for 50 users?",
  "conversation_history": [
    {"role": "customer", "text": "Hi, I'm looking for a CRM solution"},
    {"role": "agent", "text": "Happy to help! What size is your team?"}
  ],
  "tone": "professional"
}
```
- **Response**:
```json
{
  "draft_text": "For a team of 50 users, our Growth plan at $149/month would be a great fit...",
  "confidence": 0.85,
  "model_version": "gpt-4-turbo"
}
```

**POST /api/v1/content/suggest-actions**
- **Purpose**: Suggest follow-up actions after conversation
- **Authentication**: JWT with tenant_id claim
- **Rate Limit**: 100 req/min per tenant
- **Timeout**: 3 seconds
- **Request**:
```json
{
  "message_id": "uuid",
  "tenant_id": "uuid",
  "conversation_summary": "Customer inquired about Enterprise pricing and requested demo",
  "contact_context": {
    "company_name": "Acme Corp",
    "industry": "Manufacturing",
    "current_tier": "growth"
  }
}
```
- **Response**:
```json
{
  "actions": [
    {
      "action_type": "schedule_demo",
      "description": "Schedule Enterprise demo with decision maker",
      "priority": "high",
      "due_date_suggestion": "2024-01-15"
    },
    {
      "action_type": "send_pricing",
      "description": "Send custom Enterprise pricing proposal",
      "priority": "high",
      "due_date_suggestion": "2024-01-12"
    },
    {
      "action_type": "follow_up",
      "description": "Follow up if no response in 3 days",
      "priority": "medium",
      "due_date_suggestion": "2024-01-15"
    }
  ],
  "model_version": "claude-3-sonnet"
}
```

**GET /api/v1/usage**
- **Purpose**: Retrieve AI usage statistics for billing period
- **Authentication**: JWT with tenant_id claim
- **Response**:
```json
{
  "tenant_id": "uuid",
  "billing_period_start": "2024-01-01T00:00:00Z",
  "billing_period_end": "2024-01-31T23:59:59Z",
  "total_requests": 12450,
  "requests_by_endpoint": {
    "lead_scoring": 8000,
    "intent_detection": 3200,
    "content_generation": 1250
  },
  "total_tokens_consumed": 2450000,
  "estimated_cost_usd": 24.50
}
```

#### 1.3 Lead Scoring Algorithm

The lead scoring engine combines multiple signals into a weighted score:

**Signal Categories:**
1. **Demographic Signals (30% weight)**
   - Email domain quality (corporate vs free email)
   - Company size (employees)
   - Industry match with ICP
   - Geographic location

2. **Behavioral Signals (40% weight)**
   - Website visits (frequency and recency)
   - Email engagement (opens, clicks)
   - Form submissions
   - Content downloads
   - Time on site

3. **Engagement Signals (20% weight)**
   - Days since last activity (recency decay)
   - Response rate to outreach
   - Meeting attendance
   - Social media interactions

4. **Pipeline Signals (10% weight)**
   - Current stage in pipeline
   - Stage velocity (time in current stage)
   - Previous stage conversions

**Scoring Formula:**
```python
base_score = (
    demographic_score * 0.30 +
    behavioral_score * 0.40 +
    engagement_score * 0.20 +
    pipeline_score * 0.10
)

# Apply recency decay
days_inactive = lead_data.days_since_last_activity
decay_factor = max(0.5, 1.0 - (days_inactive / 30) * 0.5)
final_score = int(base_score * decay_factor)

# Clamp to 0-100 range
final_score = max(0, min(100, final_score))
```

**Confidence Calculation:**
```python
# Confidence based on data completeness
available_signals = count_non_null_signals(lead_data)
total_signals = 12
confidence = available_signals / total_signals
```

#### 1.4 Intent Detection Strategy

Intent detection uses Anthropic Claude for natural language understanding:

**Intent Categories:**
- `buying_intent`: Customer expresses interest in purchasing or upgrading
- `churn_risk`: Customer expresses dissatisfaction or cancellation intent
- `support_request`: Customer needs technical help or has a question
- `neutral`: General conversation without clear intent

**Prompt Template:**
```python
INTENT_DETECTION_PROMPT = """
Analyze the following customer message and classify the intent.

Message: {message_text}

Context:
- Customer relationship age: {relationship_age_days} days
- Previous purchases: {previous_purchases}
- Recent support tickets: {support_tickets_count}
- Last interaction: {last_interaction_days_ago} days ago

Classify the intent as one of:
- buying_intent: Customer wants to purchase or upgrade
- churn_risk: Customer is unhappy or considering leaving
- support_request: Customer needs help or has questions
- neutral: General conversation

Respond in JSON format:
{
  "intent": "<category>",
  "confidence": <0.0-1.0>,
  "reasoning": "<brief explanation>"
}
"""
```

**Model Configuration:**
- Model: `claude-3-sonnet-20240229`
- Temperature: 0.1 (low for consistent classification)
- Max tokens: 200
- Timeout: 2 seconds

#### 1.5 Content Generation Strategy

Content generation uses OpenAI GPT-4 for high-quality responses:

**Tone Presets:**
- `professional`: Formal business language
- `friendly`: Warm and conversational
- `concise`: Brief and to-the-point

**Prompt Template:**
```python
CONTENT_GENERATION_PROMPT = """
You are a helpful customer service agent. Generate a {tone} response to the customer's message.

Conversation history:
{conversation_history}

Customer's latest message:
{message_text}

Generate a response that:
1. Addresses the customer's question or concern
2. Maintains a {tone} tone
3. Is clear and actionable
4. Includes next steps if appropriate

Response:
"""
```

**Model Configuration:**
- Model: `gpt-4-turbo-preview`
- Temperature: 0.7 (balanced creativity)
- Max tokens: 500
- Timeout: 4 seconds

#### 1.6 Authentication and Rate Limiting

**JWT Validation:**
```python
from fastapi import Security, HTTPException
from fastapi.security import HTTPBearer
import jwt

security = HTTPBearer()

async def validate_jwt(credentials: HTTPAuthorizationCredentials = Security(security)):
    token = credentials.credentials
    try:
        payload = jwt.decode(
            token,
            JWT_SECRET,
            algorithms=["HS256"],
            audience="ai-engine",
            issuer="nexsaas-platform"
        )
        return payload
    except jwt.InvalidTokenError:
        raise HTTPException(status_code=401, detail="Invalid authentication token")
```

**Rate Limiting:**
```python
from fastapi import Request
from slowapi import Limiter
from slowapi.util import get_remote_address

limiter = Limiter(key_func=lambda request: request.state.tenant_id)

@app.post("/api/v1/leads/score")
@limiter.limit("100/minute")
async def score_lead(request: Request, payload: LeadScoreRequest):
    # Endpoint logic
    pass
```

#### 1.7 Error Handling and Retries

**External API Retry Strategy:**
```python
from tenacity import retry, stop_after_attempt, wait_exponential, retry_if_exception_type

@retry(
    stop=stop_after_attempt(3),
    wait=wait_exponential(multiplier=1, min=1, max=10),
    retry=retry_if_exception_type((TimeoutError, ConnectionError)),
    reraise=True
)
async def call_openai_api(prompt: str) -> str:
    response = await openai_client.chat.completions.create(
        model="gpt-4-turbo-preview",
        messages=[{"role": "user", "content": prompt}],
        timeout=10.0
    )
    return response.choices[0].message.content
```

**Error Response Format:**
```json
{
  "error": {
    "code": "SERVICE_UNAVAILABLE",
    "message": "OpenAI API is temporarily unavailable",
    "details": "Failed after 3 retry attempts",
    "retry_after": 60
  }
}
```

#### 1.8 Logging and Observability

**Structured Logging:**
```python
import structlog

logger = structlog.get_logger()

@app.post("/api/v1/leads/score")
async def score_lead(request: Request, payload: LeadScoreRequest):
    start_time = time.time()
    
    logger.info(
        "lead_score_request",
        tenant_id=payload.tenant_id,
        lead_id=payload.lead_id,
        endpoint="/api/v1/leads/score"
    )
    
    try:
        result = await lead_scorer.score(payload.lead_data)
        duration_ms = (time.time() - start_time) * 1000
        
        logger.info(
            "lead_score_success",
            tenant_id=payload.tenant_id,
            lead_id=payload.lead_id,
            score=result.score,
            confidence=result.confidence,
            duration_ms=duration_ms
        )
        
        return result
    except Exception as e:
        logger.error(
            "lead_score_error",
            tenant_id=payload.tenant_id,
            lead_id=payload.lead_id,
            error=str(e),
            duration_ms=(time.time() - start_time) * 1000
        )
        raise
```

---

### 2. Billing System (PHP Laravel)

#### 2.1 Service Structure

```
modular_core/modules/Platform/Billing/
├── BillingController.php          # REST API endpoints
├── StripeService.php               # Stripe API wrapper
├── SubscriptionManager.php         # Subscription lifecycle
├── WebhookHandler.php              # Stripe webhook processor
├── UsageMeteringService.php        # AI usage tracking
├── GracePeriodService.php          # Payment failure handling
├── InvoiceService.php              # Invoice generation
└── TenantLockoutMiddleware.php     # Access control
```

#### 2.2 Database Schema

**tenants table additions:**
```sql
ALTER TABLE tenants ADD COLUMN stripe_customer_id VARCHAR(255);
ALTER TABLE tenants ADD COLUMN stripe_subscription_id VARCHAR(255);
ALTER TABLE tenants ADD COLUMN current_tier ENUM('starter', 'growth', 'enterprise');
ALTER TABLE tenants ADD COLUMN billing_period_start TIMESTAMP;
ALTER TABLE tenants ADD COLUMN billing_period_end TIMESTAMP;
ALTER TABLE tenants ADD COLUMN subscription_status ENUM('active', 'past_due', 'canceled', 'unpaid');
ALTER TABLE tenants ADD COLUMN grace_period_start TIMESTAMP NULL;
ALTER TABLE tenants ADD COLUMN grace_period_end TIMESTAMP NULL;
ALTER TABLE tenants ADD COLUMN access_locked_at TIMESTAMP NULL;
ALTER TABLE tenants ADD COLUMN last_score_run TIMESTAMP NULL;
```

**subscriptions table:**
```sql
CREATE TABLE subscriptions (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    stripe_subscription_id VARCHAR(255) NOT NULL UNIQUE,
    stripe_customer_id VARCHAR(255) NOT NULL,
    tier ENUM('starter', 'growth', 'enterprise') NOT NULL,
    status ENUM('active', 'past_due', 'canceled', 'unpaid', 'trialing') NOT NULL,
    current_period_start TIMESTAMP NOT NULL,
    current_period_end TIMESTAMP NOT NULL,
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    canceled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id),
    INDEX idx_stripe_subscription (stripe_subscription_id)
);
```

**invoices table:**
```sql
CREATE TABLE invoices (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    stripe_invoice_id VARCHAR(255) NOT NULL UNIQUE,
    invoice_number VARCHAR(100) NOT NULL,
    amount_due INT NOT NULL COMMENT 'Amount in cents',
    amount_paid INT NOT NULL COMMENT 'Amount in cents',
    tax_amount INT DEFAULT 0 COMMENT 'Tax in cents',
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('draft', 'open', 'paid', 'void', 'uncollectible') NOT NULL,
    invoice_pdf_url TEXT,
    due_date TIMESTAMP,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status)
);
```

**usage_records table:**
```sql
CREATE TABLE usage_records (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    resource_type ENUM('ai_requests', 'storage_gb', 'api_calls') NOT NULL,
    quantity INT NOT NULL,
    recorded_at TIMESTAMP NOT NULL,
    billing_period_start TIMESTAMP NOT NULL,
    billing_period_end TIMESTAMP NOT NULL,
    synced_to_stripe BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_period (tenant_id, billing_period_start, billing_period_end),
    INDEX idx_sync_status (synced_to_stripe)
);
```

**webhook_events table:**
```sql
CREATE TABLE webhook_events (
    id CHAR(36) PRIMARY KEY,
    stripe_event_id VARCHAR(255) NOT NULL UNIQUE,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    processed BOOLEAN DEFAULT FALSE,
    processing_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_processed (processed)
);
```

#### 2.3 Pricing Configuration

**Stripe Price IDs (configured in .env):**
```env
STRIPE_PRICE_STARTER=price_1234567890abcdef
STRIPE_PRICE_GROWTH=price_0987654321fedcba
STRIPE_PRICE_ENTERPRISE_BASE=price_abcdef1234567890
STRIPE_PRICE_ENTERPRISE_USAGE=price_fedcba0987654321
```

**Tier Limits:**
```php
class PricingTier {
    const STARTER = [
        'name' => 'Starter',
        'price_monthly' => 4900, // cents
        'max_users' => 5,
        'ai_requests_included' => 10000,
        'storage_gb' => 10,
        'features' => ['basic_crm', 'email_integration', 'lead_scoring']
    ];
    
    const GROWTH = [
        'name' => 'Growth',
        'price_monthly' => 14900,
        'max_users' => 25,
        'ai_requests_included' => 50000,
        'storage_gb' => 100,
        'features' => ['basic_crm', 'email_integration', 'lead_scoring', 'whatsapp', 'intent_detection', 'ai_replies']
    ];
    
    const ENTERPRISE = [
        'name' => 'Enterprise',
        'price_monthly' => 49900,
        'max_users' => -1, // unlimited
        'ai_requests_included' => 200000,
        'storage_gb' => 1000,
        'overage_per_1000_requests' => 1000, // $10 per 1000 requests
        'features' => ['all']
    ];
}
```

#### 2.4 API Endpoints

**POST /api/v1/billing/checkout**
- **Purpose**: Create Stripe Checkout Session
- **Authentication**: JWT (Owner or Admin role)
- **Request**:
```json
{
  "tenant_id": "uuid",
  "tier": "growth",
  "success_url": "https://app.nexsaas.com/billing/success",
  "cancel_url": "https://app.nexsaas.com/billing"
}
```
- **Response**:
```json
{
  "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
  "session_id": "cs_test_1234567890"
}
```

**POST /api/v1/billing/change-tier**
- **Purpose**: Upgrade or downgrade subscription
- **Authentication**: JWT (Owner or Admin role)
- **Request**:
```json
{
  "tenant_id": "uuid",
  "new_tier": "enterprise"
}
```
- **Response**:
```json
{
  "subscription_id": "sub_1234567890",
  "current_tier": "enterprise",
  "effective_date": "2024-01-15T00:00:00Z",
  "proration_amount": 12500,
  "next_invoice_date": "2024-02-01T00:00:00Z"
}
```

**POST /api/v1/billing/cancel**
- **Purpose**: Cancel subscription
- **Authentication**: JWT (Owner or Admin role)
- **Request**:
```json
{
  "tenant_id": "uuid",
  "cancel_at_period_end": true,
  "cancellation_reason": "switching_to_competitor"
}
```
- **Response**:
```json
{
  "subscription_id": "sub_1234567890",
  "status": "active",
  "cancel_at_period_end": true,
  "cancellation_date": "2024-02-01T00:00:00Z"
}
```

**GET /api/v1/billing/invoices**
- **Purpose**: List billing history
- **Authentication**: JWT (Owner or Admin role)
- **Response**:
```json
{
  "invoices": [
    {
      "id": "uuid",
      "invoice_number": "INV-2024-001",
      "amount_due": 14900,
      "tax_amount": 2235,
      "currency": "USD",
      "status": "paid",
      "invoice_pdf_url": "https://...",
      "due_date": "2024-01-01T00:00:00Z",
      "paid_at": "2024-01-01T10:30:00Z"
    }
  ],
  "total": 1,
  "page": 1,
  "per_page": 20
}
```

**GET /api/v1/billing/usage**
- **Purpose**: Current billing period usage
- **Authentication**: JWT (Owner or Admin role)
- **Response**:
```json
{
  "billing_period_start": "2024-01-01T00:00:00Z",
  "billing_period_end": "2024-01-31T23:59:59Z",
  "tier": "growth",
  "usage": {
    "ai_requests": {
      "included": 50000,
      "used": 32450,
      "remaining": 17550,
      "overage": 0
    },
    "storage_gb": {
      "included": 100,
      "used": 45.2,
      "remaining": 54.8
    },
    "active_users": {
      "limit": 25,
      "current": 18
    }
  },
  "estimated_overage_charges": 0
}
```

#### 2.5 Subscription State Machine

**States:**
- `trialing`: Free trial period (14 days)
- `active`: Subscription paid and current
- `past_due`: Payment failed, in grace period
- `unpaid`: Grace period expired, access locked
- `canceled`: Subscription terminated

**State Transitions:**
```mermaid
stateDiagram-v2
    [*] --> trialing: Checkout completed
    trialing --> active: Trial ends + payment succeeds
    trialing --> unpaid: Trial ends + payment fails
    active --> past_due: Payment fails
    past_due --> active: Payment succeeds
    past_due --> unpaid: Grace period expires
    unpaid --> active: Payment succeeds
    active --> canceled: User cancels
    past_due --> canceled: User cancels
    unpaid --> canceled: User cancels
    canceled --> [*]
```

**Transition Logic:**
```php
class SubscriptionStateMachine {
    public function handlePaymentFailed(Subscription $subscription): void {
        $subscription->status = 'past_due';
        $subscription->tenant->grace_period_start = now();
        $subscription->tenant->grace_period_end = now()->addDays(7);
        $subscription->save();
        
        // Send notification
        $this->notificationService->sendPaymentFailedEmail($subscription->tenant);
        
        // Schedule retries
        $this->schedulePaymentRetries($subscription);
    }
    
    public function handlePaymentSucceeded(Subscription $subscription): void {
        $subscription->status = 'active';
        $subscription->tenant->grace_period_start = null;
        $subscription->tenant->grace_period_end = null;
        $subscription->tenant->access_locked_at = null;
        $subscription->save();
    }
    
    public function handleGracePeriodExpired(Subscription $subscription): void {
        $subscription->status = 'unpaid';
        $subscription->tenant->access_locked_at = now();
        $subscription->save();
        
        // Send final notice
        $this->notificationService->sendAccessLockedEmail($subscription->tenant);
    }
}
```

#### 2.6 Webhook Processing

**Webhook Handler:**
```php
class StripeWebhookHandler {
    public function handle(Request $request): Response {
        // Verify signature
        $signature = $request->header('Stripe-Signature');
        try {
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            Log::error('Invalid webhook signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }
        
        // Store event for idempotency
        $webhookEvent = WebhookEvent::firstOrCreate(
            ['stripe_event_id' => $event->id],
            [
                'event_type' => $event->type,
                'payload' => $event->data->toArray()
            ]
        );
        
        if ($webhookEvent->processed) {
            return response()->json(['status' => 'already_processed'], 200);
        }
        
        // Process event
        try {
            $this->processEvent($event);
            $webhookEvent->processed = true;
            $webhookEvent->processed_at = now();
            $webhookEvent->save();
        } catch (\Exception $e) {
            $webhookEvent->processing_error = $e->getMessage();
            $webhookEvent->save();
            throw $e;
        }
        
        return response()->json(['status' => 'success'], 200);
    }
    
    private function processEvent(\Stripe\Event $event): void {
        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($event),
            'invoice.payment_failed' => $this->handlePaymentFailed($event),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            'invoice.created' => $this->handleInvoiceCreated($event),
            default => Log::info('Unhandled webhook event', ['type' => $event->type])
        };
    }
}
```

**Event Handlers:**
```php
private function handleCheckoutCompleted(\Stripe\Event $event): void {
    $session = $event->data->object;
    $tenant = Tenant::where('id', $session->metadata->tenant_id)->firstOrFail();
    
    // Create subscription record
    $subscription = Subscription::create([
        'tenant_id' => $tenant->id,
        'stripe_subscription_id' => $session->subscription,
        'stripe_customer_id' => $session->customer,
        'tier' => $session->metadata->tier,
        'status' => 'active',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth()
    ]);
    
    // Update tenant
    $tenant->update([
        'stripe_customer_id' => $session->customer,
        'stripe_subscription_id' => $session->subscription,
        'current_tier' => $session->metadata->tier,
        'subscription_status' => 'active',
        'access_locked_at' => null
    ]);
    
    // Send welcome email
    Mail::to($tenant->owner->email)->send(new SubscriptionActivated($tenant));
}

private function handlePaymentFailed(\Stripe\Event $event): void {
    $invoice = $event->data->object;
    $subscription = Subscription::where('stripe_subscription_id', $invoice->subscription)->firstOrFail();
    
    $this->stateMachine->handlePaymentFailed($subscription);
    
    // Schedule retries on days 3, 5, 7
    dispatch(new RetryPayment($subscription))->delay(now()->addDays(3));
    dispatch(new RetryPayment($subscription))->delay(now()->addDays(5));
    dispatch(new RetryPayment($subscription))->delay(now()->addDays(7));
}
```

#### 2.7 Usage Metering

**Usage Tracking:**
```php
class UsageMeteringService {
    public function recordAIRequest(string $tenantId, string $endpoint): void {
        $tenant = Tenant::findOrFail($tenantId);
        
        UsageRecord::create([
            'tenant_id' => $tenantId,
            'resource_type' => 'ai_requests',
            'quantity' => 1,
            'recorded_at' => now(),
            'billing_period_start' => $tenant->billing_period_start,
            'billing_period_end' => $tenant->billing_period_end
        ]);
        
        // Check if over limit
        $usage = $this->getCurrentUsage($tenantId);
        if ($usage['ai_requests']['overage'] > 0 && $tenant->current_tier === 'enterprise') {
            $this->reportUsageToStripe($tenant, $usage['ai_requests']['overage']);
        }
    }
    
    private function reportUsageToStripe(Tenant $tenant, int $overageCount): void {
        \Stripe\SubscriptionItem::createUsageRecord(
            $tenant->stripe_subscription_item_id,
            [
                'quantity' => $overageCount,
                'timestamp' => now()->timestamp,
                'action' => 'set'
            ]
        );
        
        UsageRecord::where('tenant_id', $tenant->id)
            ->where('synced_to_stripe', false)
            ->update(['synced_to_stripe' => true]);
    }
}
```

#### 2.8 Tenant Lockout Middleware

**Access Control:**
```php
class TenantLockoutMiddleware {
    public function handle(Request $request, Closure $next) {
        $tenantId = $request->user()->tenant_id;
        $tenant = Tenant::findOrFail($tenantId);
        
        // Allow billing endpoints even when locked
        if (Str::startsWith($request->path(), 'api/v1/billing')) {
            return $next($request);
        }
        
        // Check subscription status
        if (in_array($tenant->subscription_status, ['canceled', 'past_due', 'unpaid'])) {
            return response()->json([
                'error' => 'Subscription expired. Please update billing.',
                'subscription_status' => $tenant->subscription_status,
                'billing_url' => route('billing.index')
            ], 403);
        }
        
        // Check grace period expiration
        if ($tenant->grace_period_end && now()->isAfter($tenant->grace_period_end)) {
            $tenant->update([
                'subscription_status' => 'unpaid',
                'access_locked_at' => now()
            ]);
            
            return response()->json([
                'error' => 'Grace period expired. Please update payment method.',
                'billing_url' => route('billing.index')
            ], 403);
        }
        
        return $next($request);
    }
}
```

---

### 3. Real-time WebSockets (Soketi)

#### 3.1 Soketi Configuration

**Docker Compose:**
```yaml
services:
  soketi:
    image: quay.io/soketi/soketi:1.6-16-debian
    ports:
      - "6001:6001"
      - "6002:6002"
    environment:
      SOKETI_DEBUG: "1"
      SOKETI_DEFAULT_APP_ID: "nexsaas-app"
      SOKETI_DEFAULT_APP_KEY: "${SOKETI_APP_KEY}"
      SOKETI_DEFAULT_APP_SECRET: "${SOKETI_APP_SECRET}"
      SOKETI_USER_AUTHENTICATION_TIMEOUT: "30000"
    networks:
      - nexsaas-network
```

**Nginx SSL Termination:**
```nginx
upstream soketi {
    server soketi:6001;
}

server {
    listen 443 ssl http2;
    server_name ws.nexsaas.com;
    
    ssl_certificate /etc/nginx/ssl/nexsaas.crt;
    ssl_certificate_key /etc/nginx/ssl/nexsaas.key;
    
    location / {
        proxy_pass http://soketi;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }
}
```

#### 3.2 Laravel Broadcasting Configuration

**config/broadcasting.php:**
```php
'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'host' => env('PUSHER_HOST', '127.0.0.1'),
            'port' => env('PUSHER_PORT', 6001),
            'scheme' => env('PUSHER_SCHEME', 'http'),
            'encrypted' => true,
            'useTLS' => env('PUSHER_SCHEME') === 'https',
        ],
    ],
]
```

**.env:**
```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=nexsaas-app
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
```

#### 3.3 Channel Structure

**Public Channels:**
- `tenant.{tenant_id}.messages` - New message notifications
- `tenant.{tenant_id}.leads` - Lead status updates
- `tenant.{tenant_id}.tasks` - Task assignments

**Private Channels:**
- `private-user.{user_id}` - Personal notifications
- `private-conversation.{conversation_id}` - Conversation-specific events

**Presence Channels:**
- `presence-tenant.{tenant_id}` - Online users in tenant
- `presence-conversation.{conversation_id}` - Active participants in conversation

#### 3.4 Channel Authorization

**routes/channels.php:**
```php
Broadcast::channel('tenant.{tenantId}.messages', function ($user, $tenantId) {
    return $user->tenant_id === $tenantId;
});

Broadcast::channel('private-user.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});

Broadcast::channel('presence-tenant.{tenantId}', function ($user, $tenantId) {
    if ($user->tenant_id === $tenantId) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'avatar_url' => $user->avatar_url
        ];
    }
});

Broadcast::channel('presence-conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    if ($conversation && $conversation->tenant_id === $user->tenant_id) {
        return [
            'id' => $user->id,
            'name' => $user->name
        ];
    }
});
```

#### 3.5 Event Broadcasting

**Message Received Event:**
```php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcast {
    use InteractsWithSockets, SerializesModels;
    
    public function __construct(
        public string $tenantId,
        public string $messageId,
        public string $senderName,
        public string $senderChannel,
        public string $previewText,
        public ?string $assignedAgentId
    ) {}
    
    public function broadcastOn(): Channel {
        return new Channel("tenant.{$this->tenantId}.messages");
    }
    
    public function broadcastAs(): string {
        return 'message.received';
    }
    
    public function broadcastWith(): array {
        return [
            'message_id' => $this->messageId,
            'sender_name' => $this->senderName,
            'sender_channel' => $this->senderChannel,
            'preview_text' => $this->previewText,
            'assigned_agent_id' => $this->assignedAgentId,
            'timestamp' => now()->toIso8601String()
        ];
    }
}
```

**Lead Updated Event:**
```php
class LeadUpdated implements ShouldBroadcast {
    public function __construct(
        public string $tenantId,
        public string $leadId,
        public array $updatedFields,
        public string $updatedByUserId
    ) {}
    
    public function broadcastOn(): Channel {
        return new Channel("tenant.{$this->tenantId}.leads");
    }
    
    public function broadcastAs(): string {
        return 'lead.updated';
    }
    
    public function broadcastWith(): array {
        return [
            'lead_id' => $this->leadId,
            'updated_fields' => $this->updatedFields,
            'updated_by_user_id' => $this->updatedByUserId,
            'timestamp' => now()->toIso8601String()
        ];
    }
}
```

**Typing Indicator Events:**
```php
class TypingStarted implements ShouldBroadcast {
    public function __construct(
        public string $conversationId,
        public string $userId,
        public string $userName
    ) {}
    
    public function broadcastOn(): PresenceChannel {
        return new PresenceChannel("presence-conversation.{$this->conversationId}");
    }
    
    public function broadcastAs(): string {
        return 'typing.start';
    }
}

class TypingStopped implements ShouldBroadcast {
    public function __construct(
        public string $conversationId,
        public string $userId
    ) {}
    
    public function broadcastOn(): PresenceChannel {
        return new PresenceChannel("presence-conversation.{$this->conversationId}");
    }
    
    public function broadcastAs(): string {
        return 'typing.stop';
    }
}
```

**Message Seen Event:**
```php
class MessageSeen implements ShouldBroadcast {
    public function __construct(
        public string $conversationId,
        public string $messageId,
        public string $seenByUserId,
        public string $seenAt
    ) {}
    
    public function broadcastOn(): PresenceChannel {
        return new PresenceChannel("presence-conversation.{$this->conversationId}");
    }
    
    public function broadcastAs(): string {
        return 'message.seen';
    }
}
```

#### 3.6 Frontend Integration

**Pusher Client Setup (React):**
```typescript
import Pusher from 'pusher-js';

const pusher = new Pusher(process.env.REACT_APP_PUSHER_KEY, {
  wsHost: process.env.REACT_APP_PUSHER_HOST,
  wsPort: parseInt(process.env.REACT_APP_PUSHER_PORT),
  forceTLS: process.env.REACT_APP_PUSHER_SCHEME === 'https',
  encrypted: true,
  disableStats: true,
  enabledTransports: ['ws', 'wss'],
  authEndpoint: '/api/broadcasting/auth',
  auth: {
    headers: {
      Authorization: `Bearer ${getAuthToken()}`
    }
  }
});

export default pusher;
```

**Message Notifications:**
```typescript
import { useEffect } from 'react';
import pusher from './pusher';
import { useAuth } from './hooks/useAuth';

export function useMessageNotifications() {
  const { user } = useAuth();
  
  useEffect(() => {
    if (!user) return;
    
    const channel = pusher.subscribe(`tenant.${user.tenant_id}.messages`);
    
    channel.bind('message.received', (data: MessageReceivedEvent) => {
      // Show browser notification
      if (Notification.permission === 'granted') {
        new Notification(`New message from ${data.sender_name}`, {
          body: data.preview_text,
          icon: '/logo.png'
        });
      }
      
      // Play sound
      const audio = new Audio('/notification.mp3');
      audio.play();
      
      // Update inbox UI
      queryClient.invalidateQueries(['messages']);
    });
    
    return () => {
      channel.unbind_all();
      channel.unsubscribe();
    };
  }, [user]);
}
```

**Typing Indicators:**
```typescript
export function useTypingIndicator(conversationId: string) {
  const [typingUsers, setTypingUsers] = useState<string[]>([]);
  const typingTimeoutRef = useRef<NodeJS.Timeout>();
  
  useEffect(() => {
    const channel = pusher.subscribe(`presence-conversation.${conversationId}`);
    
    channel.bind('typing.start', (data: { user_id: string; user_name: string }) => {
      setTypingUsers(prev => [...new Set([...prev, data.user_name])]);
    });
    
    channel.bind('typing.stop', (data: { user_id: string }) => {
      setTypingUsers(prev => prev.filter(name => name !== data.user_name));
    });
    
    return () => {
      channel.unbind_all();
      channel.unsubscribe();
    };
  }, [conversationId]);
  
  const handleTyping = () => {
    // Trigger typing.start
    fetch(`/api/v1/conversations/${conversationId}/typing`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: 'start' })
    });
    
    // Auto-stop after 3 seconds
    if (typingTimeoutRef.current) {
      clearTimeout(typingTimeoutRef.current);
    }
    
    typingTimeoutRef.current = setTimeout(() => {
      fetch(`/api/v1/conversations/${conversationId}/typing`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: 'stop' })
      });
    }, 3000);
  };
  
  return { typingUsers, handleTyping };
}
```

**Online Status:**
```typescript
export function useOnlineStatus() {
  const { user } = useAuth();
  const [onlineUsers, setOnlineUsers] = useState<OnlineUser[]>([]);
  
  useEffect(() => {
    if (!user) return;
    
    const channel = pusher.subscribe(`presence-tenant.${user.tenant_id}`);
    
    channel.bind('pusher:subscription_succeeded', (members: any) => {
      const users = Object.values(members.members) as OnlineUser[];
      setOnlineUsers(users);
    });
    
    channel.bind('pusher:member_added', (member: any) => {
      setOnlineUsers(prev => [...prev, member.info]);
    });
    
    channel.bind('pusher:member_removed', (member: any) => {
      setOnlineUsers(prev => prev.filter(u => u.id !== member.id));
    });
    
    return () => {
      channel.unbind_all();
      channel.unsubscribe();
    };
  }, [user]);
  
  return { onlineUsers };
}
```

---

## Data Models

### AI Engine Models (Python Pydantic)

**Lead Score Request:**
```python
from pydantic import BaseModel, Field
from typing import Optional

class LeadData(BaseModel):
    email_domain: str
    company_size: Optional[int] = None
    industry: Optional[str] = None
    website_visits: int = 0
    email_opens: int = 0
    link_clicks: int = 0
    form_submissions: int = 0
    days_since_last_activity: int
    pipeline_stage: str

class LeadScoreRequest(BaseModel):
    lead_id: str = Field(..., description="UUID of the lead")
    tenant_id: str = Field(..., description="UUID of the tenant")
    lead_data: LeadData

class ScoringFactor(BaseModel):
    name: str
    weight: float = Field(..., ge=0.0, le=1.0)

class LeadScoreResponse(BaseModel):
    score: int = Field(..., ge=0, le=100)
    confidence: float = Field(..., ge=0.0, le=1.0)
    factors: list[ScoringFactor]
    model_version: str
```

**Intent Detection Models:**
```python
from enum import Enum

class IntentCategory(str, Enum):
    BUYING_INTENT = "buying_intent"
    CHURN_RISK = "churn_risk"
    SUPPORT_REQUEST = "support_request"
    NEUTRAL = "neutral"

class SenderContext(BaseModel):
    relationship_age_days: int
    previous_purchases: int
    support_tickets_count: int
    last_interaction_days_ago: int

class IntentDetectionRequest(BaseModel):
    message_id: str
    tenant_id: str
    message_text: str = Field(..., max_length=10000)
    sender_context: SenderContext

class IntentDetectionResponse(BaseModel):
    intent: IntentCategory
    confidence: float = Field(..., ge=0.0, le=1.0)
    reasoning: str
    model_version: str
```

**Content Generation Models:**
```python
class TonePreset(str, Enum):
    PROFESSIONAL = "professional"
    FRIENDLY = "friendly"
    CONCISE = "concise"

class ConversationMessage(BaseModel):
    role: str  # "customer" or "agent"
    text: str
    timestamp: Optional[str] = None

class ContentGenerationRequest(BaseModel):
    message_id: str
    tenant_id: str
    message_text: str
    conversation_history: list[ConversationMessage]
    tone: TonePreset = TonePreset.PROFESSIONAL

class ContentGenerationResponse(BaseModel):
    draft_text: str
    confidence: float = Field(..., ge=0.0, le=1.0)
    model_version: str
```

**Action Suggestion Models:**
```python
class ActionType(str, Enum):
    SCHEDULE_DEMO = "schedule_demo"
    SEND_PRICING = "send_pricing"
    FOLLOW_UP = "follow_up"
    CREATE_DEAL = "create_deal"
    ESCALATE = "escalate"

class ActionPriority(str, Enum):
    HIGH = "high"
    MEDIUM = "medium"
    LOW = "low"

class SuggestedAction(BaseModel):
    action_type: ActionType
    description: str
    priority: ActionPriority
    due_date_suggestion: str  # ISO date

class ContactContext(BaseModel):
    company_name: Optional[str] = None
    industry: Optional[str] = None
    current_tier: Optional[str] = None

class ActionSuggestionRequest(BaseModel):
    message_id: str
    tenant_id: str
    conversation_summary: str
    contact_context: ContactContext

class ActionSuggestionResponse(BaseModel):
    actions: list[SuggestedAction]
    model_version: str
```

### Billing System Models (PHP)

**Subscription Model:**
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model {
    protected $fillable = [
        'tenant_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'tier',
        'status',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at'
    ];
    
    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'cancel_at_period_end' => 'boolean'
    ];
    
    public function tenant(): BelongsTo {
        return $this->belongsTo(Tenant::class);
    }
    
    public function isActive(): bool {
        return $this->status === 'active';
    }
    
    public function isInGracePeriod(): bool {
        return $this->status === 'past_due' 
            && $this->tenant->grace_period_end 
            && now()->isBefore($this->tenant->grace_period_end);
    }
    
    public function daysUntilExpiration(): ?int {
        if (!$this->tenant->grace_period_end) {
            return null;
        }
        return now()->diffInDays($this->tenant->grace_period_end, false);
    }
}
```

**Invoice Model:**
```php
class Invoice extends Model {
    protected $fillable = [
        'tenant_id',
        'stripe_invoice_id',
        'invoice_number',
        'amount_due',
        'amount_paid',
        'tax_amount',
        'currency',
        'status',
        'invoice_pdf_url',
        'due_date',
        'paid_at'
    ];
    
    protected $casts = [
        'amount_due' => 'integer',
        'amount_paid' => 'integer',
        'tax_amount' => 'integer',
        'due_date' => 'datetime',
        'paid_at' => 'datetime'
    ];
    
    public function tenant(): BelongsTo {
        return $this->belongsTo(Tenant::class);
    }
    
    public function getAmountDueFormattedAttribute(): string {
        return number_format($this->amount_due / 100, 2);
    }
    
    public function getTaxAmountFormattedAttribute(): string {
        return number_format($this->tax_amount / 100, 2);
    }
}
```

**Usage Record Model:**
```php
class UsageRecord extends Model {
    protected $fillable = [
        'tenant_id',
        'resource_type',
        'quantity',
        'recorded_at',
        'billing_period_start',
        'billing_period_end',
        'synced_to_stripe'
    ];
    
    protected $casts = [
        'quantity' => 'integer',
        'recorded_at' => 'datetime',
        'billing_period_start' => 'datetime',
        'billing_period_end' => 'datetime',
        'synced_to_stripe' => 'boolean'
    ];
    
    public function tenant(): BelongsTo {
        return $this->belongsTo(Tenant::class);
    }
}
```

---

## Correctness Properties

A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.

### Property 1: AI Engine Response Schema Completeness

For any valid request to AI Engine endpoints (/api/v1/leads/score, /api/v1/messages/detect-intent, /api/v1/content/generate-reply, /api/v1/content/suggest-actions), the response SHALL contain all required fields as defined in the endpoint specification (score/intent/draft_text/actions, confidence, model_version, and endpoint-specific fields).

**Validates: Requirements 1.3, 3.3, 4.3, 5.3**

### Property 2: Lead Score Range Invariant

For any valid lead data submitted to POST /api/v1/leads/score, the returned score SHALL be an integer in the range [0, 100] inclusive.

**Validates: Requirements 1.2**

### Property 3: Data Persistence Round-Trip

For any AI Engine operation (lead scoring, intent detection) that returns data to be persisted, retrieving the record from the database SHALL return the same values that were provided by the AI Engine (score, confidence, factors, intent, reasoning).

**Validates: Requirements 1.5, 3.5, 8.7, 9.5, 10.4**

### Property 4: Lead Batching Correctness

For any list of N leads where N > 0, batching them into groups of 100 SHALL produce ceil(N/100) batches, where each batch except possibly the last contains exactly 100 leads, and the last batch contains N mod 100 leads (or 100 if N mod 100 = 0).

**Validates: Requirements 2.2**

### Property 5: Batch Processing Resilience

For any batch of leads being scored, if one lead fails to score after 3 retry attempts, the remaining leads in the batch SHALL still be processed and scored successfully.

**Validates: Requirements 2.4**

### Property 6: Scoring Run Timestamp Update

For any completed daily scoring run for a tenant, the tenant's last_score_run timestamp SHALL be updated to the completion time of that run.

**Validates: Requirements 2.6**

### Property 7: Intent Classification Enum Constraint

For any valid message submitted to POST /api/v1/messages/detect-intent, the returned intent SHALL be one of the four enumerated values: "buying_intent", "churn_risk", "support_request", or "neutral".

**Validates: Requirements 3.2**

### Property 8: Intent Detection Event Enqueueing

For any new inbound message arriving via WhatsApp or Email, a message.intent_detection event SHALL be enqueued on the Queue.

**Validates: Requirements 3.4**

### Property 9: High-Confidence Intent Notification

For any message with detected intent of "buying_intent" and confidence > 0.7, a real-time notification SHALL be sent to the assigned agent via WebSocket.

**Validates: Requirements 3.7**

### Property 10: Churn Risk Task Creation

For any message with detected intent of "churn_risk" and confidence > 0.7, a high-priority Task SHALL be created for the account owner.

**Validates: Requirements 3.8**

### Property 11: Action Suggestion Count Limit

For any valid request to POST /api/v1/content/suggest-actions, the response SHALL contain at most 3 suggested actions.

**Validates: Requirements 5.2**

### Property 12: AI Draft Usage Logging

For any AI-generated draft reply, the system SHALL log whether the agent sent it unmodified, modified, or discarded.

**Validates: Requirements 4.7**

### Property 13: JWT Authentication Enforcement

For any request to the AI Engine without a valid JWT token, or with a JWT where the tenant_id claim does not match the request body tenant_id, the AI Engine SHALL return HTTP 401 Unauthorized.

**Validates: Requirements 6.3, 6.4**

### Property 14: Rate Limiting Enforcement

For any tenant making more than 100 requests to the AI Engine within a 1-minute window, the 101st and subsequent requests SHALL return HTTP 429 Too Many Requests with a Retry-After header.

**Validates: Requirements 6.6, 6.7**

### Property 15: Request Logging Completeness

For any request to the AI Engine, a structured JSON log entry SHALL be created containing tenant_id, user_id, endpoint, duration_ms, and status_code.

**Validates: Requirements 6.8**

### Property 16: External API Retry Behavior

For any call to an external AI API (OpenAI or Anthropic) that fails with a transient error (HTTP 429, 500, 503, timeout, connection error), the system SHALL retry up to 2 additional times with exponential backoff before failing.

**Validates: Requirements 7.3**

### Property 17: Retry Exhaustion Error Response

For any external AI API call where all retry attempts (initial + 2 retries = 3 total attempts) fail, the AI Engine SHALL return HTTP 503 Service Unavailable with a descriptive error message.

**Validates: Requirements 7.4**

### Property 18: AI Usage Tracking

For any successful call to an AI Engine endpoint, usage SHALL be tracked for the tenant including request count, tokens consumed, and cost estimate.

**Validates: Requirements 7.5**

### Property 19: Stripe Customer Creation

For any tenant creating their first subscription, a Stripe Customer record SHALL be created and the stripe_customer_id SHALL be stored on the tenant record.

**Validates: Requirements 8.2**

### Property 20: Checkout Session URL Generation

For any valid request to POST /api/v1/billing/checkout with a valid tier, a Stripe Checkout Session SHALL be created and a session URL SHALL be returned.

**Validates: Requirements 8.4**

### Property 21: Subscription Activation on Checkout Completion

For any completed Stripe Checkout Session, a Subscription record SHALL be created, linked to the tenant, and the tenant's access SHALL be activated (access_locked_at = null, subscription_status = 'active').

**Validates: Requirements 8.6**

### Property 22: Tier Upgrade Immediate Effect

For any subscription tier upgrade request, the Stripe subscription SHALL be updated immediately with proration, and the tenant's current_tier field SHALL be updated to the new tier.

**Validates: Requirements 9.2**

### Property 23: Tier Downgrade Deferred Effect

For any subscription tier downgrade request, the tier change SHALL be scheduled to take effect at the end of the current billing period (cancel_at_period_end behavior for the old tier).

**Validates: Requirements 9.3**

### Property 24: Downgrade User Limit Enforcement

For any downgrade request where the new tier's user limit is less than the tenant's current active user count, the system SHALL reject the downgrade request and return an error instructing the owner to deactivate users first.

**Validates: Requirements 9.4**

### Property 25: Scheduled Cancellation

For any cancellation request with cancel_at_period_end = true, the subscription SHALL remain active until the current_period_end, and the cancel_at_period_end flag SHALL be set to true.

**Validates: Requirements 10.2**

### Property 26: Immediate Cancellation and Lockout

For any cancellation request with cancel_at_period_end = false, the subscription status SHALL be set to 'canceled', and the tenant's access_locked_at timestamp SHALL be set to the current time.

**Validates: Requirements 10.3**

### Property 27: Canceled Tenant Access Restriction

For any tenant with subscription status 'canceled', 'past_due', or 'unpaid', all API requests except those to /api/v1/billing/* endpoints SHALL return HTTP 403 Forbidden.

**Validates: Requirements 10.5, 14.2**

### Property 28: Webhook Signature Validation

For any incoming Stripe webhook request with an invalid signature (not matching the webhook secret), the system SHALL return HTTP 400 Bad Request and SHALL NOT process the event.

**Validates: Requirements 11.2, 11.3**

### Property 29: Webhook Event Idempotency

For any Stripe webhook event with a given event_id, if the system receives the same event_id multiple times, only the first occurrence SHALL be processed, and subsequent occurrences SHALL return HTTP 200 with status "already_processed".

**Validates: Requirements 11.4**

### Property 30: Payment Success State Transition

For any invoice.payment_succeeded webhook event, the associated tenant's subscription status SHALL be updated to 'active', and any grace_period_start and grace_period_end timestamps SHALL be cleared (set to null).

**Validates: Requirements 11.5**

### Property 31: Payment Failure Grace Period Initiation

For any invoice.payment_failed webhook event, the associated tenant's grace_period_start SHALL be set to the current time, grace_period_end SHALL be set to 7 days later, and the subscription status SHALL be set to 'past_due'.

**Validates: Requirements 11.6, 13.1**

### Property 32: Subscription Deletion Lockout

For any customer.subscription.deleted webhook event, the associated tenant's subscription status SHALL be set to 'canceled' and access_locked_at SHALL be set to the current time.

**Validates: Requirements 11.7**

### Property 33: Webhook Event Audit Logging

For any Stripe webhook event received, a log entry SHALL be created containing event_id, event_type, tenant_id (if applicable), and processing_status.

**Validates: Requirements 11.9**

### Property 34: Invoice PDF Storage

For any invoice.created webhook event, the invoice PDF URL SHALL be retrieved from Stripe and stored in the invoices table linked to the tenant.

**Validates: Requirements 12.2**

### Property 35: Invoice Email Delivery

For any invoice created, an email SHALL be sent to the tenant owner's email address containing the invoice number, amount, due date, and a link to billing history.

**Validates: Requirements 12.3, 12.4**

### Property 36: Grace Period Email Reminders

For any tenant in a grace period, email reminders SHALL be sent to the owner on days 1, 3, 5, and 7 after grace_period_start.

**Validates: Requirements 13.3**

### Property 37: Grace Period Payment Success Recovery

For any successful payment that occurs while a tenant is in a grace period (grace_period_end is not null and is in the future), the grace_period_start and grace_period_end timestamps SHALL be cleared (set to null).

**Validates: Requirements 13.5**

### Property 38: Grace Period Expiration Lockout

For any tenant where the current time exceeds grace_period_end, the subscription status SHALL be set to 'unpaid' and access_locked_at SHALL be set to the current time.

**Validates: Requirements 13.6**

### Property 39: Subscription Status Check on Every Request

For any API request to the platform (except billing endpoints), the system SHALL check the tenant's subscription status before processing the request.

**Validates: Requirements 14.1**

### Property 40: Locked Tenant Billing Endpoint Access

For any tenant with access_locked_at set (not null), requests to /api/v1/billing/* endpoints SHALL still be processed successfully (not return 403).

**Validates: Requirements 14.3**

### Property 41: Subscription Reactivation Access Restoration

For any tenant with access_locked_at set, when their subscription is reactivated (status changes to 'active'), the access_locked_at timestamp SHALL be cleared (set to null).

**Validates: Requirements 14.4**

### Property 42: WebSocket Event Publishing on Entity Changes

For any change to a lead's status, score, or assigned owner, a lead.updated event SHALL be published to the WebSocket channel tenant.{tenant_id}.leads containing lead_id, updated_fields, and updated_by_user_id.

**Validates: Requirements 1.7, 18.1, 18.2**

### Property 43: Message Notification Event Publishing

For any new inbound message, a message.received event SHALL be published to the WebSocket channel tenant.{tenant_id}.messages containing message_id, sender_name, sender_channel, preview_text, and assigned_agent_id.

**Validates: Requirements 17.1, 17.2**

### Property 44: Message Seen Event Publishing

For any POST /api/v1/messages/{message_id}/mark-seen request, a message.seen event SHALL be published to the presence-conversation.{conversation_id} channel containing message_id and seen_by_user_id.

**Validates: Requirements 19.6**

### Property 45: Stripe Webhook Parsing Round-Trip

For any valid Stripe webhook Event object, serializing it to JSON then parsing it back SHALL produce an equivalent Event object with the same event_id, type, and data fields.

**Validates: Requirements 21.5**

### Property 46: AI Engine Request Validation

For any request to an AI Engine endpoint with JSON that does not match the defined schema (missing required fields, wrong types, etc.), the AI Engine SHALL return HTTP 400 Bad Request with validation errors describing the schema violations.

**Validates: Requirements 22.2**

### Property 47: AI Engine Response Serialization Round-Trip

For any AI Engine response object (LeadScoreResponse, IntentDetectionResponse, ContentGenerationResponse, ActionSuggestionResponse), serializing it to JSON then parsing it back SHALL produce an equivalent object with the same field values.

**Validates: Requirements 22.5**

---

## Error Handling

### AI Engine Error Handling

**Error Response Format:**
All AI Engine errors follow a consistent JSON structure:
```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": "Additional context or debugging information",
    "retry_after": 60  // Optional: seconds to wait before retrying
  }
}
```

**Error Categories:**

1. **Authentication Errors (HTTP 401)**
   - Invalid JWT token
   - Missing JWT token
   - Expired JWT token
   - Tenant ID mismatch between JWT claim and request body

2. **Authorization Errors (HTTP 403)**
   - User lacks required permissions for endpoint
   - Tenant subscription expired or locked

3. **Validation Errors (HTTP 400)**
   - Missing required fields in request
   - Invalid field types or formats
   - Field values out of acceptable range
   - Invalid enum values

4. **Rate Limiting Errors (HTTP 429)**
   - Tenant exceeded 100 requests per minute
   - Response includes Retry-After header with seconds to wait

5. **External Service Errors (HTTP 503)**
   - OpenAI API unavailable after retries
   - Anthropic API unavailable after retries
   - Timeout errors from external services

6. **Internal Server Errors (HTTP 500)**
   - Unexpected exceptions
   - Database connection failures
   - Unhandled edge cases

**Retry Strategy:**
- Transient errors (429, 500, 503, timeouts) trigger automatic retry with exponential backoff
- Initial retry after 1 second, second retry after 2 seconds, third retry after 4 seconds
- After 3 failed attempts, return 503 to client
- Non-transient errors (400, 401, 403) are not retried

**Circuit Breaker:**
If external AI APIs fail consistently (>50% error rate over 5 minutes), the circuit breaker opens:
- Subsequent requests fail fast with 503 without calling external API
- Circuit half-opens after 60 seconds to test if service recovered
- Circuit closes if test request succeeds

### Billing System Error Handling

**Stripe API Error Handling:**
```php
try {
    $session = \Stripe\Checkout\Session::create([...]);
} catch (\Stripe\Exception\CardException $e) {
    // Card was declined
    return response()->json([
        'error' => 'Payment method declined',
        'details' => $e->getMessage()
    ], 402);
} catch (\Stripe\Exception\RateLimitException $e) {
    // Too many requests to Stripe
    return response()->json([
        'error' => 'Service temporarily unavailable',
        'retry_after' => 60
    ], 503);
} catch (\Stripe\Exception\InvalidRequestException $e) {
    // Invalid parameters
    Log::error('Stripe invalid request', ['error' => $e->getMessage()]);
    return response()->json([
        'error' => 'Invalid billing request',
        'details' => $e->getMessage()
    ], 400);
} catch (\Stripe\Exception\AuthenticationException $e) {
    // Authentication with Stripe failed
    Log::critical('Stripe authentication failed', ['error' => $e->getMessage()]);
    return response()->json([
        'error' => 'Billing service configuration error'
    ], 500);
} catch (\Stripe\Exception\ApiConnectionException $e) {
    // Network communication failed
    return response()->json([
        'error' => 'Unable to connect to billing service',
        'retry_after' => 30
    ], 503);
} catch (\Exception $e) {
    // Generic error
    Log::error('Unexpected billing error', ['error' => $e->getMessage()]);
    return response()->json([
        'error' => 'An unexpected error occurred'
    ], 500);
}
```

**Webhook Processing Errors:**
- Invalid signature: Return 400, log attempt for security monitoring
- Duplicate event: Return 200 with "already_processed" status
- Processing exception: Log error, store in webhook_events.processing_error, return 500 (Stripe will retry)
- Unknown event type: Log for monitoring, return 200 (acknowledge receipt)

**Subscription State Errors:**
- Downgrade with too many users: Return 400 with error message listing active users
- Cancel non-existent subscription: Return 404
- Upgrade/downgrade during grace period: Allow but warn user
- Reactivate without payment method: Return 400 with instructions to add payment method

### WebSocket Error Handling

**Connection Errors:**
- Invalid authentication: Soketi returns 401, client should re-authenticate
- Channel authorization failed: Soketi returns 403, client should check permissions
- Connection timeout: Client should implement exponential backoff reconnection

**Event Publishing Errors:**
```php
try {
    event(new MessageReceived($tenantId, $messageId, ...));
} catch (\Exception $e) {
    // Log error but don't fail the main operation
    Log::error('Failed to publish WebSocket event', [
        'event' => 'MessageReceived',
        'tenant_id' => $tenantId,
        'error' => $e->getMessage()
    ]);
    
    // Continue processing - WebSocket is non-critical
    // Users can still see updates on page refresh
}
```

**Client-Side Reconnection:**
```typescript
pusher.connection.bind('error', (err: any) => {
  if (err.error.data.code === 4004) {
    // Over connection limit
    console.error('WebSocket connection limit exceeded');
  } else if (err.error.data.code === 4009) {
    // Connection closed by server
    console.log('Reconnecting...');
    setTimeout(() => pusher.connect(), 5000);
  }
});

pusher.connection.bind('disconnected', () => {
  console.log('WebSocket disconnected, will auto-reconnect');
});
```

---

## Testing Strategy

### Dual Testing Approach

This feature requires both unit testing and property-based testing for comprehensive coverage:

**Unit Tests** focus on:
- Specific examples and edge cases
- Integration points between components
- Error conditions and exception handling
- Mocked external service responses

**Property-Based Tests** focus on:
- Universal properties that hold for all inputs
- Comprehensive input coverage through randomization
- Invariants that must be maintained
- Round-trip properties for serialization

Together, unit tests catch concrete bugs while property tests verify general correctness across the input space.

### Property-Based Testing Configuration

**Python (AI Engine):**
- Library: Hypothesis
- Minimum iterations: 100 per property test
- Each test must reference its design property in a comment
- Tag format: `# Feature: ai-engine-billing, Property {number}: {property_text}`

**PHP (Billing System):**
- Library: Eris (PHP port of QuickCheck)
- Minimum iterations: 100 per property test
- Each test must reference its design property in a docblock
- Tag format: `@property Feature: ai-engine-billing, Property {number}: {property_text}`

### AI Engine Test Structure

```
ai_engine/tests/
├── unit/
│   ├── test_lead_scorer.py
│   ├── test_intent_detector.py
│   ├── test_content_generator.py
│   └── test_auth.py
├── properties/
│   ├── test_response_schemas.py
│   ├── test_score_range.py
│   ├── test_rate_limiting.py
│   ├── test_retry_behavior.py
│   └── test_serialization.py
└── integration/
    ├── test_openai_integration.py
    └── test_anthropic_integration.py
```

**Example Property Test (Python/Hypothesis):**
```python
from hypothesis import given, strategies as st
import pytest

# Feature: ai-engine-billing, Property 2: Lead Score Range Invariant
@given(
    email_domain=st.text(min_size=1),
    company_size=st.integers(min_value=1, max_value=100000),
    website_visits=st.integers(min_value=0, max_value=1000),
    email_opens=st.integers(min_value=0, max_value=100),
    link_clicks=st.integers(min_value=0, max_value=100),
    form_submissions=st.integers(min_value=0, max_value=50),
    days_since_last_activity=st.integers(min_value=0, max_value=365),
    pipeline_stage=st.sampled_from(['new', 'qualified', 'proposal', 'negotiation'])
)
@pytest.mark.property_test
def test_lead_score_always_in_valid_range(
    email_domain, company_size, website_visits, email_opens,
    link_clicks, form_submissions, days_since_last_activity, pipeline_stage
):
    """Property: For any valid lead data, score must be in range [0, 100]"""
    lead_data = LeadData(
        email_domain=email_domain,
        company_size=company_size,
        website_visits=website_visits,
        email_opens=email_opens,
        link_clicks=link_clicks,
        form_submissions=form_submissions,
        days_since_last_activity=days_since_last_activity,
        pipeline_stage=pipeline_stage
    )
    
    scorer = LeadScorer()
    result = scorer.score(lead_data)
    
    assert 0 <= result.score <= 100, f"Score {result.score} out of valid range"
    assert 0.0 <= result.confidence <= 1.0, f"Confidence {result.confidence} out of valid range"
```

**Example Unit Test (Python):**
```python
def test_lead_scorer_high_engagement_scenario():
    """Unit test: Specific example of high-engagement lead"""
    lead_data = LeadData(
        email_domain="enterprise.com",
        company_size=500,
        industry="SaaS",
        website_visits=25,
        email_opens=15,
        link_clicks=10,
        form_submissions=3,
        days_since_last_activity=1,
        pipeline_stage="qualified"
    )
    
    scorer = LeadScorer()
    result = scorer.score(lead_data)
    
    assert result.score >= 80, "High engagement should yield high score"
    assert result.confidence >= 0.8, "Complete data should yield high confidence"
    assert any(f.name == "High engagement" for f in result.factors)
```

### Billing System Test Structure

```
modular_core/tests/
├── Unit/
│   ├── Billing/
│   │   ├── SubscriptionManagerTest.php
│   │   ├── WebhookHandlerTest.php
│   │   └── UsageMeteringTest.php
├── Properties/
│   ├── Billing/
│   │   ├── SubscriptionStateTransitionsTest.php
│   │   ├── WebhookIdempotencyTest.php
│   │   └── TenantLockoutTest.php
└── Integration/
    └── Billing/
        └── StripeIntegrationTest.php
```

**Example Property Test (PHP/Eris):**
```php
use Eris\Generator;
use Eris\TestTrait;

class SubscriptionStateTransitionsTest extends TestCase
{
    use TestTrait;
    
    /**
     * @property Feature: ai-engine-billing, Property 30: Payment Success State Transition
     * @test
     */
    public function payment_success_always_activates_subscription_and_clears_grace_period()
    {
        $this->forAll(
            Generator::associative([
                'tenant_id' => Generator::uuid(),
                'subscription_id' => Generator::string(),
                'grace_period_days' => Generator::choose(1, 7)
            ])
        )->then(function ($data) {
            // Setup: Create tenant in grace period
            $tenant = Tenant::factory()->create([
                'id' => $data['tenant_id'],
                'stripe_subscription_id' => $data['subscription_id'],
                'subscription_status' => 'past_due',
                'grace_period_start' => now(),
                'grace_period_end' => now()->addDays($data['grace_period_days'])
            ]);
            
            // Action: Process payment_succeeded webhook
            $event = $this->createStripeEvent('invoice.payment_succeeded', [
                'subscription' => $data['subscription_id']
            ]);
            
            $handler = new StripeWebhookHandler();
            $handler->handlePaymentSucceeded($event);
            
            // Assert: Subscription activated and grace period cleared
            $tenant->refresh();
            $this->assertEquals('active', $tenant->subscription_status);
            $this->assertNull($tenant->grace_period_start);
            $this->assertNull($tenant->grace_period_end);
        });
    }
}
```

### WebSocket Testing

**Backend Event Publishing Tests:**
```php
class MessageNotificationTest extends TestCase
{
    /**
     * @property Feature: ai-engine-billing, Property 43: Message Notification Event Publishing
     * @test
     */
    public function new_message_publishes_websocket_event()
    {
        Event::fake([MessageReceived::class]);
        
        $message = Message::factory()->create([
            'tenant_id' => 'test-tenant-id',
            'sender_name' => 'John Doe',
            'channel' => 'whatsapp'
        ]);
        
        // Trigger message processing
        dispatch(new ProcessInboundMessage($message));
        
        // Assert event was broadcast
        Event::assertDispatched(MessageReceived::class, function ($event) use ($message) {
            return $event->tenantId === $message->tenant_id
                && $event->messageId === $message->id
                && $event->senderName === $message->sender_name;
        });
    }
}
```

**Frontend Integration Tests (Jest + React Testing Library):**
```typescript
describe('WebSocket Message Notifications', () => {
  it('displays notification when message.received event is received', async () => {
    const { getByText } = render(<InboxPage />);
    
    // Simulate WebSocket event
    const mockEvent = {
      message_id: 'msg-123',
      sender_name: 'John Doe',
      sender_channel: 'whatsapp',
      preview_text: 'Hello, I need help with...',
      assigned_agent_id: 'agent-456'
    };
    
    act(() => {
      pusher.channel('tenant.test-tenant.messages').emit('message.received', mockEvent);
    });
    
    // Assert notification displayed
    await waitFor(() => {
      expect(getByText(/New message from John Doe/i)).toBeInTheDocument();
    });
  });
});
```

### Integration Testing

**End-to-End Subscription Flow:**
```php
class SubscriptionFlowTest extends TestCase
{
    /** @test */
    public function complete_subscription_flow_from_checkout_to_activation()
    {
        // 1. Create checkout session
        $response = $this->postJson('/api/v1/billing/checkout', [
            'tenant_id' => $this->tenant->id,
            'tier' => 'growth'
        ]);
        
        $response->assertStatus(200);
        $sessionId = $response->json('session_id');
        
        // 2. Simulate Stripe webhook: checkout.session.completed
        $this->postJson('/webhooks/stripe', [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test456',
                    'metadata' => [
                        'tenant_id' => $this->tenant->id,
                        'tier' => 'growth'
                    ]
                ]
            ]
        ], ['Stripe-Signature' => $this->generateValidSignature()]);
        
        // 3. Assert subscription created and tenant activated
        $this->tenant->refresh();
        $this->assertEquals('active', $this->tenant->subscription_status);
        $this->assertEquals('growth', $this->tenant->current_tier);
        $this->assertNull($this->tenant->access_locked_at);
        
        $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('sub_test456', $subscription->stripe_subscription_id);
    }
}
```

### Performance Testing

While not part of property-based testing, performance requirements should be validated:

**Load Testing (Locust):**
```python
from locust import HttpUser, task, between

class AIEngineUser(HttpUser):
    wait_time = between(1, 3)
    
    @task
    def score_lead(self):
        self.client.post("/api/v1/leads/score", json={
            "lead_id": "test-lead-123",
            "tenant_id": "test-tenant-456",
            "lead_data": {
                "email_domain": "test.com",
                "company_size": 100,
                "website_visits": 10,
                "email_opens": 5,
                "link_clicks": 2,
                "form_submissions": 1,
                "days_since_last_activity": 3,
                "pipeline_stage": "qualified"
            }
        }, headers={"Authorization": f"Bearer {self.token}"})
```

**Performance Benchmarks:**
- Lead scoring: < 3 seconds per request
- Intent detection: < 2 seconds per request
- Content generation: < 4 seconds per request
- Webhook processing: < 5 seconds per event
- WebSocket event delivery: < 3 seconds from trigger to client

---

## Implementation Phases

### Phase 1: AI Engine Foundation (Days 1-5)

**Day 1-2: Project Setup and Authentication**
- Initialize FastAPI project structure
- Implement JWT authentication middleware
- Set up structured logging
- Configure OpenAI and Anthropic clients
- Write authentication property tests

**Day 3-4: Lead Scoring Engine**
- Implement lead scoring algorithm
- Create scoring factor calculation
- Add confidence scoring
- Write lead scoring property tests (score range, response schema)
- Integration test with OpenAI embeddings

**Day 5: Intent Detection**
- Implement intent classification with Claude
- Create prompt templates
- Add confidence thresholds
- Write intent detection property tests (enum constraint, response schema)

### Phase 2: Content Generation and Actions (Days 6-8)

**Day 6: Content Generation**
- Implement reply generation with GPT-4
- Create tone presets
- Add conversation history context
- Write content generation property tests

**Day 7: Action Suggestions**
- Implement action suggestion logic
- Create action type classification
- Add priority and due date suggestions
- Write action suggestion property tests (count limit, response schema)

**Day 8: Rate Limiting and Error Handling**
- Implement per-tenant rate limiting
- Add retry logic with exponential backoff
- Implement circuit breaker
- Write rate limiting and retry property tests

### Phase 3: Billing System Core (Days 9-12)

**Day 9: Stripe Integration Setup**
- Configure Stripe SDK
- Create database migrations
- Implement SubscriptionManager service
- Set up Stripe webhook endpoint

**Day 10: Checkout and Subscription Creation**
- Implement checkout session creation
- Handle checkout.session.completed webhook
- Create subscription activation logic
- Write subscription creation property tests

**Day 11: Subscription Management**
- Implement tier upgrade/downgrade
- Add cancellation logic
- Implement user limit enforcement
- Write tier change property tests

**Day 12: Webhook Processing**
- Implement all webhook event handlers
- Add webhook signature validation
- Create idempotency handling
- Write webhook property tests (idempotency, state transitions)

### Phase 4: Grace Period and Lockout (Days 13-15)

**Day 13: Grace Period Logic**
- Implement payment failure handling
- Create grace period state machine
- Add email reminder scheduling
- Write grace period property tests

**Day 14: Tenant Lockout**
- Implement TenantLockoutMiddleware
- Add subscription status checks
- Create access restoration logic
- Write lockout property tests

**Day 15: Usage Metering**
- Implement usage tracking
- Create Stripe usage reporting
- Add overage calculation
- Write usage metering property tests

### Phase 5: WebSocket Infrastructure (Days 16-18)

**Day 16: Soketi Setup**
- Deploy Soketi container
- Configure SSL termination
- Set up Laravel Broadcasting
- Create channel authorization

**Day 17: Event Broadcasting**
- Implement MessageReceived event
- Create LeadUpdated event
- Add TypingIndicator events
- Write event publishing property tests

**Day 18: Frontend Integration**
- Set up Pusher client
- Implement message notifications
- Add typing indicators
- Create online status tracking

### Phase 6: Integration and Testing (Days 19-21)

**Day 19: End-to-End Integration**
- Connect AI Engine to PHP backend
- Integrate billing with AI usage tracking
- Connect WebSocket events to AI operations
- Run integration test suite

**Day 20: Property Test Suite Completion**
- Complete all 47 property tests
- Run full property test suite (100 iterations each)
- Fix any discovered edge cases
- Document test coverage

**Day 21: Performance Testing and Documentation**
- Run load tests on AI Engine
- Verify webhook processing performance
- Test WebSocket scalability
- Complete API documentation
- Create deployment guide

---

## Deployment Architecture

### Docker Compose Configuration

```yaml
version: '3.8'

services:
  ai-engine:
    build: ./ai_engine
    ports:
      - "8001:8001"
    environment:
      - OPENAI_API_KEY=${OPENAI_API_KEY}
      - ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY}
      - JWT_SECRET=${JWT_SECRET}
      - DATABASE_URL=${DATABASE_URL}
    depends_on:
      - redis
    networks:
      - nexsaas-network
    restart: unless-stopped
  
  soketi:
    image: quay.io/soketi/soketi:1.6-16-debian
    ports:
      - "6001:6001"
      - "6002:6002"
    environment:
      - SOKETI_DEFAULT_APP_ID=${PUSHER_APP_ID}
      - SOKETI_DEFAULT_APP_KEY=${PUSHER_APP_KEY}
      - SOKETI_DEFAULT_APP_SECRET=${PUSHER_APP_SECRET}
    networks:
      - nexsaas-network
    restart: unless-stopped
  
  nginx:
    image: nginx:alpine
    ports:
      - "443:443"
    volumes:
      - ./nginx/conf.d:/etc/nginx/conf.d
      - ./nginx/ssl:/etc/nginx/ssl
    depends_on:
      - ai-engine
      - soketi
    networks:
      - nexsaas-network
    restart: unless-stopped

networks:
  nexsaas-network:
    driver: bridge
```

### Environment Variables

**.env (AI Engine):**
```env
# AI Engine
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
JWT_SECRET=your-jwt-secret
JWT_AUDIENCE=ai-engine
JWT_ISSUER=nexsaas-platform

# Database
DATABASE_URL=postgresql://user:pass@localhost:5432/nexsaas

# Redis
REDIS_URL=redis://localhost:6379/0

# Logging
LOG_LEVEL=INFO
LOG_FORMAT=json
```

**.env (PHP Backend):**
```env
# Stripe
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_STARTER=price_...
STRIPE_PRICE_GROWTH=price_...
STRIPE_PRICE_ENTERPRISE_BASE=price_...
STRIPE_PRICE_ENTERPRISE_USAGE=price_...

# WebSocket
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=nexsaas-app
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=https

# AI Engine
AI_ENGINE_URL=http://ai-engine:8001
AI_ENGINE_JWT_SECRET=your-jwt-secret
```

### Monitoring and Observability

**Metrics to Track:**
- AI Engine: Request rate, latency (p50, p95, p99), error rate, token usage
- Billing: Subscription creation rate, webhook processing time, failed payments
- WebSocket: Connected clients, message throughput, connection errors

**Logging:**
- All services log to stdout in JSON format
- Centralized logging with ELK stack or CloudWatch
- Log retention: 30 days for info, 90 days for errors

**Alerts:**
- AI Engine error rate > 5%
- Webhook processing time > 10 seconds
- WebSocket connection failures > 10%
- Stripe API errors
- Grace period expirations without payment

---

## Security Considerations

### AI Engine Security

1. **JWT Validation**: All requests must include valid JWT with matching tenant_id
2. **Rate Limiting**: Per-tenant limits prevent abuse
3. **Input Sanitization**: All user input sanitized before sending to AI APIs
4. **API Key Rotation**: OpenAI and Anthropic keys rotated quarterly
5. **Prompt Injection Protection**: User input validated to prevent prompt injection attacks

### Billing Security

1. **Webhook Signature Verification**: All Stripe webhooks verified before processing
2. **Idempotency**: Duplicate webhook events handled safely
3. **PCI Compliance**: No card data stored (handled by Stripe)
4. **Audit Logging**: All billing operations logged with user_id and timestamp
5. **Access Control**: Only Owners and Admins can modify billing

### WebSocket Security

1. **Channel Authorization**: All private/presence channels require authentication
2. **SSL/TLS**: All WebSocket connections encrypted
3. **Origin Validation**: CORS configured to allow only platform domain
4. **Rate Limiting**: Connection and message rate limits prevent abuse
5. **Token Expiration**: WebSocket auth tokens expire after 24 hours

---

## Conclusion

This design provides a comprehensive architecture for the AI Engine, Billing System, and Real-time WebSocket infrastructure. The three systems work together to deliver intelligent lead scoring, seamless subscription management, and real-time user experience while maintaining clean separation of concerns and independent scalability.

Key design decisions:
- **Microservice architecture** for AI Engine enables independent scaling and technology choice
- **Webhook-driven state machine** for billing ensures consistency with Stripe
- **Self-hosted Soketi** eliminates recurring Pusher costs while maintaining compatibility
- **Property-based testing** provides comprehensive correctness guarantees
- **Dual testing approach** balances specific examples with universal properties

The implementation phases provide a clear 21-day roadmap from foundation to production-ready deployment.
