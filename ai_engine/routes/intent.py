import structlog
from fastapi import APIRouter, Request, HTTPException
from ai_engine.models.schemas import IntentDetectionRequest, IntentDetectionResponse
from ai_engine.services.intent_detector import IntentDetector

router = APIRouter()
detector = IntentDetector()
logger = structlog.get_logger()

@router.post("/detect-intent", response_model=IntentDetectionResponse)
async def detect_intent_endpoint(request: Request, body: IntentDetectionRequest):
    tenant_id = getattr(request.state, "tenant_id", "unknown")
    
    logger.info("intent_detection_request", tenant_id=tenant_id)

    try:
        # 1. Detect Intent
        response = await detector.detect_intent(body)
        
        # 2. Log result for usage tracking
        # UsageTracker.track(tenant_id, "detect_intent", total_tokens=0)
        
        return response

    except Exception as e:
        logger.error("intent_detection_failed", tenant_id=tenant_id, error=str(e))
        raise HTTPException(status_code=500, detail="Intent detection failed")
