import os
import time
import structlog
import httpx
from typing import Dict, Any, List

logger = structlog.get_logger()

# 1. Configuration (In production, read from env)
ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY", "sk-ant-mock-key-001")
ANTHROPIC_URL = "https://api.anthropic.com/v1/messages"
ANTHROPIC_VERSION = "2023-06-01"
TIMEOUT_SECONDS = 10.0
MAX_RETRIES = 3
BACKOFF_BASE = 1.0

class AnthropicClient:
    def __init__(self, api_key: str = ANTHROPIC_API_KEY):
        self._api_key = api_key
        self._http_client = httpx.AsyncClient(timeout=TIMEOUT_SECONDS)

    async def chat_complete(self, messages: List[Dict[str, str]], system: str = "", model: str = "claude-3-sonnet-20240229", temperature: float = 0.1) -> Dict[str, Any]:
        last_exception = None
        
        for attempt in range(MAX_RETRIES):
            try:
                # 1. Exponential Backoff (skip for first attempt)
                if attempt > 0:
                    delay = BACKOFF_BASE * (2 ** (attempt - 1))
                    time.sleep(delay)
                    logger.info("anthropic_retry", attempt=attempt+1, delay=delay)

                # 2. Make Request
                response = await self._http_client.post(
                    ANTHROPIC_URL,
                    headers={
                        "x-api-key": self._api_key,
                        "anthropic-version": ANTHROPIC_VERSION,
                        "Content-Type": "application/json"
                    },
                    json={
                        "model": model,
                        "system": system,
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
                logger.warning("anthropic_failed", error=str(e), attempt=attempt+1)
                
                # Check if non-retryable (e.g., 401, 400)
                if isinstance(e, httpx.HTTPStatusError) and e.response.status_code in [400, 401, 403, 404]:
                    break

        # 5. Final Failure Case
        logger.error("anthropic_exhausted", error=str(last_exception))
        raise last_exception or Exception("Anthropic Request Failed")

    async def close(self):
        await self._http_client.aclose()
