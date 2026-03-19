"""
AI Email Drafter using Claude API
Generates 3 email variants with different tones
Requirements: Master Spec - AI Email Generation
"""

from typing import Dict, Any, List, Optional
import logging
from .claude_client import get_claude_client
from ..models.prompts import (
    EMAIL_DRAFTER_SYSTEM,
    EMAIL_DRAFTER_PROMPT,
    format_prompt
)

logger = logging.getLogger(__name__)


class EmailDrafterClaude:
    """
    AI email drafting service powered by Claude
    Generates 3 variants: professional, friendly, casual
    """
    
    def __init__(self):
        self.client = get_claude_client()
    
    def draft_email(
        self,
        recipient_name: str,
        company_name: str,
        scenario: str,
        goal: str,
        key_points: List[str],
        previous_context: Optional[str] = None
    ) -> Dict[str, Any]:
        """
        Generate 3 email variants for a scenario
        
        Args:
            recipient_name: Name of the recipient
            company_name: Recipient's company name
            scenario: Email scenario (follow_up, cold_outreach, demo_invite, etc.)
            goal: What you want to achieve with this email
            key_points: List of key points to include
            previous_context: Optional context from previous conversations
            
        Returns:
            Dict with variant_1, variant_2, variant_3 (each with subject, body, tone, length)
        """
        try:
            # Format key points as bullet list
            key_points_str = "\n".join([f"- {point}" for point in key_points])
            
            # Format the prompt
            prompt = format_prompt(
                EMAIL_DRAFTER_PROMPT,
                recipient_name=recipient_name,
                company_name=company_name,
                scenario=scenario,
                goal=goal,
                key_points=key_points_str,
                previous_context=previous_context or "No previous context"
            )
            
            # Call Claude API
            response = self.client.generate_json(
                prompt=prompt,
                system_prompt=EMAIL_DRAFTER_SYSTEM,
                temperature=0.7  # Higher temperature for creative writing
            )
            
            # Validate response
            self.client.validate_response(response, [
                'variant_1', 'variant_2', 'variant_3'
            ])
            
            # Validate each variant has required fields
            for variant_key in ['variant_1', 'variant_2', 'variant_3']:
                variant = response[variant_key]
                if 'subject' not in variant or 'body' not in variant:
                    raise ValueError(f"{variant_key} missing subject or body")
            
            logger.info(f"Generated 3 email variants for {recipient_name} at {company_name}")
            
            return response
            
        except Exception as e:
            logger.error(f"Email drafting failed: {str(e)}")
            # Return fallback emails
            return self._generate_fallback_emails(
                recipient_name, company_name, scenario, goal
            )
    
    def draft_follow_up(
        self,
        recipient_name: str,
        company_name: str,
        previous_interaction: str,
        days_since_last_contact: int
    ) -> Dict[str, Any]:
        """
        Generate follow-up email variants
        
        Args:
            recipient_name: Name of the recipient
            company_name: Company name
            previous_interaction: Summary of previous interaction
            days_since_last_contact: Days since last contact
            
        Returns:
            3 email variants
        """
        scenario = "follow_up"
        goal = f"Re-engage after {days_since_last_contact} days of no contact"
        key_points = [
            f"Reference previous interaction: {previous_interaction}",
            "Provide value or new information",
            "Include clear call-to-action"
        ]
        
        return self.draft_email(
            recipient_name=recipient_name,
            company_name=company_name,
            scenario=scenario,
            goal=goal,
            key_points=key_points,
            previous_context=previous_interaction
        )
    
    def draft_cold_outreach(
        self,
        recipient_name: str,
        company_name: str,
        industry: str,
        pain_point: str
    ) -> Dict[str, Any]:
        """
        Generate cold outreach email variants
        
        Args:
            recipient_name: Name of the recipient
            company_name: Company name
            industry: Recipient's industry
            pain_point: Identified pain point to address
            
        Returns:
            3 email variants
        """
        scenario = "cold_outreach"
        goal = "Start a conversation and book a discovery call"
        key_points = [
            f"Address pain point: {pain_point}",
            f"Show understanding of {industry} industry",
            "Offer specific value proposition",
            "Low-friction CTA (15-min call)"
        ]
        
        return self.draft_email(
            recipient_name=recipient_name,
            company_name=company_name,
            scenario=scenario,
            goal=goal,
            key_points=key_points
        )
    
    def draft_demo_invite(
        self,
        recipient_name: str,
        company_name: str,
        features_of_interest: List[str]
    ) -> Dict[str, Any]:
        """
        Generate demo invitation email variants
        
        Args:
            recipient_name: Name of the recipient
            company_name: Company name
            features_of_interest: Features they've shown interest in
            
        Returns:
            3 email variants
        """
        scenario = "demo_invitation"
        goal = "Schedule a personalized product demo"
        key_points = [
            "Highlight relevant features: " + ", ".join(features_of_interest),
            "Emphasize personalization",
            "Offer flexible scheduling",
            "Include calendar link"
        ]
        
        return self.draft_email(
            recipient_name=recipient_name,
            company_name=company_name,
            scenario=scenario,
            goal=goal,
            key_points=key_points
        )
    
    def draft_proposal_followup(
        self,
        recipient_name: str,
        company_name: str,
        proposal_sent_date: str,
        proposal_value: float
    ) -> Dict[str, Any]:
        """
        Generate proposal follow-up email variants
        
        Args:
            recipient_name: Name of the recipient
            company_name: Company name
            proposal_sent_date: When proposal was sent
            proposal_value: Proposal value in USD
            
        Returns:
            3 email variants
        """
        scenario = "proposal_followup"
        goal = "Get feedback on proposal and move to next step"
        key_points = [
            f"Reference proposal sent on {proposal_sent_date}",
            "Ask for specific feedback",
            "Address potential concerns proactively",
            "Offer to discuss or adjust terms"
        ]
        
        return self.draft_email(
            recipient_name=recipient_name,
            company_name=company_name,
            scenario=scenario,
            goal=goal,
            key_points=key_points,
            previous_context=f"Sent ${proposal_value:,.2f} proposal on {proposal_sent_date}"
        )
    
    def draft_churn_prevention(
        self,
        recipient_name: str,
        company_name: str,
        churn_signals: List[str]
    ) -> Dict[str, Any]:
        """
        Generate churn prevention email variants
        
        Args:
            recipient_name: Name of the recipient
            company_name: Company name
            churn_signals: List of churn signals detected
            
        Returns:
            3 email variants
        """
        scenario = "churn_prevention"
        goal = "Re-engage at-risk customer and prevent churn"
        key_points = [
            "Show empathy and understanding",
            "Offer to help solve their challenges",
            "Provide incentive or special offer",
            "Schedule call with customer success"
        ]
        
        return self.draft_email(
            recipient_name=recipient_name,
            company_name=company_name,
            scenario=scenario,
            goal=goal,
            key_points=key_points,
            previous_context=f"Churn signals detected: {', '.join(churn_signals)}"
        )
    
    def _generate_fallback_emails(
        self,
        recipient_name: str,
        company_name: str,
        scenario: str,
        goal: str
    ) -> Dict[str, Any]:
        """
        Generate simple fallback emails when AI fails
        """
        return {
            "variant_1": {
                "subject": f"Following up - {company_name}",
                "body": f"Hi {recipient_name},\n\nI wanted to follow up regarding {goal}.\n\nWould you be available for a quick call this week?\n\nBest regards",
                "tone": "professional",
                "length": "short"
            },
            "variant_2": {
                "subject": f"Quick question for {company_name}",
                "body": f"Hey {recipient_name},\n\nHope you're doing well! I wanted to reach out about {goal}.\n\nLet me know if you'd like to chat.\n\nCheers",
                "tone": "friendly",
                "length": "medium"
            },
            "variant_3": {
                "subject": f"Re: {company_name}",
                "body": f"{recipient_name} - quick follow up on {goal}. Available this week?",
                "tone": "casual",
                "length": "brief"
            },
            "error": "AI generation failed, using fallback templates"
        }
    
    def get_best_variant(
        self,
        variants: Dict[str, Any],
        preferred_tone: str = "professional",
        preferred_length: str = "medium"
    ) -> Dict[str, Any]:
        """
        Select the best variant based on preferences
        
        Args:
            variants: Result from draft_email()
            preferred_tone: Preferred tone (professional, friendly, casual)
            preferred_length: Preferred length (short, medium, brief)
            
        Returns:
            The best matching variant
        """
        for variant_key in ['variant_1', 'variant_2', 'variant_3']:
            variant = variants[variant_key]
            if (variant.get('tone') == preferred_tone or 
                variant.get('length') == preferred_length):
                return variant
        
        # Default to variant_1 if no match
        return variants['variant_1']


# Singleton instance
_email_drafter = None

def get_email_drafter() -> EmailDrafterClaude:
    """Get or create the singleton email drafter instance"""
    global _email_drafter
    if _email_drafter is None:
        _email_drafter = EmailDrafterClaude()
    return _email_drafter
