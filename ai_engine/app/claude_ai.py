"""
FastAPI endpoints for Claude AI services
Requirements: Master Spec - AI Engine API
"""

from fastapi import APIRouter, HTTPException, Body
from pydantic import BaseModel, Field
from typing import List, Optional, Dict, Any
import logging

from ..services.lead_scorer_claude import get_lead_scorer
from ..services.intent_detector_claude import get_intent_detector
from ..services.email_drafter_claude import get_email_drafter
from ..services.deal_forecaster_claude import get_deal_forecaster
from ..services.summarizer_claude import get_summarizer

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/ai/claude", tags=["Claude AI"])


# ============================================================================
# REQUEST MODELS
# ============================================================================

class LeadScoreRequest(BaseModel):
    company_name: str
    industry: str
    company_size: int
    revenue: Optional[float] = None
    job_title: Optional[str] = None
    seniority: Optional[str] = None
    department: Optional[str] = None
    email_opens: int = 0
    website_visits: int = 0
    content_downloads: int = 0
    pricing_views: int = 0
    demo_requests: int = 0


class IntentDetectionRequest(BaseModel):
    message: str
    customer_stage: str = "prospect"
    interaction_count: int = 0
    account_status: str = "active"


class EmailDraftRequest(BaseModel):
    recipient_name: str
    company_name: str
    scenario: str
    goal: str
    key_points: List[str]
    previous_context: Optional[str] = None


class DealForecastRequest(BaseModel):
    deal_value: float
    stage: str
    days_in_stage: int
    created_date: str
    expected_close: str
    company_name: str
    industry: str
    company_size: int
    last_contact: str
    meetings_count: int = 0
    emails_count: int = 0
    proposal_sent: bool = False
    decision_makers: int = 0
    avg_cycle_days: Optional[int] = None
    similar_win_rate: Optional[float] = None


class ConversationSummaryRequest(BaseModel):
    conversation_text: str
    participants: List[str]
    conversation_type: str = "meeting"
    duration: Optional[str] = None
    date: Optional[str] = None


# ============================================================================
# LEAD SCORING ENDPOINTS
# ============================================================================

@router.post("/lead/score")
async def score_lead(request: LeadScoreRequest):
    """
    Score a lead using Claude AI
    Returns score (0-100), category, reasoning, and next action
    """
    try:
        scorer = get_lead_scorer()
        result = scorer.score_lead(
            company_name=request.company_name,
            industry=request.industry,
            company_size=request.company_size,
            revenue=request.revenue,
            job_title=request.job_title,
            seniority=request.seniority,
            department=request.department,
            email_opens=request.email_opens,
            website_visits=request.website_visits,
            content_downloads=request.content_downloads,
            pricing_views=request.pricing_views,
            demo_requests=request.demo_requests
        )
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"Lead scoring error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/lead/score/batch")
async def batch_score_leads(leads: List[Dict[str, Any]] = Body(...)):
    """
    Score multiple leads in batch
    """
    try:
        scorer = get_lead_scorer()
        results = scorer.batch_score_leads(leads)
        return {"success": True, "data": results}
    except Exception as e:
        logger.error(f"Batch lead scoring error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# INTENT DETECTION ENDPOINTS
# ============================================================================

@router.post("/intent/detect")
async def detect_intent(request: IntentDetectionRequest):
    """
    Detect intent from a customer message
    Returns primary intent, confidence, sentiment, and urgency
    """
    try:
        detector = get_intent_detector()
        result = detector.detect_intent(
            message=request.message,
            customer_stage=request.customer_stage,
            interaction_count=request.interaction_count,
            account_status=request.account_status
        )
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"Intent detection error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/intent/detect/batch")
async def batch_detect_intents(messages: List[Dict[str, Any]] = Body(...)):
    """
    Detect intents for multiple messages in batch
    """
    try:
        detector = get_intent_detector()
        results = detector.batch_detect_intents(messages)
        return {"success": True, "data": results}
    except Exception as e:
        logger.error(f"Batch intent detection error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/intent/route")
async def route_message(request: IntentDetectionRequest):
    """
    Detect intent and determine routing
    Returns team assignment and priority
    """
    try:
        detector = get_intent_detector()
        intent_result = detector.detect_intent(
            message=request.message,
            customer_stage=request.customer_stage,
            interaction_count=request.interaction_count,
            account_status=request.account_status
        )
        
        team = detector.route_message(intent_result)
        is_priority = detector.is_high_priority(intent_result)
        actions = detector.get_suggested_actions(intent_result)
        
        return {
            "success": True,
            "data": {
                "intent": intent_result,
                "routing": {
                    "team": team,
                    "is_priority": is_priority,
                    "suggested_actions": actions
                }
            }
        }
    except Exception as e:
        logger.error(f"Message routing error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# EMAIL DRAFTING ENDPOINTS
# ============================================================================

@router.post("/email/draft")
async def draft_email(request: EmailDraftRequest):
    """
    Generate 3 email variants with different tones
    Returns professional, friendly, and casual versions
    """
    try:
        drafter = get_email_drafter()
        result = drafter.draft_email(
            recipient_name=request.recipient_name,
            company_name=request.company_name,
            scenario=request.scenario,
            goal=request.goal,
            key_points=request.key_points,
            previous_context=request.previous_context
        )
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"Email drafting error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/email/draft/followup")
async def draft_followup_email(
    recipient_name: str = Body(...),
    company_name: str = Body(...),
    previous_interaction: str = Body(...),
    days_since_last_contact: int = Body(...)
):
    """
    Generate follow-up email variants
    """
    try:
        drafter = get_email_drafter()
        result = drafter.draft_follow_up(
            recipient_name=recipient_name,
            company_name=company_name,
            previous_interaction=previous_interaction,
            days_since_last_contact=days_since_last_contact
        )
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"Follow-up email drafting error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/email/draft/cold")
async def draft_cold_outreach(
    recipient_name: str = Body(...),
    company_name: str = Body(...),
    industry: str = Body(...),
    pain_point: str = Body(...)
):
    """
    Generate cold outreach email variants
    """
    try:
        drafter = get_email_drafter()
        result = drafter.draft_cold_outreach(
            recipient_name=recipient_name,
            company_name=company_name,
            industry=industry,
            pain_point=pain_point
        )
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"Cold outreach email drafting error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# DEAL FORECASTING ENDPOINTS
# ============================================================================

