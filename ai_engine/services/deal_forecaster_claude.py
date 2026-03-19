"""
Deal Forecasting Service using Claude API
Predicts deal close probability and timeline
Requirements: Master Spec - AI Deal Forecasting
"""

from typing import Dict, Any, Optional
from datetime import datetime, timedelta
import logging
from .claude_client import get_claude_client
from ..models.prompts import (
    DEAL_FORECASTING_SYSTEM,
    DEAL_FORECASTING_PROMPT,
    format_prompt
)

logger = logging.getLogger(__name__)


class DealForecasterClaude:
    """
    Deal forecasting service powered by Claude AI
    Predicts close probability, timeline, and risks
    """
    
    def __init__(self):
        self.client = get_claude_client()
    
    def forecast_deal(
        self,
        deal_value: float,
        stage: str,
        days_in_stage: int,
        created_date: str,
        expected_close: str,
        company_name: str,
        industry: str,
        company_size: int,
        last_contact: str,
        meetings_count: int = 0,
        emails_count: int = 0,
        proposal_sent: bool = False,
        decision_makers: int = 0,
        avg_cycle_days: Optional[int] = None,
        similar_win_rate: Optional[float] = None
    ) -> Dict[str, Any]:
        """
        Forecast a deal's outcome
        
        Args:
            deal_value: Deal value in USD
            stage: Current deal stage
            days_in_stage: Days in current stage
            created_date: Deal creation date (YYYY-MM-DD)
            expected_close: Expected close date (YYYY-MM-DD)
            company_name: Company name
            industry: Industry
            company_size: Number of employees
            last_contact: Last contact date (YYYY-MM-DD)
            meetings_count: Number of meetings held
            emails_count: Number of emails exchanged
            proposal_sent: Whether proposal was sent
            decision_makers: Number of decision makers engaged
            avg_cycle_days: Average deal cycle in days (optional)
            similar_win_rate: Win rate for similar deals (optional)
            
        Returns:
            Dict with close_probability, predicted_close_date, risk_factors, etc.
        """
        try:
            # Format the prompt
            prompt = format_prompt(
                DEAL_FORECASTING_PROMPT,
                deal_value=f"{deal_value:,.2f}",
                stage=stage,
                days_in_stage=days_in_stage,
                created_date=created_date,
                expected_close=expected_close,
                company_name=company_name,
                industry=industry,
                company_size=company_size,
                last_contact=last_contact,
                meetings_count=meetings_count,
                emails_count=emails_count,
                proposal_sent="Yes" if proposal_sent else "No",
                decision_makers=decision_makers,
                avg_cycle_days=avg_cycle_days or "Unknown",
                similar_win_rate=similar_win_rate or "Unknown"
            )
            
            # Call Claude API
            response = self.client.generate_json(
                prompt=prompt,
                system_prompt=DEAL_FORECASTING_SYSTEM,
                temperature=0.3  # Lower temperature for consistent forecasting
            )
            
            # Validate response
            self.client.validate_response(response, [
                'close_probability', 'predicted_close_date', 'confidence', 'forecast_category'
            ])
            
            # Ensure probability is in valid range
            response['close_probability'] = max(0, min(100, response['close_probability']))
            response['confidence'] = max(0.0, min(1.0, response['confidence']))
            
            logger.info(
                f"Forecasted deal {company_name}: {response['close_probability']}% "
                f"({response['forecast_category']})"
            )
            
            return response
            
        except Exception as e:
            logger.error(f"Deal forecasting failed: {str(e)}")
            # Return fallback forecast
            return {
                "close_probability": 50,
                "predicted_close_date": expected_close,
                "confidence": 0.5,
                "risk_factors": ["Unable to forecast due to AI error"],
                "positive_signals": [],
                "recommended_actions": ["Manual review required"],
                "forecast_category": "pipeline",
                "error": str(e)
            }
    
    def batch_forecast_deals(self, deals: list) -> list:
        """
        Forecast multiple deals in batch
        
        Args:
            deals: List of deal dicts with required fields
            
        Returns:
            List of forecast results
        """
        results = []
        
        for deal in deals:
            try:
                forecast_result = self.forecast_deal(
                    deal_value=deal.get('value', 0),
                    stage=deal.get('stage', 'Unknown'),
                    days_in_stage=deal.get('days_in_stage', 0),
                    created_date=deal.get('created_date', ''),
                    expected_close=deal.get('expected_close', ''),
                    company_name=deal.get('company_name', 'Unknown'),
                    industry=deal.get('industry', 'Unknown'),
                    company_size=deal.get('company_size', 0),
                    last_contact=deal.get('last_contact', ''),
                    meetings_count=deal.get('meetings_count', 0),
                    emails_count=deal.get('emails_count', 0),
                    proposal_sent=deal.get('proposal_sent', False),
                    decision_makers=deal.get('decision_makers', 0),
                    avg_cycle_days=deal.get('avg_cycle_days'),
                    similar_win_rate=deal.get('similar_win_rate')
                )
                
                results.append({
                    'deal_id': deal.get('id'),
                    'company_name': deal.get('company_name'),
                    **forecast_result
                })
                
            except Exception as e:
                logger.error(f"Failed to forecast deal {deal.get('id')}: {str(e)}")
                results.append({
                    'deal_id': deal.get('id'),
                    'company_name': deal.get('company_name'),
                    'error': str(e)
                })
        
        return results
    
    def get_pipeline_forecast(self, deals: list) -> Dict[str, Any]:
        """
        Generate overall pipeline forecast
        
        Args:
            deals: List of all deals in pipeline
            
        Returns:
            Aggregated forecast metrics
        """
        forecasts = self.batch_forecast_deals(deals)
        
        # Aggregate by forecast category
        commit = []
        best_case = []
        pipeline = []
        omitted = []
        
        for forecast in forecasts:
            if 'error' in forecast:
                continue
                
            category = forecast.get('forecast_category', 'pipeline')
            deal_value = next(
                (d['value'] for d in deals if d.get('id') == forecast.get('deal_id')),
                0
            )
            weighted_value = deal_value * (forecast.get('close_probability', 0) / 100)
            
            forecast_data = {
                'deal_id': forecast.get('deal_id'),
                'company_name': forecast.get('company_name'),
                'value': deal_value,
                'weighted_value': weighted_value,
                'probability': forecast.get('close_probability')
            }
            
            if category == 'commit':
                commit.append(forecast_data)
            elif category == 'best_case':
                best_case.append(forecast_data)
            elif category == 'pipeline':
                pipeline.append(forecast_data)
            else:
                omitted.append(forecast_data)
        
        return {
            'commit': {
                'count': len(commit),
                'total_value': sum(d['value'] for d in commit),
                'weighted_value': sum(d['weighted_value'] for d in commit),
                'deals': commit
            },
            'best_case': {
                'count': len(best_case),
                'total_value': sum(d['value'] for d in best_case),
                'weighted_value': sum(d['weighted_value'] for d in best_case),
                'deals': best_case
            },
            'pipeline': {
                'count': len(pipeline),
                'total_value': sum(d['value'] for d in pipeline),
                'weighted_value': sum(d['weighted_value'] for d in pipeline),
                'deals': pipeline
            },
            'omitted': {
                'count': len(omitted),
                'total_value': sum(d['value'] for d in omitted),
                'deals': omitted
            }
        }
    
    def identify_at_risk_deals(self, deals: list, threshold: int = 30) -> list:
        """
        Identify deals at risk of being lost
        
        Args:
            deals: List of deals to analyze
            threshold: Probability threshold below which deals are at risk
            
        Returns:
            List of at-risk deals with recommendations
        """
        forecasts = self.batch_forecast_deals(deals)
        at_risk = []
        
        for forecast in forecasts:
            if 'error' in forecast:
                continue
                
            probability = forecast.get('close_probability', 100)
            if probability < threshold:
                at_risk.append({
                    'deal_id': forecast.get('deal_id'),
                    'company_name': forecast.get('company_name'),
                    'probability': probability,
                    'risk_factors': forecast.get('risk_factors', []),
                    'recommended_actions': forecast.get('recommended_actions', [])
                })
        
        # Sort by probability (lowest first)
        at_risk.sort(key=lambda x: x['probability'])
        
        return at_risk


# Singleton instance
_deal_forecaster = None

def get_deal_forecaster() -> DealForecasterClaude:
    """Get or create the singleton deal forecaster instance"""
    global _deal_forecaster
    if _deal_forecaster is None:
        _deal_forecaster = DealForecasterClaude()
    return _deal_forecaster
