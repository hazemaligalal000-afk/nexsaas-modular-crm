import math
import structlog
from ai_engine.models.schemas import LeadData, LeadScoreResponse, ScoringFactor
from typing import List, Dict, Any

logger = structlog.get_logger()

class LeadScorer:
    # 1. Weights from Requirements
    DEMOGRAPHIC_WEIGHT = 0.30
    BEHAVIORAL_WEIGHT = 0.40
    ENGAGEMENT_WEIGHT = 0.20
    PIPELINE_WEIGHT = 0.10

    async def score_lead(self, lead: LeadData) -> LeadScoreResponse:
        factors: List[ScoringFactor] = []
        
        # 2. Demographic Score (30%)
        demo_score = 0
        if lead.email and not self._is_public_provider(lead.email):
            demo_score += 40
            factors.append(ScoringFactor(name="Corporate Email", weight=0.12, impact="positive", description="Lead uses a business domain email"))
        
        if lead.company_size and lead.company_size > 50:
            demo_score += 30
            factors.append(ScoringFactor(name="Company Size Match", weight=0.09, impact="positive", description=f"Company with {lead.company_size} employees matches ideal profile"))

        if lead.industry in ["SaaS", "FinTech", "HealthTech"]:
            demo_score += 30
            factors.append(ScoringFactor(name="Target Industry", weight=0.09, impact="positive", description=f"Industry '{lead.industry}' is a high-priority segment"))

        # 3. Behavioral Score (40%)
        behav_score = min(100, (lead.website_visits * 5) + (lead.email_clicks * 10) + (lead.form_submissions * 25))
        if behav_score > 50:
             factors.append(ScoringFactor(name="High Digital Engagement", weight=0.20, impact="positive", description="Significant website and email activity detected"))

        # 4. Engagement Recency (20%) - Decay Function
        # Score decreases as days_since_last_activity increases
        engagement_score = 100 * math.exp(-0.05 * lead.days_since_last_activity)
        if lead.days_since_last_activity > 14:
            factors.append(ScoringFactor(name="Recency Decay", weight=0.10, impact="negative", description=f"Last activity was {lead.days_since_last_activity} days ago"))

        # 5. Pipeline Score (10%)
        stage_map = {"new": 20, "qualified": 60, "demo_scheduled": 90, "negotiation": 100}
        pipe_score = stage_map.get(lead.current_stage, 10)

        # 6. Final Calculation
        total_score = (
            (demo_score * self.DEMOGRAPHIC_WEIGHT) +
            (behav_score * self.BEHAVIORAL_WEIGHT) +
            (engagement_score * self.ENGAGEMENT_WEIGHT) +
            (pipe_score * self.PIPELINE_WEIGHT)
        )

        # 7. LLM-based Explanation Layer (Requirement Phase 1/1.1)
        explanation = await self._generate_ai_explanation(lead, int(total_score), factors)

        # 8. Confidence Calculation (Based on data completeness)
        data_points = [lead.email, lead.company_size, lead.industry, lead.website_visits > 0]
        confidence = sum([1 for p in data_points if p]) / len(data_points)

        return LeadScoreResponse(
            lead_id=lead.id,
            score=int(total_score),
            confidence=round(confidence, 2),
            factors=factors[:3], # Return top 3 contributing factors
            recommendation=explanation
        )

    async def _generate_ai_explanation(self, lead: LeadData, score: int, factors: List[ScoringFactor]) -> str:
        """
        Generate a human-readable explanation and recommendation using Claude
        """
        try:
            from ai_engine.services.claude_client import get_claude_client
            claude = get_claude_client()
            
            prompt = (
                f"As an AI Sales Assistant, analyze this lead and provide a 1-sentence action recommendation.\n"
                f"Lead Score: {score}/100\n"
                f"Factors: {', '.join([f.description for f in factors])}\n"
                f"Context: Industry {lead.industry}, Company Size {lead.company_size}, "
                f"Last Activity {lead.days_since_last_activity} days ago.\n"
                "Provide ONLY the recommendation sentence."
            )
            
            response = await claude.generate_async(prompt, system_prompt="You are a senior sales strategist.")
            return response.get('content', "Follow up immediately to maintain momentum.")
        except Exception as e:
            logger.error("ai_explanation_failed", error=str(e))
            # Fallback
            if score > 70: return "High potential lead. Schedule a demo within 24 hours."
            if score > 40: return "Warm lead. Share relevant case studies to build trust."
            return "Cold lead. Nurture with automated email sequence."

    def _is_public_provider(self, email: str) -> bool:
        public_domains = ["gmail.com", "outlook.com", "yahoo.com", "hotmail.com"]
        return any(domain in email.lower() for domain in public_domains)
