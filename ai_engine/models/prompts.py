"""
Claude AI Prompt Templates
All prompts for Claude API interactions
Requirements: Master Spec - AI Engine Prompts
"""

# ============================================================================
# LEAD SCORING PROMPTS
# ============================================================================

LEAD_SCORING_SYSTEM = """You are an expert B2B sales analyst specializing in lead qualification.
Your task is to analyze lead data and provide an accurate score from 0-100 based on:
- Company fit (industry, size, revenue)
- Engagement level (email opens, website visits, content downloads)
- Intent signals (pricing page views, demo requests, competitor research)
- Demographic fit (job title, seniority, department)

Always respond with valid JSON only."""

LEAD_SCORING_PROMPT = """Analyze this lead and provide a score from 0-100:

Lead Data:
- Company: {company_name}
- Industry: {industry}
- Company Size: {company_size} employees
- Annual Revenue: ${revenue}
- Job Title: {job_title}
- Seniority: {seniority}
- Department: {department}

Engagement Data:
- Email Opens: {email_opens}
- Website Visits: {website_visits}
- Content Downloads: {content_downloads}
- Pricing Page Views: {pricing_views}
- Demo Requests: {demo_requests}

Respond with JSON:
{{
  "score": <0-100>,
  "category": "<hot|warm|cold>",
  "reasoning": "<brief explanation>",
  "next_action": "<recommended next step>",
  "priority": "<high|medium|low>"
}}"""

# ============================================================================
# INTENT DETECTION PROMPTS
# ============================================================================

INTENT_DETECTION_SYSTEM = """You are an expert at analyzing customer messages to detect intent.
Classify messages into these categories:
- purchase_intent: Ready to buy, asking about pricing/contracts
- support_request: Needs help with existing product
- information_seeking: Researching, asking questions
- complaint: Expressing dissatisfaction
- feature_request: Suggesting new features
- churn_risk: Considering leaving/canceling

Always respond with valid JSON only."""

INTENT_DETECTION_PROMPT = """Analyze this customer message and detect the intent:

Message:
"{message}"

Context:
- Customer Stage: {customer_stage}
- Previous Interactions: {interaction_count}
- Account Status: {account_status}

Respond with JSON:
{{
  "primary_intent": "<intent_category>",
  "confidence": <0-1>,
  "secondary_intents": ["<intent>", ...],
  "sentiment": "<positive|neutral|negative>",
  "urgency": "<high|medium|low>",
  "suggested_response": "<brief suggestion>"
}}"""

# ============================================================================
# EMAIL DRAFTER PROMPTS
# ============================================================================

EMAIL_DRAFTER_SYSTEM = """You are an expert sales and customer success email writer.
Write professional, personalized emails that:
- Match the recipient's communication style
- Are concise and action-oriented
- Include clear CTAs
- Sound human, not robotic
- Adapt tone based on context

Always respond with valid JSON containing 3 email variants."""

EMAIL_DRAFTER_PROMPT = """Write 3 email variants for this scenario:

Context:
- Recipient: {recipient_name}
- Company: {company_name}
- Scenario: {scenario}
- Goal: {goal}
- Key Points: {key_points}

Previous Context:
{previous_context}

Respond with JSON:
{{
  "variant_1": {{
    "subject": "<subject line>",
    "body": "<email body>",
    "tone": "professional",
    "length": "short"
  }},
  "variant_2": {{
    "subject": "<subject line>",
    "body": "<email body>",
    "tone": "friendly",
    "length": "medium"
  }},
  "variant_3": {{
    "subject": "<subject line>",
    "body": "<email body>",
    "tone": "casual",
    "length": "brief"
  }}
}}"""

# ============================================================================
# DEAL FORECASTING PROMPTS
# ============================================================================

DEAL_FORECASTING_SYSTEM = """You are an expert sales forecaster with deep knowledge of B2B sales cycles.
Analyze deal data to predict:
- Close probability (0-100%)
- Expected close date
- Risk factors
- Recommended actions

Base predictions on historical patterns, engagement signals, and deal characteristics."""

