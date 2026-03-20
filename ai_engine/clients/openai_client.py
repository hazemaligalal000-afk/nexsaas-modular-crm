import os
import time
import structlog
import httpx
from typing import Dict, Any, Optional

logger = structlog.get_logger()

# 1. Configuration (In production, read from env)
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY", "sk-mock-key-001")
OPENAI_URL = "https://api.openai.com/v1/chat/completions"
TIMEOUT_SECONDS = 10.0
MAX_RETRIES = 3
BACKOFF_BASE = 1.0

class OpenAIClient:
    def __init__(self, api_key: str = OPENAI_API_KEY):
        self._api_key = api_key
        self._http_client = httpx.AsyncClient(timeout=TIMEOUT_SECONDS)

    async def chat_complete(self, messages: list, model: str = "gpt-4-turbo-preview", temperature: float = 0.7) -> Dict[str, Any]:
        last_exception = None
        
        for attempt in range(MAX_RETRIES):
            try:
                # 1. Exponential Backoff (skip for first attempt)
                if attempt > 0:
                    delay = BACKOFF_BASE * (2 ** (attempt - 1))
                    time.sleep(delay)
                    logger.info("openai_retry", attempt=attempt+1, delay=delay)

                # 2. Make Request
                response = await self._http_client.post(
                    OPENAI_URL,
                    headers={
                        "Authorization": f"Bearer {self._api_key}",
                        "Content-Type": "application/json"
                    },
                    json={
                        "model": model,
                        "messages": messages,
                        "temperature": temperature,
                        "max_tokens": 800
                    }
                )

                # 3. Handle Transient Errors (Retry-able)
                if response.status_code in [429, 500, 502, 503, 504]:
                    response.raise_for_status() # Trigger except block

                # 4. Success Case
                return response.json()

            except (httpx.RequestError, httpx.HTTPStatusError) as e:
                last_exception = e
                logger.warning("openai_attempt_failed", error=str(e), attempt=attempt+1)
                
                # Check if non-retryable (e.g., 401, 400)
                if isinstance(e, httpx.HTTPStatusError) and e.response.status_code in [400, 401, 403, 404]:
                    break

        # 5. Final Failure Case
        logger.error("openai_retries_exhausted", error=str(last_exception))
        raise last_exception or Exception("OpenAI Request Failed")

    async def close(self):
        await self._http_client.aclose()
