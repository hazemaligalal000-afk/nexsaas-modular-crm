"""
ai_engine/app/win_probability.py

FastAPI router for the Deal Win Probability prediction endpoint.

POST /predict/win-probability
  Request:  { tenant_id: str, deal_id: int, features: dict }
  Response: { result: { probability: float }, confidence: float, model_version: str }

probability must be in [0.0, 1.0].
confidence is a float in [0.0, 1.0].

Requirements: 11.2, 35.1, 35.2
"""

from __future__ import annotations

import random
from typing import Any

from fastapi import APIRouter
from pydantic import BaseModel, Field

# ---------------------------------------------------------------------------
# Router
# ---------------------------------------------------------------------------

router = APIRouter()

MODEL_VERSION = "1.0.0"

# ---------------------------------------------------------------------------
# Schemas
# ---------------------------------------------------------------------------


class WinProbabilityRequest(BaseModel):
    tenant_id: str = Field(..., description="Tenant UUID")
    deal_id: int = Field(..., description="Deal primary key")
    features: dict[str, Any] = Field(
        default_factory=dict,
        description="Deal features: stage_position, value, age_days, historical_win_rate, etc.",
    )


class WinProbabilityResult(BaseModel):
    probability: float = Field(..., ge=0.0, le=1.0, description="Win probability 0.0–1.0")


class WinProbabilityResponse(BaseModel):
    result: WinProbabilityResult
    confidence: float = Field(..., ge=0.0, le=1.0, description="Model confidence 0.0–1.0")
    model_version: str = Field(..., description="Deployed model version string")


# ---------------------------------------------------------------------------
# Logistic regression stub
# ---------------------------------------------------------------------------

def _sigmoid(x: float) -> float:
    """Numerically stable sigmoid."""
    if x >= 0:
        import math
        return 1.0 / (1.0 + math.exp(-x))
    else:
        import math
        exp_x = math.exp(x)
        return exp_x / (1.0 + exp_x)


def _build_feature_vector(features: dict[str, Any]) -> list[float]:
    """
    Extract a fixed-length numeric feature vector from the raw features dict.

    Supported keys (all optional, default to 0):
      - stage_position      int    position of current stage (higher = closer to close)
      - value               float  deal monetary value
      - age_days            int    days since deal was created
      - historical_win_rate float  tenant-level historical win rate [0.0, 1.0]
      - activity_count      int    number of activities logged on this deal
    """
    return [
        float(features.get("stage_position", 0)),
        float(features.get("value", 0)),
        float(features.get("age_days", 0)),
        float(features.get("historical_win_rate", 0.0)),
        float(features.get("activity_count", 0)),
    ]


def _predict_probability(features: dict[str, Any], deal_id: int) -> tuple[float, float]:
    """
    Logistic regression stub.

    In production this would load a persisted sklearn LogisticRegression model.
    For now we use a deterministic weighted linear combination passed through
    sigmoid, plus a small jitter to simulate model variance.

    Returns:
        (probability: float, confidence: float)  — both clamped to [0.0, 1.0].
    """
    fv = _build_feature_vector(features)
    stage_pos, value, age_days, hist_win_rate, activity_count = fv

    # Weighted linear combination (logistic regression weights stub)
    linear = (
        -1.0                                        # bias
        + stage_pos * 0.4                           # later stage → higher probability
        + min(value / 50000.0, 1.0) * 0.3          # larger deal (capped)
        - min(age_days / 90.0, 1.0) * 0.2          # older deal → slight penalty
        + hist_win_rate * 1.5                       # historical win rate is strong signal
        + min(activity_count / 10.0, 1.0) * 0.3    # engagement signal
    )

    # Small deterministic jitter based on deal_id
    jitter = ((deal_id % 11) - 5) * 0.01  # [-0.05, +0.05]
    linear += jitter

    probability = _sigmoid(linear)
    probability = max(0.0, min(1.0, round(probability, 6)))

    # Confidence: higher when more features are provided
    non_zero = sum(1 for v in fv if v != 0.0)
    confidence = round(0.45 + non_zero * 0.09 + random.uniform(-0.02, 0.02), 4)
    confidence = max(0.0, min(1.0, confidence))

    return probability, confidence


# ---------------------------------------------------------------------------
# Endpoint
# ---------------------------------------------------------------------------


@router.post("/predict/win-probability", response_model=WinProbabilityResponse)
def predict_win_probability(request: WinProbabilityRequest) -> WinProbabilityResponse:
    """
    Predict the win probability for a deal.

    - probability is a float in [0.0, 1.0] (validated and clamped).
    - confidence is a float in [0.0, 1.0].
    - model_version is always "1.0.0" for this stub.

    Requirements: 11.2, 35.1, 35.2
    """
    features_with_id = {**request.features, "deal_id": request.deal_id}

    raw_probability, confidence = _predict_probability(features_with_id, request.deal_id)

    # Belt-and-suspenders clamp
    probability = max(0.0, min(1.0, raw_probability))

    return WinProbabilityResponse(
        result=WinProbabilityResult(probability=probability),
        confidence=confidence,
        model_version=MODEL_VERSION,
    )
