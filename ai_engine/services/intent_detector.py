import structlog
import json
from ai_engine.clients.anthropic_client import AnthropicClient
from ai_engine.models.schemas import IntentDetectionRequest, IntentDetectionResponse, IntentCategory
from typing import Optional, List, Dict

logger = structlog.get_logger()

class IntentDetector:
    def __init__(self):
        self._client = AnthropicClient()

    async def detect_intent(self, request: IntentDetectionRequest) -> IntentDetectionResponse:
        # 1. Construct Prompt for Claude
        system_prompt = (
            "You are an expert CRM analyst. Analyze the following message and conversation history "
            "to detect primary intent. Choose ONLY one from: buying_intent, churn_risk, support_request, neutral. "
            "Provide a confidence score (0.0 to 1.0) and brief reasoning. Respond ONLY in valid JSON format."
        )

        user_message = f"Message: {request.message_text}\nContext: {json.dumps(request.sender_context)}\nHistory: {json.dumps(request.conversation_history)}"
        
        # 2. Call Anthropic Service
        try:
            response = await self._client.chat_complete(
                messages=[{"role": "user", "content": user_message}],
                system=system_prompt,
                model="claude-3-sonnet-20240229",
                temperature=0.1
            )

            # 3. Parse JSON from Claude response
            # Assuming Claude returns: {"intent": "...", "confidence": 0.9, "reasoning": "..."}
            # Note: In production, use robust parsing as models occasionally wrap JSON in code blocks
            content_str = response['content'][0]['text']
            # Basic cleanup for accidental markdown blocks
            if "```json" in content_str:
                content_str = content_str.split("```json")[1].split("```")[0].strip()
            
            data = json.loads(content_str)
            
            return IntentDetectionResponse(
                intent=IntentCategory(data.get("intent", "neutral")),
                confidence=float(data.get("confidence", 0.5)),
                reasoning=data.get("reasoning", "Analysis based on message context")
            )

        except Exception as e:
            logger.error("intent_detection_failed", error=str(e))
            raise e

    async def close(self):
        await self._client.close()
