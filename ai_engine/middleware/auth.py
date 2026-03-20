import time
import structlog
from jose import jwt, JWTError
from fastapi import Request, HTTPException
from fastapi.responses import JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware
from typing import Optional

logger = structlog.get_logger()

# 1. Configuration (In production, read from env)
SECRET_KEY = "nexsaas-platform-secret-key-001"
ALGORITHM = "HS256"
ISSUER = "nexsaas-platform"
AUDIENCE = "ai-engine"

class JWTAuthMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        # 1. Skip paths (Health, Public Docs)
        if request.url.path in ["/health", "/docs", "/openapi.json"]:
            return await call_next(request)

        # 2. Extract Token from Authorization header
        auth_header = request.headers.get("Authorization")
        if not auth_header or not auth_header.startswith("Bearer "):
            return self._error_response("Missing Authorization Header", 401)

        token = auth_header.split(" ")[1]

        # 3. Validate Token
        try:
            payload = jwt.decode(
                token, 
                SECRET_KEY, 
                algorithms=[ALGORITHM], 
                audience=AUDIENCE, 
                issuer=ISSUER
            )
            
            tenant_id: str = payload.get("tenant_id")
            user_id: str = payload.get("user_id")

            if not tenant_id:
                return self._error_response("Token missing tenant_id claim", 401)

            # 4. Attach to Request State for downstream services
            request.state.tenant_id = tenant_id
            request.state.user_id = user_id
            
            # Additional Context for structured logging
            structlog.contextvars.bind_contextvars(tenant_id=tenant_id, user_id=user_id)

        except JWTError as e:
            logger.warning("auth_failed", error=str(e), path=request.url.path)
            return self._error_response(f"Invalid Token: {str(e)}", 401)

        return await call_next(request)

    def _error_response(self, detail: str, status_code: int):
        return JSONResponse(
            status_code=status_code,
            content={"success": False, "error": {"code": status_code, "message": detail}}
        )
