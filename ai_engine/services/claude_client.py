"""
Claude API Client Wrapper
Centralized client for all Claude API interactions
Requirements: Master Spec - AI Engine with Claude API
"""

import os
from typing import Dict, Any, Optional
from anthropic import Anthropic, AsyncAnthropic
import json
import logging

logger = logging.getLogger(__name__)


class ClaudeClient:
    """
    Wrapper for Anthropic Claude API
    Handles all interactions with Claude Sonnet 4
    """
    
    def __init__(self):
        """Initialize Claude client with API key from environment"""
        api_key = os.getenv('ANTHROPIC_API_KEY')
        if not api_key:
            raise ValueError("ANTHROPIC_API_KEY environment variable is required")
        
        self.client = Anthropic(api_key=api_key)
        self.async_client = AsyncAnthropic(api_key=api_key)
        self.model = "claude-sonnet-4-20250514"  # As specified in master spec
        self.max_tokens = 4096
        
    def generate(
        self,
        prompt: str,
        system_prompt: Optional[str] = None,
        temperature: float = 0.7,
        max_tokens: Optional[int] = None
    ) -> Dict[str, Any]:
        """
        Generate a response from Claude
        
        Args:
            prompt: The user prompt
            system_prompt: Optional system prompt for context
            temperature: Sampling temperature (0-1)
            max_tokens: Maximum tokens to generate
            
        Returns:
            Dict with 'content' and 'usage' keys
        """
        try:
            messages = [{"role": "user", "content": prompt}]
            
            kwargs = {
                "model": self.model,
                "max_tokens": max_tokens or self.max_tokens,
                "temperature": temperature,
                "messages": messages
            }
            
            if system_prompt:
                kwargs["system"] = system_prompt
            
            response = self.client.messages.create(**kwargs)
            
            return {
                "content": response.content[0].text,
                "usage": {
                    "input_tokens": response.usage.input_tokens,
                    "output_tokens": response.usage.output_tokens
                },
                "model": response.model,
                "stop_reason": response.stop_reason
            }
            
        except Exception as e:
            logger.error(f"Claude API error: {str(e)}")
            raise
    
    def generate_json(
        self,
        prompt: str,
        system_prompt: Optional[str] = None,
        temperature: float = 0.7
    ) -> Dict[str, Any]:
        """
        Generate a JSON response from Claude
        Automatically parses the response as JSON
        
        Args:
            prompt: The user prompt (should request JSON output)
            system_prompt: Optional system prompt
            temperature: Sampling temperature
            
        Returns:
            Parsed JSON dict
        """
        response = self.generate(
            prompt=prompt,
            system_prompt=system_prompt,
            temperature=temperature
        )
        
        content = response["content"].strip()
        
        # Extract JSON from markdown code blocks if present
        if content.startswith("```json"):
            content = content.split("```json")[1].split("```")[0].strip()
        elif content.startswith("```"):
            content = content.split("```")[1].split("```")[0].strip()
        
        try:
            return json.loads(content)
        except json.JSONDecodeError as e:
            logger.error(f"Failed to parse JSON from Claude response: {content}")
            raise ValueError(f"Claude did not return valid JSON: {str(e)}")
    
    async def generate_async(
        self,
        prompt: str,
        system_prompt: Optional[str] = None,
        temperature: float = 0.7,
        max_tokens: Optional[int] = None
    ) -> Dict[str, Any]:
        """
        Async version of generate()
        """
        try:
            messages = [{"role": "user", "content": prompt}]
            
            kwargs = {
                "model": self.model,
                "max_tokens": max_tokens or self.max_tokens,
                "temperature": temperature,
                "messages": messages
            }
            
            if system_prompt:
                kwargs["system"] = system_prompt
            
            response = await self.async_client.messages.create(**kwargs)
            
            return {
                "content": response.content[0].text,
                "usage": {
                    "input_tokens": response.usage.input_tokens,
                    "output_tokens": response.usage.output_tokens
                },
                "model": response.model,
                "stop_reason": response.stop_reason
            }
            
        except Exception as e:
            logger.error(f"Claude API error (async): {str(e)}")
            raise
    
    def count_tokens(self, text: str) -> int:
        """
        Estimate token count for text
        Claude uses ~4 characters per token on average
        """
        return len(text) // 4
    
    def validate_response(self, response: Dict[str, Any], required_keys: list) -> bool:
        """
        Validate that a JSON response contains required keys
        
        Args:
            response: The parsed JSON response
            required_keys: List of required key names
            
        Returns:
            True if valid, raises ValueError if not
        """
        missing_keys = [key for key in required_keys if key not in response]
        if missing_keys:
            raise ValueError(f"Claude response missing required keys: {missing_keys}")
        return True


# Singleton instance
_claude_client = None

def get_claude_client() -> ClaudeClient:
    """Get or create the singleton Claude client instance"""
    global _claude_client
    if _claude_client is None:
        _claude_client = ClaudeClient()
    return _claude_client
