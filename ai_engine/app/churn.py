from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from typing import Dict, List, Optional
import random

router = APIRouter(prefix="/predict", tags=["Churn Prediction"])

class ChurnRequest(BaseModel):
    tenant_id: str
    account_id: int
    engagement_index: float
    support_volume: int
    renewal_proximity_days: int

class ChurnResponse(BaseModel):
    churn_score: float
    risk_tier: str
    confidence: float
    model_version: str

@router.post("/churn", response_model=ChurnResponse)
async def predict_churn(req: ChurnRequest):
    """
    Task 51.1: Churn Prediction (Survival analysis mockup)
    Requirements: 36.1, 36.2, 36.3
    """
    if not req.tenant_id:
        raise HTTPException(status_code=400, detail="Missing tenant_id")

    # Weighted risk logic
    score = 50.0 # Base
    score -= req.engagement_index * 10
    score += req.support_volume * 5
    if req.renewal_proximity_days < 30: score += 15
    
    churn_score = max(0, min(100, score))
    
    if churn_score >= 67: tier = "high"
    elif churn_score >= 34: tier = "medium"
    else: tier = "low"
    
    return ChurnResponse(
        churn_score=round(churn_score, 2),
        risk_tier=tier,
        confidence=0.88,
        model_version="churn-survival-v1.0"
    )
