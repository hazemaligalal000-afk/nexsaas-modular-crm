import structlog
from fastapi import APIRouter, Request, HTTPException
from ai_engine.models.schemas import (
    ContentGenerationRequest, ContentGenerationResponse,
    ActionSuggestionRequest, ActionSuggestionResponse
)
from ai_engine.services.content_generator import ContentGenerator
# from ai_engine.services.action_suggester import ActionSuggester (To be implemented next)
from typing import List, Dict, Any

from ai_engine.services.action_suggester import ActionSuggester

router = APIRouter()
logger = structlog.get_logger()
generator = ContentGenerator()
suggester = ActionSuggester()

# 1. Generate Intelligent CRM Drafts
@router.post("/generate-reply", response_model=ContentGenerationResponse)
async def generate_reply_endpoint(request: Request, body: ContentGenerationRequest):
    tenant_id = getattr(request.state, "tenant_id", "unknown")
    logger.info("generate_reply_request", tenant_id=tenant_id, tone=body.tone)

    try:
        response = await generator.generate_reply(body)
        return response

    except Exception as e:
        logger.error("generate_reply_failed", tenant_id=tenant_id, error=str(e))
        raise HTTPException(status_code=500, detail="AI Content Generation failed")

# 2. Suggest Next Strategic Actions
@router.post("/suggest-actions", response_model=List[ActionSuggestionResponse])
async def suggest_actions_endpoint(request: Request, body: ActionSuggestionRequest):
    tenant_id = getattr(request.state, "tenant_id", "unknown")
    logger.info("suggest_actions_request", tenant_id=tenant_id)

    try:
        # REAL IMPLEMENTATION
        # Pass body directly as it contains the message and context
        suggestions = await suggester.suggest_actions(
            body.message, 
            body.context, 
            {"id": "unknown", "name": "Unknown Lead"}
        )
        
        return [
            ActionSuggestionResponse(
                action=s.action, 
                confidence=s.confidence, 
                reasoning=s.reasoning
            ) for s in suggestions
        ]

    except Exception as e:
        logger.error("suggest_actions_failed", tenant_id=tenant_id, error=str(e))
        raise HTTPException(status_code=500, detail="Action suggestion engine failed")
