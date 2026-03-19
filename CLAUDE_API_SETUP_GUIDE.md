# 🚀 Claude API Setup Guide

## Quick Start (5 Minutes)

### Step 1: Get Your API Key

1. Go to https://console.anthropic.com/
2. Sign up or log in
3. Navigate to API Keys
4. Create a new API key
5. Copy the key (starts with `sk-ant-api03-...`)

### Step 2: Configure Environment

Add to your `.env` file:

```bash
ANTHROPIC_API_KEY=sk-ant-api03-your-actual-key-here
```

### Step 3: Install Dependencies

```bash
cd ai_engine
pip install anthropic>=0.18.0
```

Or rebuild Docker containers:

```bash
docker compose down
docker compose build
docker compose up -d
```

### Step 4: Verify It Works

```bash
# Check health
curl http://localhost:8000/ai/claude/health

# Expected response:
{
  "success": true,
  "status": "healthy",
  "model": "claude-sonnet-4-20250514",
  "services": [
    "lead_scoring",
    "intent_detection",
    "email_drafting",
    "deal_forecasting",
    "conversation_summarization"
  ]
}
```

---

## 🎯 Available Services

### 1. Lead Scoring

Score leads from 0-100 based on fit and engagement.

**Endpoint:** `POST /ai/claude/lead/score`

**Example:**
```bash
curl -X POST http://localhost:8000/ai/claude/lead/score \
  -H "Content-Type: application/json" \
  -d '{
    "company_name": "Acme Corp",
    "industry": "Technology",
    "company_size": 500,
    "revenue": 50000000,
    "job_title": "VP of Sales",
    "seniority": "VP",
    "department": "Sales",
    "email_opens": 15,
    "website_visits": 25,
    "content_downloads": 5,
    "pricing_views": 8,
    "demo_requests": 2
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "score": 92,
    "category": "hot",
    "reasoning": "Strong company fit with high engagement signals. VP-level contact with multiple pricing page views and demo requests indicates high purchase intent.",
    "next_action": "Schedule demo call within 24 hours",
    "priority": "high"
  }
}
```

---

### 2. Intent Detection

Detect customer intent from messages.

**Endpoint:** `POST /ai/claude/intent/detect`

**Example:**
```bash
curl -X POST http://localhost:8000/ai/claude/intent/detect \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Hi, I am interested in your Enterprise plan. Can we schedule a demo to see how it works for our team of 50 people?",
    "customer_stage": "prospect",
    "interaction_count": 2,
    "account_status": "trial"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "primary_intent": "purchase_intent",
    "confidence": 0.95,
    "secondary_intents": ["information_seeking"],
    "sentiment": "positive",
    "urgency": "high",
    "suggested_response": "Respond with available demo times and Enterprise plan details"
  }
}
```

---

### 3. Email Drafting

Generate 3 email variants with different tones.

**Endpoint:** `POST /ai/claude/email/draft`

**Example:**
```bash
curl -X POST http://localhost:8000/ai/claude/email/draft \
  -H "Content-Type: application/json" \
  -d '{
    "recipient_name": "Sarah Johnson",
    "company_name": "TechStart Inc",
    "scenario": "follow_up",
    "goal": "Schedule a product demo",
    "key_points": [
      "Discussed pricing last week",
      "Interested in Enterprise features",
      "Team of 30 users"
    ],
    "previous_context": "Had initial call on March 12, discussed their need for better CRM"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "variant_1": {
      "subject": "Following up: TechStart Inc Demo",
      "body": "Hi Sarah,\n\nI wanted to follow up on our conversation last week about NexSaaS Enterprise...",
      "tone": "professional",
      "length": "short"
    },
    "variant_2": {
      "subject": "Quick follow-up for TechStart",
      "body": "Hey Sarah,\n\nHope you're doing well! I wanted to circle back...",
      "tone": "friendly",
      "length": "medium"
    },
    "variant_3": {
      "subject": "Demo for TechStart?",
      "body": "Sarah - following up on our chat. Still interested in seeing the Enterprise features?",
      "tone": "casual",
      "length": "brief"
    }
  }
}
```

---

### 4. Deal Forecasting

Predict deal close probability and timeline.

**Endpoint:** `POST /ai/claude/deal/forecast`

**Example:**
```bash
curl -X POST http://localhost:8000/ai/claude/deal/forecast \
  -H "Content-Type: application/json" \
  -d '{
    "deal_value": 120000,
    "stage": "Proposal Sent",
    "days_in_stage": 14,
    "created_date": "2026-02-01",
    "expected_close": "2026-04-15",
    "company_name": "Acme Corp",
    "industry": "SaaS",
    "company_size": 500,
    "last_contact": "2026-03-15",
    "meetings_count": 5,
    "emails_count": 23,
    "proposal_sent": true,
    "decision_makers": 3,
    "avg_cycle_days": 60,
    "similar_win_rate": 65
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "close_probability": 78,
    "predicted_close_date": "2026-04-10",
    "confidence": 0.82,
    "risk_factors": [
      "14 days in proposal stage without response",
      "Expected close date approaching"
    ],
    "positive_signals": [
      "Multiple meetings with decision makers",
      "High email engagement",
      "Proposal sent and acknowledged"
    ],
    "recommended_actions": [
      "Schedule follow-up call to address questions",
      "Send case study from similar company",
      "Offer limited-time discount to accelerate decision"
    ],
    "forecast_category": "best_case"
  }
}
```

