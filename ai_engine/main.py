"""
AI Revenue Operating System — Python AI Microservice
FastAPI application for Lead Scoring, Intent Detection, and AI Content Generation.
All endpoints are tenant-aware.
"""

from fastapi import FastAPI, HTTPException, Header
from pydantic import BaseModel, Field
from typing import List, Optional
import datetime
import hashlib
import json

app = FastAPI(
    title="AI Revenue OS — Intelligence Engine",
    version="2.0.0",
    description="Enterprise AI Microservice: Lead Scoring, Intent Detection, Content Generation, Revenue Forecasting."
)

# ─── Data Models ─────────────────────────────────────────────

class LeadScoringRequest(BaseModel):
    tenant_id: int
    lead_id: int
    first_name: Optional[str] = None
    company: Optional[str] = None
    source: Optional[str] = None
    email_engagement: int = Field(0, description="Total emails opened/clicked")
    days_in_stage: int = Field(0, description="Days in current pipeline stage")
    total_messages: int = Field(0, description="Total omnichannel messages")
    company_size: int = Field(0, description="Employee count if known")

class IntentDetectionRequest(BaseModel):
    tenant_id: int
    message_content: str
    channel: str = "whatsapp"
    contact_id: Optional[int] = None

class ContentGenerationRequest(BaseModel):
    tenant_id: int
    content_type: str = Field("email", description="email|sms|whatsapp|ad_copy")
    prospect_name: str
    company: str = ""
    context: str = ""
    tone: str = Field("professional", description="professional|casual|urgent")
    pain_points: List[str] = []

class BulkScoringRequest(BaseModel):
    tenant_id: int
    leads: List[LeadScoringRequest]

class RevenueForecastRequest(BaseModel):
    tenant_id: int
    historical_data: Optional[List[dict]] = None

# ─── Health Check ────────────────────────────────────────────

@app.get("/health")
def health():
    return {"status": "ok", "service": "AI Revenue OS Intelligence Engine", "version": "2.0.0"}

# ─── 1. Lead Scoring ────────────────────────────────────────

@app.post("/ai/score-lead")
def score_lead(req: LeadScoringRequest):
    """
    Predictive Lead Scoring using a weighted feature model.
    In production: Replace with XGBoost/LightGBM model loaded from S3.
    """
    score = 50  # Base score

    # Engagement signals
    if req.email_engagement >= 5: score += 20
    elif req.email_engagement >= 2: score += 10

    # Company size signal
    if req.company_size > 200: score += 15
    elif req.company_size > 50: score += 8

    # Recency / urgency
    if req.days_in_stage > 30: score -= 15
    elif req.days_in_stage > 14: score -= 5

    # Omnichannel activity
    if req.total_messages > 10: score += 10
    elif req.total_messages > 3: score += 5

    # Source quality bonus
    high_quality_sources = ["referral", "organic", "linkedin"]
    if req.source and req.source.lower() in high_quality_sources:
        score += 10

    final_score = max(0, min(100, score))

    if final_score >= 80:
        priority = "hot"
        action = "Immediate phone call recommended"
    elif final_score >= 60:
        priority = "warm"
        action = "Schedule a demo within 48 hours"
    elif final_score >= 40:
        priority = "nurture"
        action = "Add to email drip campaign"
    else:
        priority = "cold"
        action = "Monitor for re-engagement signals"

    return {
        "success": True,
        "tenant_id": req.tenant_id,
        "lead_id": req.lead_id,
        "score": final_score,
        "priority": priority,
        "recommended_action": action
    }

# ─── 2. Bulk Scoring (Async-ready) ──────────────────────────

@app.post("/ai/score-leads-bulk")
def score_leads_bulk(req: BulkScoringRequest):
    """Score multiple leads in a single request. Used by background workers."""
    results = []
    for lead in req.leads:
        result = score_lead(lead)
        results.append(result)
    return {"success": True, "tenant_id": req.tenant_id, "results": results, "total": len(results)}

# ─── 3. Intent Detection ────────────────────────────────────

@app.post("/ai/detect-intent")
def detect_intent(req: IntentDetectionRequest):
    """
    NLP Intent Classification for incoming messages.
    In production: Use HuggingFace Transformers or fine-tuned BERT model.
    """
    content = req.message_content.lower()

    intent_rules = [
        (["price", "cost", "pricing", "how much", "quote", "rates"], "pricing_inquiry"),
        (["buy", "purchase", "order", "subscribe", "upgrade", "sign up"], "purchase_intent"),
        (["problem", "issue", "bug", "broken", "not working", "error", "help"], "support_request"),
        (["cancel", "refund", "unsubscribe", "stop"], "churn_risk"),
        (["demo", "trial", "test", "try"], "demo_request"),
        (["thank", "great", "awesome", "perfect", "love"], "positive_feedback"),
        (["when", "schedule", "meeting", "call", "available"], "scheduling"),
    ]

    detected_intent = "general_inquiry"
    confidence = 0.65

    for keywords, intent in intent_rules:
        matches = sum(1 for kw in keywords if kw in content)
        if matches > 0:
            detected_intent = intent
            confidence = min(0.95, 0.7 + (matches * 0.08))
            break

    # Suggested action based on intent
    action_map = {
        "pricing_inquiry": "Route to Sales Agent with pricing deck",
        "purchase_intent": "🔥 HIGH PRIORITY: Route to closer immediately",
        "support_request": "Create support ticket and assign to Support Agent",
        "churn_risk": "⚠️ Escalate to Account Manager",
        "demo_request": "Send calendar booking link",
        "positive_feedback": "Thank them and ask for a review/referral",
        "scheduling": "Share available time slots",
        "general_inquiry": "Respond with general information"
    }

    return {
        "success": True,
        "tenant_id": req.tenant_id,
        "intent": detected_intent,
        "confidence": round(confidence, 2),
        "suggested_action": action_map.get(detected_intent, "Review manually"),
        "channel": req.channel
    }

