"""
Intent Detection Service using Claude API
Detects customer intent from messages
Requirements: Master Spec - AI Intent Detection
"""

from typing import Dict, Any, Optional
import logging
from .claude_client import get_claude_client
from ..models.prompts import (
    INTENT_DETECTION_SYSTEM,
    INTENT_DETECTION_PROMPT,
    format_prompt
)

logger = logging.getLogger(__name__)


class IntentDetectorClaude:
    """
    Intent detection service powered by Claude AI
    Analyzes customer messages to detect intent and sentiment
    """
    
    VALID_INTENTS = [
        'purchase_intent',
        'support_request',
        'information_seeking',
        'complaint',
        'feature_request',
        'churn_risk'
    ]
    
    def __init__(self):
        self.client = get_claude_client()
    
    def detect_intent(
        self,
        message: str,
        customer_stage: str = "prospect",
        interaction_count: int = 0,
        account_status: str = "active"
    ) -> Dict[str, Any]:
        """
        Detect intent from a customer message
        
        Args:
            message: The customer message text
            customer_stage: Customer lifecycle stage (prospect, trial, customer, churned)
            interaction_count: Number of previous interactions
            account_status: Account status (active, inactive, trial, cancelled)
            
        Returns:
            Dict with primary_intent, confidence, secondary_intents, sentiment, urgency, suggested_response
        """
        try:
            # Format the prompt
            prompt = format_prompt(
                INTENT_DETECTION_PROMPT,
                message=message,
                customer_stage=customer_stage,
                interaction_count=interaction_count,
                account_status=account_status
            )
            
            # Call Claude API
            response = self.client.generate_json(
                prompt=prompt,
                system_prompt=INTENT_DETECTION_SYSTEM,
                temperature=0.4  # Moderate temperature for intent detection
            )
            
            # Validate response
            self.client.validate_response(response, [
                'primary_intent', 'confidence', 'sentiment', 'urgency'
            ])
            
            # Validate intent is in allowed list
            if response['primary_intent'] not in self.VALID_INTENTS:
                logger.warning(f"Invalid intent detected: {response['primary_intent']}")
                response['primary_intent'] = 'information_seeking'  # Default fallback
            
            # Ensure confidence is in valid range
            response['confidence'] = max(0.0, min(1.0, response['confidence']))
            
            logger.info(
                f"Detected intent: {response['primary_intent']} "
                f"(confidence: {response['confidence']:.2f}, sentiment: {response['sentiment']})"
            )
            
            return response
            
        except Exception as e:
            logger.error(f"Intent detection failed: {str(e)}")
            # Return fallback response
            return {
                "primary_intent": "information_seeking",
                "confidence": 0.5,
                "secondary_intents": [],
                "sentiment": "neutral",
                "urgency": "medium",
                "suggested_response": "Manual review required",
                "error": str(e)
            }
    
    def batch_detect_intents(self, messages: list) -> list:
        """
        Detect intents for multiple messages in batch
        
        Args:
            messages: List of message dicts with 'text' and optional context
            
        Returns:
            List of intent detection results
        """
        results = []
        
        for msg in messages:
            try:
                intent_result = self.detect_intent(
                    message=msg.get('text', ''),
                    customer_stage=msg.get('customer_stage', 'prospect'),
                    interaction_count=msg.get('interaction_count', 0),
                    account_status=msg.get('account_status', 'active')
                )
                
                results.append({
                    'message_id': msg.get('id'),
                    **intent_result
                })
                
            except Exception as e:
                logger.error(f"Failed to detect intent for message {msg.get('id')}: {str(e)}")
                results.append({
                    'message_id': msg.get('id'),
                    'error': str(e)
                })
        
        return results
    
    def is_high_priority(self, intent_result: Dict[str, Any]) -> bool:
        """
        Determine if a message requires immediate attention
        
        Args:
            intent_result: Result from detect_intent()
            
        Returns:
            True if high priority
        """
        high_priority_intents = ['purchase_intent', 'complaint', 'churn_risk']
        
        is_urgent = intent_result.get('urgency') == 'high'
        is_negative = intent_result.get('sentiment') == 'negative'
        is_critical_intent = intent_result.get('primary_intent') in high_priority_intents
        
        return is_urgent or (is_negative and is_critical_intent)
    
    def route_message(self, intent_result: Dict[str, Any]) -> str:
        """
        Determine which team should handle the message
        
        Args:
            intent_result: Result from detect_intent()
            
        Returns:
            Team name (sales, support, success, product)
        """
        intent = intent_result.get('primary_intent')
        
        routing_map = {
            'purchase_intent': 'sales',
            'support_request': 'support',
            'information_seeking': 'sales',
            'complaint': 'support',
            'feature_request': 'product',
            'churn_risk': 'success'
        }
        
        return routing_map.get(intent, 'support')
    
    def get_suggested_actions(self, intent_result: Dict[str, Any]) -> list:
        """
        Get suggested actions based on detected intent
        
        Args:
            intent_result: Result from detect_intent()
            
        Returns:
            List of suggested action strings
        """
        intent = intent_result.get('primary_intent')
        urgency = intent_result.get('urgency')
        sentiment = intent_result.get('sentiment')
        
        actions = []
        
        # Intent-specific actions
        if intent == 'purchase_intent':
            actions.append("Send pricing information")
            actions.append("Schedule demo call")
            actions.append("Share case studies")
        elif intent == 'support_request':
            actions.append("Create support ticket")
            actions.append("Check knowledge base for solution")
            actions.append("Escalate if needed")
        elif intent == 'churn_risk':
            actions.append("Alert customer success manager")
            actions.append("Schedule retention call")
            actions.append("Offer incentive or discount")
        elif intent == 'complaint':
            actions.append("Acknowledge issue immediately")
            actions.append("Escalate to manager")
            actions.append("Provide compensation if appropriate")
        elif intent == 'feature_request':
            actions.append("Log in product roadmap")
            actions.append("Thank customer for feedback")
            actions.append("Share timeline if available")
        
        # Urgency-based actions
        if urgency == 'high':
            actions.insert(0, "Respond within 1 hour")
        
        # Sentiment-based actions
        if sentiment == 'negative':
            actions.insert(0, "Use empathetic tone in response")
        
        return actions


# Singleton instance
_intent_detector = None

def get_intent_detector() -> IntentDetectorClaude:
    """Get or create the singleton intent detector instance"""
    global _intent_detector
    if _intent_detector is None:
        _intent_detector = IntentDetectorClaude()
    return _intent_detector