@router.post("/deal/forecast")
async def forecast_deal(request: DealForecastRequest):
    """
    Forecast deal outcome with probability and timeline
    Returns close probability, predicted date, risks, and actions
    """
    try:
        forecaster = get_deal_forecaster()
        result = forecaster.forecast_deal(
            deal_value=request.deal_value,
            stage=request.stage,
            days_in_stage=request.days_in_stage,
            created_date=request.created_date,
            expected_close=request.expected_close,
            company_name=request.company_name,
            industry=request.industry,
            company_size=request.company_size,
            last_contact=request.last_contact,
            meetings_count=request.meetings_count,
            emails_count=request.emails_count,
            proposal_sent=request.proposal_sent,
            decision_makers=request.decision_makers,
            avg_cycle_days=request.avg_cycle_days,
            similar_win_rate=request.similar_win_rate
        )
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"Deal forecasting error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/deal/forecast/batch")
async def batch_forecast_deals(deals: List[Dict[str, Any]] = Body(...)):
    """
    Forecast multiple deals in batch
    """
    try:
        forecaster = get_deal_forecaster()
        results = forecaster.batch_forecast_deals(deals)
        return {"success": True, "data": results}
    except Exception as e:
        logger.error(f"Batch deal forecasting error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/deal/forecast/pipeline")
async def forecast_pipeline(deals: List[Dict[str, Any]] = Body(...)):
    """
    Generate overall pipeline forecast
    Returns aggregated metrics by forecast category
    """
    try:
        forecaster = get_deal_forecaster()
        result = forecaster.get_pipeline_forecast(deals)
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"Pipeline forecasting error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/deal/at-risk")
async def identify_at_risk_deals(
    deals: List[Dict[str, Any]] = Body(...),
    threshold: int = Body(30)
):
    """
    Identify deals at risk of being lost
    """
    try:
        forecaster = get_deal_forecaster()
        results = forecaster.identify_at_risk_deals(deals, threshold)
        return {"success": True, "data": results}
    except Exception as e:
        logger.error(f"At-risk deal identification error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# CONVERSATION SUMMARIZATION ENDPOINTS
# ============================================================================

@router.post("/conversation/summarize")
async def summarize_conversation(request: ConversationSummaryRequest):
    """
    Summarize a conversation with action items
    Returns summary, key points, decisions, and next steps
    """
    try:
        summarizer = get_summarizer()
        result = summarizer.summarize_conversation(
            conversation_text=request.conversation_text,
            participants=request.participants,
            conversation_type=request.conversation_type,
            duration=request.duration,
            date=request.date
        )
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"Conversation summarization error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/conversation/summarize/email")
async def summarize_email_thread(emails: List[Dict[str, Any]] = Body(...)):
    """
    Summarize an email thread
    """
    try:
        summarizer = get_summarizer()
        result = summarizer.summarize_email_thread(emails)
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"Email thread summarization error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/conversation/summarize/meeting")
async def summarize_meeting(
    transcript: str = Body(...),
    attendees: List[str] = Body(...),
    duration: str = Body(...),
    date: str = Body(...)
):
    """
    Summarize a meeting
    """
    try:
        summarizer = get_summarizer()
        result = summarizer.summarize_meeting(
            transcript=transcript,
            attendees=attendees,
            duration=duration,
            date=date
        )
        return {"success": True, "data": result}
    except Exception as e:
        logger.error(f"Meeting summarization error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# HEALTH CHECK
# ============================================================================

@router.get("/health")
async def health_check():
    """
    Check if Claude AI services are available
    """
    try:
        from ..services.claude_client import get_claude_client
        client = get_claude_client()
        return {
            "success": True,
            "status": "healthy",
            "model": client.model,
            "services": [
                "lead_scoring",
                "intent_detection",
                "email_drafting",
                "deal_forecasting",
                "conversation_summarization"
            ]
        }
    except Exception as e:
        logger.error(f"Health check failed: {str(e)}")
        return {
            "success": False,
            "status": "unhealthy",
            "error": str(e)
        }
