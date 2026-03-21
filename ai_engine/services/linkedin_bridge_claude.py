import logging
import json
from typing import Dict, Any, Optional
from .claude_client import get_claude_client
from ..models.prompts import format_prompt

logger = logging.getLogger(__name__)

LINKEDIN_PROFESSIONAL_SYSTEM = """You are an expert at B2B professional communication on LinkedIn.
Your task is to analyze incoming LinkedIn messages and suggest high-conversion, professional replies.
Always maintain a balance of professional etiquette and strategic persistence."""

LINKEDIN_ANALYSIS_PROMPT = """Analyze this LinkedIn message and provide B2B context:

Message:
"{message}"

Context:
- Sender Profile: {sender_job_title} at {sender_company}
- Lead Segment: {segment}
- Interaction History: {history_summary}

Respond with JSON:
{{
  "intent": "<intent_category>",
  "sentiment": "<tone_analysis>",
  "suggested_reply": "<professional_linkedin_style_reply>",
  "perceived_urgency": <0-1>,
  "next_best_step": "<strategic_action>",
  "icebreaker_context": "<relevant_company_news_or_insight>"
}}"""

class LinkedInAIBridge:
    """
    NexSaaS LinkedIn Intelligence Bridge
    Enriches LinkedIn messages with B2B graph data and AI insights.
    Requirements: Master Spec 3.77 - Omnichannel Social
    """
    
    def __init__(self):
        self.claude = get_claude_client()
    
    def analyze_social_signal(
        self,
        message: str,
        sender_job_title: str = "Decision Maker",
        sender_company: str = "Target Account",
        segment: str = "Enterprise",
        history_summary: str = "No previous history"
    ) -> Dict[str, Any]:
        """
        Analyze a social signal (LinkedIn message) to determine B2B intent.
        """
        try:
            prompt = format_prompt(
                LINKEDIN_ANALYSIS_PROMPT,
                message=message,
                sender_job_title=sender_job_title,
                sender_company=sender_company,
                segment=segment,
                history_summary=history_summary
            )
            
            result = self.claude.generate_json(
                prompt=prompt,
                system_prompt=LINKEDIN_PROFESSIONAL_SYSTEM,
                temperature=0.4
            )
            
            logger.info(f"Analyzed LinkedIn message from {sender_company}: {result['intent']}")
            return result
            
        except Exception as e:
            logger.error(f"LinkedIn AI analysis failed: {str(e)}")
            return {
                "intent": "unknown",
                "suggested_reply": "Hi, thanks for reaching out. Let's connect.",
                "perceived_urgency": 0.5,
                "error": str(e)
            }

# Singleton instance
_linkedin_bridge = None

def get_linkedin_bridge() -> LinkedInAIBridge:
    global _linkedin_bridge
    if _linkedin_bridge is None:
        _linkedin_bridge = LinkedInAIBridge()
    return _linkedin_bridge
