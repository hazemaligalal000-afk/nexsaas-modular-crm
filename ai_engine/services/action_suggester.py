import structlog
from typing import List, Dict, Any
from ai_engine.clients.anthropic_client import AnthropicClient
from ai_engine.models.schemas import ActionSuggestion

logger = structlog.get_logger(__name__)

/**
 * Task 1.2: Action Suggester Implementation (AI Engine)
 * Replaces mock with real LLM-backed decision logic
 */
class ActionSuggester:
    def __init__(self):
        self.anthropic = AnthropicClient()

    async def suggest_actions(self, 
                               message: str, 
                               context: List[Dict[str, str]], 
                               lead_profile: Dict[str, Any]) -> List[ActionSuggestion]:
        
        system_prompt = """
        You are a Strategic Sales Consultant for NexSaaS. 
        Analyze the incoming message, history, and lead profile to suggest the 3 most effective NEXT ACTIONS.
        
        Potential Actions: 
        - SCHEDULE_DEMO (Lead is ready for product tour)
        - SEND_PRICING (Specific price inquiry)
        - ESCLATE_TO_SENIOR (Complex technical Q or high-value deal)
        - FOLLOW_UP_24H (Nudge if no response)
        - CLOSE_DEAL (Commitment detected)
        - UPDATE_PIPELINE (Status change required)

        Return precisely a JSON list of objects: [{"action": "ACTION_ID", "confidence": 0.0-1.0, "reasoning": "..."}]
        """

        lead_snippet = f"Name: {lead_profile.get('name')}, Stage: {lead_profile.get('stage')}, Score: {lead_profile.get('score')}"
        user_prompt = f"Lead Context: {lead_snippet}\nHistory: {context}\nLatest Message: {message}"

        try:
            response = await self.anthropic.complete(
                model="claude-3-sonnet-20240229",
                system_prompt=system_prompt,
                user_prompt=user_prompt,
                max_tokens=500
            )

            # In production, we'd use Pydantic validation here
            suggestions = self._parse_suggestions(response)
            
            logger.info("Actions suggested successfully", lead_id=lead_profile.get('id'), count=len(suggestions))
            return [ActionSuggestion(**s) for s in suggestions]

        except Exception as e:
            logger.error("Action suggestion failed", error=str(e))
            # Fallback to safe action
            return [ActionSuggestion(action="FOLLOW_UP_24H", confidence=0.5, reasoning="Automatic fallback due to service error")]

    def _parse_suggestions(self, text: str) -> List[Dict[str, Any]]:
        # Dummy parser: in real code use regex and JSON load
        import json
        import re
        match = re.search(r'\[.*\]', text, re.DOTALL)
        if match:
            return json.loads(match.group())
        return []