---

### 5. Conversation Summarization

Summarize conversations with action items.

**Endpoint:** `POST /ai/claude/conversation/summarize`

**Example:**
```bash
curl -X POST http://localhost:8000/ai/claude/conversation/summarize \
  -H "Content-Type: application/json" \
  -d '{
    "conversation_text": "John: Thanks for joining the call today. We wanted to discuss the implementation timeline.\nSarah: Of course. We are looking at a Q2 start.\nJohn: That works for us. What about pricing?\nSarah: We can go with the Enterprise plan at $5k/month.\nJohn: Perfect. I will send the contract tomorrow.\nSarah: Great, we will review with legal and get back to you by Friday.",
    "participants": ["John Smith", "Sarah Johnson"],
    "conversation_type": "meeting",
    "duration": "30 minutes",
    "date": "2026-03-19"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "summary": "Sales call to finalize implementation timeline and pricing. Agreed on Q2 start with Enterprise plan at $5k/month. Contract to be sent and reviewed.",
    "key_points": [
      "Q2 implementation start date confirmed",
      "Enterprise plan pricing agreed at $5k/month",
      "Contract process initiated"
    ],
    "decisions": [
      "Go with Enterprise plan",
      "Start implementation in Q2",
      "Pricing set at $5k/month"
    ],
    "action_items": [
      {
        "task": "Send contract for signature",
        "owner": "John Smith",
        "due_date": "2026-03-20"
      },
      {
        "task": "Review contract with legal team",
        "owner": "Sarah Johnson",
        "due_date": "2026-03-22"
      }
    ],
    "next_steps": [
      "Legal review of contract",
      "Contract signature",
      "Kickoff call scheduling"
    ],
    "sentiment": "positive",
    "topics": ["implementation", "pricing", "contract", "timeline"]
  }
}
```

---

## 🔧 Advanced Usage

### Batch Processing

Score multiple leads at once:

```bash
curl -X POST http://localhost:8000/ai/claude/lead/score/batch \
  -H "Content-Type: application/json" \
  -d '[
    {
      "id": 1,
      "company_name": "Acme Corp",
      "industry": "SaaS",
      "company_size": 500,
      "email_opens": 15
    },
    {
      "id": 2,
      "company_name": "TechStart",
      "industry": "FinTech",
      "company_size": 100,
      "email_opens": 5
    }
  ]'
```

### Pipeline Forecasting

Get overall pipeline forecast:

```bash
curl -X POST http://localhost:8000/ai/claude/deal/forecast/pipeline \
  -H "Content-Type: application/json" \
  -d '[
    { "id": 1, "value": 50000, "stage": "Proposal", ... },
    { "id": 2, "value": 120000, "stage": "Negotiation", ... }
  ]'
```

### Intent-Based Routing

Detect intent and get routing recommendation:

```bash
curl -X POST http://localhost:8000/ai/claude/intent/route \
  -H "Content-Type: application/json" \
  -d '{
    "message": "I need help with my account!",
    "customer_stage": "customer",
    "account_status": "active"
  }'
```

---

## 💰 Cost Estimation

### Claude API Pricing (as of March 2026):
- Input: $3 per million tokens
- Output: $15 per million tokens

### Typical Usage:
- Lead scoring: ~500 tokens per request (~$0.01)
- Intent detection: ~300 tokens per request (~$0.006)
- Email drafting: ~800 tokens per request (~$0.015)
- Deal forecasting: ~600 tokens per request (~$0.012)
- Conversation summary: ~1000 tokens per request (~$0.02)

### Monthly Cost Estimates:
- 1,000 leads scored: ~$10
- 5,000 intents detected: ~$30
- 500 emails drafted: ~$7.50
- 200 deals forecasted: ~$2.40
- 100 conversations summarized: ~$2

**Total for typical usage: ~$50-100/month**

---

## 🐛 Troubleshooting

### Error: "ANTHROPIC_API_KEY environment variable is required"

**Solution:** Add API key to `.env` file and restart services.

```bash
echo "ANTHROPIC_API_KEY=sk-ant-api03-your-key" >> .env
docker compose restart fastapi
```

### Error: "Claude API error: 401 Unauthorized"

**Solution:** Check that your API key is valid and active.

### Error: "Claude did not return valid JSON"

**Solution:** This is usually a prompt issue. Check the logs for the raw response and adjust the prompt if needed.

### Service returns fallback responses

**Solution:** Check that the API key has sufficient credits and the service is not rate-limited.

---

## 📚 Additional Resources

- [Anthropic Documentation](https://docs.anthropic.com/)
- [Claude API Reference](https://docs.anthropic.com/claude/reference)
- [Prompt Engineering Guide](https://docs.anthropic.com/claude/docs/prompt-engineering)
- [Best Practices](https://docs.anthropic.com/claude/docs/best-practices)

---

## ✅ Checklist

- [ ] API key obtained from Anthropic Console
- [ ] API key added to `.env` file
- [ ] Dependencies installed (`anthropic>=0.18.0`)
- [ ] Services restarted
- [ ] Health check passes
- [ ] Test requests successful
- [ ] Monitoring set up for usage/costs

---

*Setup guide created March 19, 2026*  
*For support, check logs at `docker compose logs fastapi`*
