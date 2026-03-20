from pydantic import BaseModel, Field, EmailStr
from typing import List, Optional, Dict
from enum import Enum

# --- Enums ---
class IntentCategory(str, Enum):
    BUYING_INTENT = "buying_intent"
    CHURN_RISK = "churn_risk"
    SUPPORT_REQUEST = "support_request"
    NEUTRAL = "neutral"

class TonePreset(str, Enum):
    PROFESSIONAL = "professional"
    FRIENDLY = "friendly"
    CONCISE = "concise"

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

# --- Common Items ---
class ScoringFactor(BaseModel):
    name: str
    weight: float = Field(..., ge=0, le=1)
    impact: str # e.g., "positive", "negative"
    description: str

class SuggestedAction(BaseModel):
    action_type: ActionType
    description: str
    priority: ActionPriority
    due_date_suggestion: Optional[str] = None # ISO format

# --- Lead Scoring Models ---
class LeadData(BaseModel):
    id: str
    email: Optional[str] = None
    domain: Optional[str] = None
    company_size: Optional[int] = None
    industry: Optional[str] = None
    website_visits: int = 0
    email_clicks: int = 0
    form_submissions: int = 0
    days_since_last_activity: int = 0
    current_stage: str = "new"

class LeadScoreRequest(BaseModel):
    lead: LeadData

class LeadScoreResponse(BaseModel):
    lead_id: str
    score: int = Field(..., ge=0, le=100)
    confidence: float = Field(..., ge=0, le=1)
    factors: List[ScoringFactor]
    model_version: str = "lead-scorer-v1"

# --- Intent Detection Models ---
class IntentDetectionRequest(BaseModel):
    message_text: str
    conversation_history: Optional[List[Dict[str, str]]] = []
    sender_context: Optional[Dict[str, Any]] = {}

class IntentDetectionResponse(BaseModel):
    intent: IntentCategory
    confidence: float = Field(..., ge=0, le=1)
    reasoning: str
    model_version: str = "intent-detector-v1"

class Any:
    pass

# --- AI Content Models ---
class ContentGenerationRequest(BaseModel):
    message_text: str
    tone: TonePreset = TonePreset.PROFESSIONAL
    conversation_history: List[Dict[str, str]] = []

class ContentGenerationResponse(BaseModel):
    draft_text: str
    confidence: float = Field(..., ge=0, le=1)
    model_version: str = "gpt-4-gen-v1"
