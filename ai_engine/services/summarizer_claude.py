"""
Conversation Summarizer using Claude API
Summarizes conversations with action items
Requirements: Master Spec - AI Conversation Summarization
"""

from typing import Dict, Any, List, Optional
import logging
from .claude_client import get_claude_client
from ..models.prompts import (
    SUMMARIZER_SYSTEM,
    SUMMARIZER_PROMPT,
    format_prompt
)

logger = logging.getLogger(__name__)


class SummarizerClaude:
    """
    Conversation summarization service powered by Claude AI
    Creates actionable summaries with key points and next steps
    """
    
    def __init__(self):
        self.client = get_claude_client()
    
    def summarize_conversation(
        self,
        conversation_text: str,
        participants: List[str],
        conversation_type: str = "meeting",
        duration: Optional[str] = None,
        date: Optional[str] = None
    ) -> Dict[str, Any]:
        """
        Summarize a conversation
        
        Args:
            conversation_text: Full conversation transcript
            participants: List of participant names
            conversation_type: Type (meeting, email_thread, chat, call)
            duration: Duration (e.g., "30 minutes")
            date: Date of conversation (YYYY-MM-DD)
            
        Returns:
            Dict with summary, key_points, decisions, action_items, next_steps
        """
        try:
            # Format participants list
            participants_str = ", ".join(participants)
            
            # Format the prompt
            prompt = format_prompt(
                SUMMARIZER_PROMPT,
                conversation_text=conversation_text,
                participants=participants_str,
                conversation_type=conversation_type,
                duration=duration or "Unknown",
                date=date or "Unknown"
            )
            
            # Call Claude API
            response = self.client.generate_json(
                prompt=prompt,
                system_prompt=SUMMARIZER_SYSTEM,
                temperature=0.5  # Moderate temperature for summarization
            )
            
            # Validate response
            self.client.validate_response(response, [
                'summary', 'key_points', 'action_items', 'next_steps'
            ])
            
            logger.info(f"Summarized {conversation_type} with {len(participants)} participants")
            
            return response
            
        except Exception as e:
            logger.error(f"Conversation summarization failed: {str(e)}")
            # Return fallback summary
            return {
                "summary": "Unable to generate summary due to AI error",
                "key_points": [],
                "decisions": [],
                "action_items": [],
                "next_steps": ["Manual review required"],
                "sentiment": "neutral",
                "topics": [],
                "error": str(e)
            }
    
    def summarize_email_thread(
        self,
        emails: List[Dict[str, Any]]
    ) -> Dict[str, Any]:
        """
        Summarize an email thread
        
        Args:
            emails: List of email dicts with 'from', 'to', 'subject', 'body', 'date'
            
        Returns:
            Summary of the email thread
        """
        # Format email thread as conversation
        conversation_text = ""
        participants = set()
        
        for email in emails:
            sender = email.get('from', 'Unknown')
            body = email.get('body', '')
            date = email.get('date', '')
            
            participants.add(sender)
            conversation_text += f"\n[{date}] {sender}:\n{body}\n"
        
        return self.summarize_conversation(
            conversation_text=conversation_text.strip(),
            participants=list(participants),
            conversation_type="email_thread",
            date=emails[0].get('date') if emails else None
        )
    
    def summarize_meeting(
        self,
        transcript: str,
        attendees: List[str],
        duration: str,
        date: str
    ) -> Dict[str, Any]:
        """
        Summarize a meeting
        
        Args:
            transcript: Meeting transcript
            attendees: List of attendee names
            duration: Meeting duration
            date: Meeting date
            
        Returns:
            Meeting summary with action items
        """
        return self.summarize_conversation(
            conversation_text=transcript,
            participants=attendees,
            conversation_type="meeting",
            duration=duration,
            date=date
        )
    
    def summarize_support_ticket(
        self,
        messages: List[Dict[str, Any]]
    ) -> Dict[str, Any]:
        """
        Summarize a support ticket conversation
        
        Args:
            messages: List of message dicts with 'sender', 'text', 'timestamp'
            
        Returns:
            Support ticket summary
        """
        # Format messages as conversation
        conversation_text = ""
        participants = set()
        
        for msg in messages:
            sender = msg.get('sender', 'Unknown')
            text = msg.get('text', '')
            timestamp = msg.get('timestamp', '')
            
            participants.add(sender)
            conversation_text += f"\n[{timestamp}] {sender}:\n{text}\n"
        
        return self.summarize_conversation(
            conversation_text=conversation_text.strip(),
            participants=list(participants),
            conversation_type="support_ticket",
            date=messages[0].get('timestamp') if messages else None
        )
    
    def extract_action_items(self, summary_result: Dict[str, Any]) -> List[Dict[str, Any]]:
        """
        Extract just the action items from a summary
        
        Args:
            summary_result: Result from summarize_conversation()
            
        Returns:
            List of action items
        """
        return summary_result.get('action_items', [])
    
    def format_summary_for_email(self, summary_result: Dict[str, Any]) -> str:
        """
        Format summary as email-friendly text
        
        Args:
            summary_result: Result from summarize_conversation()
            
        Returns:
            Formatted summary text
        """
        summary = summary_result.get('summary', '')
        key_points = summary_result.get('key_points', [])
        decisions = summary_result.get('decisions', [])
        action_items = summary_result.get('action_items', [])
        next_steps = summary_result.get('next_steps', [])
        
        formatted = f"Summary:\n{summary}\n\n"
        
        if key_points:
            formatted += "Key Points:\n"
            for point in key_points:
                formatted += f"• {point}\n"
            formatted += "\n"
        
        if decisions:
            formatted += "Decisions Made:\n"
            for decision in decisions:
                formatted += f"• {decision}\n"
            formatted += "\n"
        
        if action_items:
            formatted += "Action Items:\n"
            for item in action_items:
                task = item.get('task', '')
                owner = item.get('owner', 'Unassigned')
                due_date = item.get('due_date', 'No deadline')
                formatted += f"• {task} (Owner: {owner}, Due: {due_date})\n"
            formatted += "\n"
        
        if next_steps:
            formatted += "Next Steps:\n"
            for step in next_steps:
                formatted += f"• {step}\n"
        
        return formatted.strip()
    
    def batch_summarize(self, conversations: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """
        Summarize multiple conversations in batch
        
        Args:
            conversations: List of conversation dicts
            
        Returns:
            List of summaries
        """
        results = []
        
        for conv in conversations:
            try:
                summary = self.summarize_conversation(
                    conversation_text=conv.get('text', ''),
                    participants=conv.get('participants', []),
                    conversation_type=conv.get('type', 'meeting'),
                    duration=conv.get('duration'),
                    date=conv.get('date')
                )
                
                results.append({
                    'conversation_id': conv.get('id'),
                    **summary
                })
                
            except Exception as e:
                logger.error(f"Failed to summarize conversation {conv.get('id')}: {str(e)}")
                results.append({
                    'conversation_id': conv.get('id'),
                    'error': str(e)
                })
        
        return results


# Singleton instance
_summarizer = None

def get_summarizer() -> SummarizerClaude:
    """Get or create the singleton summarizer instance"""
    global _summarizer
    if _summarizer is None:
        _summarizer = SummarizerClaude()
    return _summarizer
