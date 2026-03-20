import structlog
from fastapi import APIRouter, Request, HTTPException
from ai_engine.models.schemas import LeadScoreRequest, LeadScoreResponse
from ai_engine.services.lead_scorer import LeadScorer

router = APIRouter()
scorer = LeadScorer()
logger = structlog.get_logger()

@router.post("/score", response_model=LeadScoreResponse)
async def score_lead_endpoint(request: Request, body: LeadScoreRequest):
    tenant_id = getattr(request.state, "tenant_id", "unknown")
    
    logger.info("lead_score_request", tenant_id=tenant_id, lead_id=body.lead.id)

    try:
        # 1. Process Score
        response = await scorer.score_lead(body.lead)
        
        # 2. Log Result for usage tracking (Task 7.1)
        # UsageTracker.track(tenant_id, "score_lead", total_tokens=0)
        
        return response

    except Exception as e:
        logger.error("lead_score_failed", tenant_id=tenant_id, error=str(e))
        raise HTTPException(status_code=500, detail="AI Scoring failed")