DEAL_FORECASTING_PROMPT = """Forecast this deal:

Deal Information:
- Deal Value: ${deal_value}
- Stage: {stage}
- Days in Stage: {days_in_stage}
- Created Date: {created_date}
- Expected Close: {expected_close}

Company:
- Name: {company_name}
- Industry: {industry}
- Size: {company_size} employees

Engagement:
- Last Contact: {last_contact}
- Meetings Held: {meetings_count}
- Emails Exchanged: {emails_count}
- Proposal Sent: {proposal_sent}
- Decision Makers Engaged: {decision_makers}

Historical Context:
- Average Deal Cycle: {avg_cycle_days} days
- Win Rate for Similar Deals: {similar_win_rate}%

Respond with JSON:
{{
  "close_probability": <0-100>,
  "predicted_close_date": "<YYYY-MM-DD>",
  "confidence": <0-1>,
  "risk_factors": ["<factor>", ...],
  "positive_signals": ["<signal>", ...],
  "recommended_actions": ["<action>", ...],
  "forecast_category": "<commit|best_case|pipeline|omitted>"
}}"""

# ============================================================================
# CONVERSATION SUMMARIZER PROMPTS
# ============================================================================

SUMMARIZER_SYSTEM = """You are an expert at summarizing customer conversations.
Create concise, actionable summaries that capture:
- Key discussion points
- Decisions made
- Action items with owners
- Next steps
- Important context for future reference

Keep summaries brief but comprehensive."""

SUMMARIZER_PROMPT = """Summarize this conversation:

Conversation:
{conversation_text}

Participants:
{participants}

Context:
- Type: {conversation_type}
- Duration: {duration}
- Date: {date}

Respond with JSON:
{{
  "summary": "<2-3 sentence overview>",
  "key_points": ["<point>", ...],
  "decisions": ["<decision>", ...],
  "action_items": [
    {{
      "task": "<task description>",
      "owner": "<person>",
      "due_date": "<date or null>"
    }}
  ],
  "next_steps": ["<step>", ...],
  "sentiment": "<positive|neutral|negative>",
  "topics": ["<topic>", ...]
}}"""

# ============================================================================
# SENTIMENT ANALYSIS PROMPTS
# ============================================================================

SENTIMENT_ANALYSIS_SYSTEM = """You are an expert at analyzing customer sentiment.
Detect emotional tone, satisfaction level, and potential issues."""

SENTIMENT_ANALYSIS_PROMPT = """Analyze the sentiment of this message:

Message:
"{message}"

Context:
- Customer: {customer_name}
- Account Status: {account_status}
- Previous Sentiment: {previous_sentiment}

Respond with JSON:
{{
  "sentiment": "<positive|neutral|negative>",
  "confidence": <0-1>,
  "emotions": ["<emotion>", ...],
  "satisfaction_score": <0-10>,
  "churn_risk": <0-1>,
  "key_phrases": ["<phrase>", ...]
}}"""

# ============================================================================
# ACCOUNT HEALTH SCORING PROMPTS
# ============================================================================

ACCOUNT_HEALTH_SYSTEM = """You are an expert customer success analyst.
Evaluate account health based on usage, engagement, and satisfaction signals."""

ACCOUNT_HEALTH_PROMPT = """Evaluate this account's health:

Account:
- Name: {account_name}
- MRR: ${mrr}
- Contract End: {contract_end}
- Tenure: {tenure_months} months

Usage Metrics:
- Daily Active Users: {dau}
- Weekly Active Users: {wau}
- Feature Adoption: {feature_adoption}%
- Login Frequency: {login_frequency}

Engagement:
- Last Contact: {last_contact}
- Support Tickets (30d): {support_tickets}
- NPS Score: {nps_score}
- Product Feedback: {feedback_count}

Respond with JSON:
{{
  "health_score": <0-100>,
  "status": "<healthy|at_risk|critical>",
  "churn_probability": <0-1>,
  "risk_factors": ["<factor>", ...],
  "positive_indicators": ["<indicator>", ...],
  "recommended_actions": ["<action>", ...],
  "next_review_date": "<YYYY-MM-DD>"
}}"""

# ============================================================================
# HELPER FUNCTIONS
# ============================================================================

def format_prompt(template: str, **kwargs) -> str:
    """
    Format a prompt template with provided kwargs
    Handles missing keys gracefully
    """
    # Replace None values with "N/A"
    formatted_kwargs = {k: (v if v is not None else "N/A") for k, v in kwargs.items()}
    
    try:
        return template.format(**formatted_kwargs)
    except KeyError as e:
        raise ValueError(f"Missing required prompt parameter: {e}")


def validate_prompt_params(required_params: list, provided_params: dict) -> bool:
    """
    Validate that all required parameters are provided
    """
    missing = [p for p in required_params if p not in provided_params]
    if missing:
        raise ValueError(f"Missing required parameters: {missing}")
    return True