# ─── 4. AI Content Generation ───────────────────────────────

@app.post("/ai/generate-content")
def generate_content(req: ContentGenerationRequest):
    """
    AI-powered content generation for sales outreach.
    In production: Connect to OpenAI/Anthropic API.
    """
    templates = {
        "email": {
            "subject_variants": [
                f"Quick idea for {req.company}'s growth",
                f"{req.prospect_name}, let's solve {req.pain_points[0] if req.pain_points else 'your biggest challenge'}",
                f"Following up: AI Revenue OS × {req.company}"
            ],
            "body": f"""Hi {req.prospect_name},

I noticed that {req.company} is scaling rapidly, and I wanted to share how our AI Revenue Operating System has helped similar companies increase their conversion rates by 40%.

{f"Specifically regarding {req.pain_points[0]}, " if req.pain_points else ""}Our platform combines intelligent lead scoring with omnichannel engagement to ensure no opportunity falls through the cracks.

Would you be open to a quick 15-minute demo this week?

Best regards,
Your AI Revenue OS Sales Team"""
        },
        "whatsapp": {
            "variants": [
                f"Hey {req.prospect_name}! 👋 Just wanted to follow up on our conversation about {req.context or 'improving your sales pipeline'}. Got 5 minutes for a quick chat?",
                f"Hi {req.prospect_name}, hope you're doing great! I prepared something specifically for {req.company} — mind if I share it? 🚀"
            ]
        },
        "sms": {
            "variants": [
                f"Hi {req.prospect_name}, your demo request for AI Revenue OS is confirmed! Check your email for details. Reply STOP to opt out.",
                f"{req.prospect_name}, your lead score just increased. Let's connect today? Reply YES for a callback."
            ]
        },
        "ad_copy": {
            "headlines": [
                "Stop Losing Leads. Start Closing Deals.",
                f"AI-Powered CRM That Actually Sells",
                "Your Sales Team's Secret Weapon"
            ],
            "body": "The AI Revenue Operating System scores leads, detects intent, and generates personalized outreach — all automatically. Start your free trial today."
        }
    }

    content = templates.get(req.content_type, templates["email"])

    return {
        "success": True,
        "tenant_id": req.tenant_id,
        "content_type": req.content_type,
        "tone": req.tone,
        "generated": content
    }

# ─── 5. Revenue Forecasting ─────────────────────────────────

@app.post("/ai/forecast-revenue")
def forecast_revenue(req: RevenueForecastRequest):
    """
    Revenue forecasting using time-series analysis.
    In production: Use Facebook Prophet or custom LSTM model.
    """
    return {
        "success": True,
        "tenant_id": req.tenant_id,
        "forecast": {
            "30_days": {"amount": 47500.00, "confidence": 0.82},
            "60_days": {"amount": 112000.00, "confidence": 0.74},
            "90_days": {"amount": 218000.00, "confidence": 0.68}
        },
        "trend": "upward",
        "growth_rate": 14.2,
        "ai_insights": [
            "Pipeline velocity has increased 18% this month",
            "Proposal-to-Close conversion improved by 12%",
            "Top performing channel: LinkedIn Outreach (32% conversion)"
        ]
    }

# ─── 6. Suggest Reply (Omnichannel Assistant) ───────────────

@app.post("/ai/suggest-reply")
def suggest_reply(req: IntentDetectionRequest):
    """
    Generates contextual reply suggestions for agents in the Unified Inbox.
    """
    intent_result = detect_intent(req)
    intent = intent_result["intent"]

    replies = {
        "pricing_inquiry": [
            "I'd be happy to share our pricing! We have plans starting at $29/month. Would you like a detailed breakdown?",
            "Great question! Our pricing depends on your team size. Can I schedule a quick call to find the perfect plan?"
        ],
        "purchase_intent": [
            "Excellent! I can set up your account right now. Shall I send you a secure checkout link?",
            "Great to hear you're ready! I'll prepare a customized onboarding plan. What's the best email to send it to?"
        ],
        "support_request": [
            "I'm sorry you're experiencing that. Let me look into it right away. Can you share a screenshot?",
            "I understand the frustration. I've created a priority support ticket for you — our team will respond within 1 hour."
        ],
        "churn_risk": [
            "I'm sorry to hear that. Before you go, would you mind sharing what we could have done better? We'd love to make it right.",
            "I understand. Would a call with our success team help? We can often resolve concerns quickly."
        ],
        "demo_request": [
            "Absolutely! Here's my calendar link to book a time that works: [calendar_link]. Looking forward to it!",
            "I'd love to show you the platform. Are you available this Thursday at 2 PM?"
        ],
        "general_inquiry": [
            "Thanks for reaching out! How can I help you today?",
            "Hi there! I'm here to help. What would you like to know about AI Revenue OS?"
        ]
    }

    return {
        "success": True,
        "tenant_id": req.tenant_id,
        "detected_intent": intent,
        "suggested_replies": replies.get(intent, replies["general_inquiry"]),
        "auto_tag": intent.replace("_", " ").title()
    }
