"""
Lead Scoring Service using Claude API
Scores leads from 0-100 based on fit and engagement
Requirements: Master Spec - AI Lead Scoring
"""

from typing import Dict, Any, Optional
import logging
from .claude_client import get_claude_client
from ..models.prompts import (
    LEAD_SCORING_SYSTEM,
    LEAD_SCORING_PROMPT,
    format_prompt
)

logger = logging.getLogger(__name__)


class LeadScorerClaude:
    """
    Lead scoring service powered by Claude AI
    Analyzes lead data and provides actionable scores
    """
    
    def __init__(self):
        self.client = get_claude_client()
    
    def score_lead(
        self,
        company_name: str,
        industry: str,
        company_size: int,
        revenue: Optional[float] = None,
        job_title: Optional[str] = None,
        seniority: Optional[str] = None,
        department: Optional[str] = None,
        email_opens: int = 0,
        website_visits: int = 0,
        content_downloads: int = 0,
        pricing_views: int = 0,
        demo_requests: int = 0
    ) -> Dict[str, Any]:
        """
        Score a lead using Claude AI
        
        Args:
            company_name: Company name
            industry: Industry/sector
            company_size: Number of employees
            revenue: Annual revenue (optional)
            job_title: Contact's job title
            seniority: Seniority level (C-Level, VP, Director, Manager, IC)
            department: Department (Sales, Marketing, Engineering, etc.)
            email_opens: Number of email opens
            website_visits: Number of website visits
            content_downloads: Number of content downloads
            pricing_views: Number of pricing page views
            demo_requests: Number of demo requests
            
        Returns:
            Dict with score, category, reasoning, next_action, priority
        """
        try:
            # Format the prompt
            prompt = format_prompt(
                LEAD_SCORING_PROMPT,
                company_name=company_name,
                industry=industry,
                company_size=company_size,
                revenue=revenue or "Unknown",
                job_title=job_title or "Unknown",
                seniority=seniority or "Unknown",
                department=department or "Unknown",
                email_opens=email_opens,
                website_visits=website_visits,
                content_downloads=content_downloads,
                pricing_views=pricing_views,
                demo_requests=demo_requests
            )
            
            # Call Claude API
            response = self.client.generate_json(
                prompt=prompt,
                system_prompt=LEAD_SCORING_SYSTEM,
                temperature=0.3  # Lower temperature for more consistent scoring
            )
            
            # Validate response
            self.client.validate_response(response, [
                'score', 'category', 'reasoning', 'next_action', 'priority'
            ])
            
            # Ensure score is in valid range
            response['score'] = max(0, min(100, response['score']))
            
            logger.info(f"Scored lead {company_name}: {response['score']}/100 ({response['category']})")
            
            return response
            
        except Exception as e:
            logger.error(f"Lead scoring failed: {str(e)}")
            # Return fallback score
            return {
                "score": 50,
                "category": "warm",
                "reasoning": "Unable to score lead due to AI error",
                "next_action": "Manual review required",
                "priority": "medium",
                "error": str(e)
            }
    
    def batch_score_leads(self, leads: list) -> list:
        """
        Score multiple leads in batch
        
        Args:
            leads: List of lead dicts with required fields
            
        Returns:
            List of scored leads with results
        """
        results = []
        
        for lead in leads:
            try:
                score_result = self.score_lead(
                    company_name=lead.get('company_name', 'Unknown'),
                    industry=lead.get('industry', 'Unknown'),
                    company_size=lead.get('company_size', 0),
                    revenue=lead.get('revenue'),
                    job_title=lead.get('job_title'),
                    seniority=lead.get('seniority'),
                    department=lead.get('department'),
                    email_opens=lead.get('email_opens', 0),
                    website_visits=lead.get('website_visits', 0),
                    content_downloads=lead.get('content_downloads', 0),
                    pricing_views=lead.get('pricing_views', 0),
                    demo_requests=lead.get('demo_requests', 0)
                )
                
                results.append({
                    'lead_id': lead.get('id'),
                    'company_name': lead.get('company_name'),
                    **score_result
                })
                
            except Exception as e:
                logger.error(f"Failed to score lead {lead.get('id')}: {str(e)}")
                results.append({
                    'lead_id': lead.get('id'),
                    'company_name': lead.get('company_name'),
                    'error': str(e)
                })
        
        return results
    
    def rescore_lead(self, lead_id: int, updated_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Rescore a lead with updated data
        Useful when engagement metrics change
        
        Args:
            lead_id: Lead ID
            updated_data: Updated lead data
            
        Returns:
            New score result
        """
        logger.info(f"Rescoring lead {lead_id} with updated data")
        return self.score_lead(**updated_data)
    
    def explain_score(self, score_result: Dict[str, Any]) -> str:
        """
        Generate a human-readable explanation of the score
        
        Args:
            score_result: Result from score_lead()
            
        Returns:
            Formatted explanation string
        """
        score = score_result.get('score', 0)
        category = score_result.get('category', 'unknown')
        reasoning = score_result.get('reasoning', 'No reasoning provided')
        next_action = score_result.get('next_action', 'No action recommended')
        
        explanation = f"""
Lead Score: {score}/100 ({category.upper()})

Reasoning:
{reasoning}

Recommended Next Action:
{next_action}

Priority: {score_result.get('priority', 'medium').upper()}
        """.strip()
        
        return explanation


# Singleton instance
_lead_scorer = None

def get_lead_scorer() -> LeadScorerClaude:
    """Get or create the singleton lead scorer instance"""
    global _lead_scorer
    if _lead_scorer is None:
        _lead_scorer = LeadScorerClaude()
    return _lead_scorer
