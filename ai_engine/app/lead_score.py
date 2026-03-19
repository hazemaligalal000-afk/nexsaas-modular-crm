"""
ai_engine/app/lead_score.py

FastAPI router for the Lead Scoring prediction endpoint.

POST /predict/lead-score
  Request:  { tenant_id: str, lead_id: int, features: dict }
  Response: { result: { score: int }, confidence: float, model_version: str }

Score is an integer in [0, 100].
Confidence is a float in [0.0, 1.0].

Requirements: 8.2, 35.1, 35.2, 35.4, 35.5
"""

from __future__ import annotations

import random
from typing import Any

import numpy as np
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field, field_validator

# ---------------------------------------------------------------------------
# Router
# ---------------------------------------------------------------------------

router = APIRouter()

MODEL_VERSION = "1.0.0"

# ---------------------------------------------------------------------------
# Schemas
# ---------------------------------------------------------------------------


class LeadScoreRequest(BaseModel):
    tenant_id: str = Field(..., description="Tenant UUID")
    lead_id: int = Field(..., description="Lead primary key")
    features: dict[str, Any] = Field(default_factory=dict, description="Demographic and behavioural signals")


class LeadScoreResult(BaseModel):
    score: int = Field(..., ge=0, le=100, description="Lead score 0–100")


class LeadScoreResponse(BaseModel):
    result: LeadScoreResult
    confidence: float = Field(..., ge=0.0, le=1.0, description="Model confidence 0.0–1.0")
    model_version: str = Field(..., description="Deployed model version string")


# ---------------------------------------------------------------------------
# Gradient-boosted model stub
# ---------------------------------------------------------------------------

def _build_feature_vector(features: dict[str, Any]) -> list[float]:
    """
    Extract a fixed-length numeric feature vector from the raw features dict.

    Supported keys (all optional, default to 0):
      - email_engagement   int   emails opened / clicked
      - days_in_stage      int   days in current pipeline stage
      - total_messages     int   total omnichannel messages
      - company_size       int   employee count
      - source_quality     int   1 = high-quality source (referral/organic/linkedin), else 0
    """
    return [
        float(features.get("email_engagement", 0)),
        float(features.get("days_in_stage", 0)),
        float(features.get("total_messages", 0)),
        float(features.get("company_size", 0)),
        float(features.get("source_quality", 0)),
    ]


def _predict_score(features: dict[str, Any]) -> tuple[int, float]:
    """
    Gradient-boosted model stub.

    In production this would load a persisted sklearn GradientBoostingClassifier
    (or XGBoost/LightGBM) from disk.  For now we use a deterministic weighted
    formula that mirrors the feature importance a real model would learn, plus
    a small random jitter to simulate model variance.

    Returns:
        (score: int, confidence: float)  — score clamped to [0, 100].
    """
    try:
        from sklearn.ensemble import GradientBoostingClassifier  # noqa: F401
        # Stub: use the weighted formula below (no trained weights yet)
    except ImportError:
        pass

    fv = _build_feature_vector(features)
    email_eng, days_in_stage, total_msgs, company_size, source_quality = fv

    # Weighted linear combination (mimics gradient-boosted feature importances)
    raw = (
        50.0                                    # base
        + min(email_eng, 10) * 2.0             # engagement signal (cap at 10)
        + min(total_msgs, 20) * 0.5            # omnichannel activity
        + min(company_size / 50.0, 4.0) * 3.0 # company size (cap contribution)
        + source_quality * 10.0                # high-quality source bonus
        - min(days_in_stage / 7.0, 5.0) * 3.0 # recency penalty
    )

    # Small deterministic jitter based on lead_id (reproducible per lead)
    jitter = (hash(str(features.get("lead_id", 0))) % 11) - 5  # [-5, +5]
    raw += jitter

    score = int(round(max(0.0, min(100.0, raw))))

    # Confidence: higher when features are richer
    non_zero = sum(1 for v in fv if v != 0.0)
    confidence = round(0.50 + non_zero * 0.08 + random.uniform(-0.02, 0.02), 4)
    confidence = max(0.0, min(1.0, confidence))

    return score, confidence


# ---------------------------------------------------------------------------
# Endpoint
# ---------------------------------------------------------------------------


@router.post("/predict/lead-score", response_model=LeadScoreResponse)
def predict_lead_score(request: LeadScoreRequest) -> LeadScoreResponse:
    """
    Compute a Lead Score for the given lead.

    - Score is an integer in [0, 100] (validated and clamped).
    - Confidence is a float in [0.0, 1.0].
    - model_version is always "1.0.0" for this stub.

    Requirements: 8.2, 35.1, 35.2, 35.3, 35.4, 35.5
    """
    # Req 35.3: reject requests with missing or blank tenant_id
    if not request.tenant_id or not request.tenant_id.strip():
        raise HTTPException(status_code=400, detail="tenant_id is required and must not be blank")

    # Inject lead_id into features so the jitter is reproducible per lead
    features_with_id = {**request.features, "lead_id": request.lead_id}

    raw_score, confidence = _predict_score(features_with_id)

    # Clamp and cast — belt-and-suspenders even though _predict_score already clamps
    score = max(0, min(100, int(raw_score)))

    return LeadScoreResponse(
        result=LeadScoreResult(score=score),
        confidence=confidence,
        model_version=MODEL_VERSION,
    )
