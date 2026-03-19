"""
AI Revenue Operating System — Python AI Microservice
FastAPI application for Lead Scoring, Intent Detection, and AI Content Generation.
All endpoints are tenant-aware.
"""

from fastapi import FastAPI, HTTPException, Header
from pydantic import BaseModel, Field
from typing import List, Optional
import datetime
import hashlib
import json

from app.lead_score import router as lead_score_router
from app.win_probability import router as win_probability_router
from app.accounting_ai import router as accounting_router
from app.churn import router as churn_router
from app.sentiment import router as sentiment_router
from app.embeddings import router as embeddings_router
from app.communications import router as communications_router
from app.forecast import router as forecast_router

app = FastAPI(
    title="AI Revenue OS — Intelligence Engine",
    version="2.0.1",
    description="Enterprise AI Microservice: Hub for predictive intelligence and decision support."
)

app.include_router(lead_score_router)
app.include_router(win_probability_router)
app.include_router(accounting_router)
app.include_router(churn_router)
app.include_router(sentiment_router)
app.include_router(embeddings_router)
app.include_router(communications_router)
app.include_router(forecast_router)


@app.get("/health")
def health():
    return {"status": "ok", "service": "AI Revenue OS Intelligence Engine", "version": "2.0.1"}

# Inline tasks for lead scoring removed; moved to routers/background workers as designed.
