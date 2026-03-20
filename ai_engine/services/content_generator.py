import structlog
from ai_engine.clients.openai_client import OpenAIClient
from ai_engine.models.schemas import ContentGenerationRequest, ContentGenerationResponse, TonePreset
from typing import List, Dict

logger = structlog.get_logger()

class ContentGenerator:
    def __init__(self):
        self._client = OpenAIClient()

    async def generate_reply(self, request: ContentGenerationRequest) -> ContentGenerationResponse:
        # 1. System Prompt with Tone Injection
        tone_instructions = {
            TonePreset.PROFESSIONAL: "Keep the message formal, expert, and authoritative but helpful.",
            TonePreset.FRIENDLY: "Use a warm, welcoming, and personal tone. Use exclamation marks where appropriate.",
            TonePreset.CONCISE: "Be extremely brief and to-the-point. Avoid fluff or extended greetings."
        }

        system_prompt = (
            "You are a Senior Strategic Advisor within the NexSaaS CRM ecosystem. "
            "Draft a reply to the client's message. "
            f"Tone Requirement: {tone_instructions.get(request.tone, 'Professional')}. "
            "Use context from the conversation history provided. Respond ONLY with the draft message."
        )

        # 2. Format Messages for OpenAI
        messages = [{"role": "system", "content": system_prompt}]
        for msg in request.conversation_history[-5:]: # Last 5 messages for context
            messages.append({"role": msg['role'], "content": msg['content']})
        
        # Add the current trigger message
        messages.append({"role": "user", "content": request.message_text})

        # 3. Call OpenAI
        try:
            response = await self._client.chat_complete(
                messages=messages,
                model="gpt-4-turbo-preview",
                temperature=0.7 # Higher temperature for creative drafting
            )

            draft_text = response['choices'][0]['message']['content'].strip()
            
            return ContentGenerationResponse(
                draft_text=draft_text,
                confidence=0.95
            )

        except Exception as e:
            logger.error("content_generation_failed", error=str(e))
            raise e

    async def close(self):
        await self._client.close()
