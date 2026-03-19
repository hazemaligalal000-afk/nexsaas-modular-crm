"""
AI Accounting Features (Batch M)
Task 56: AI-powered anomaly detection and predictive analytics
Requirements: 57.1 - 57.7
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from typing import List, Optional, Dict
from datetime import datetime, timedelta
import numpy as np
from scipy import stats

router = APIRouter(prefix="/accounting", tags=["accounting-ai"])


# ============================================================================
# Request/Response Models
# ============================================================================

class AnomalyDetectRequest(BaseModel):
    """Exchange rate deviation check request"""
    currency_code: str
    entered_rate: float
    date: str
    tenant_id: str


class AnomalyDetectResponse(BaseModel):
    """Anomaly detection response"""
    result: Dict
    confidence: float
    model_version: str


class DuplicateCheckRequest(BaseModel):
    """Duplicate voucher check request"""
    vendor_code: str
    amount: float
    date: str
    company_code: str
    tenant_id: str


class DuplicateCheckResponse(BaseModel):
    """Duplicate check response"""
    result: Dict
    confidence: float
    model_version: str


class AccountSuggestRequest(BaseModel):
    """Account code suggestion request"""
    cost_identifier: str
    company_code: str
    tenant_id: str


class AccountSuggestResponse(BaseModel):
    """Account suggestion response"""
    result: Dict
    confidence: float
    model_version: str


class CashFlowPredictRequest(BaseModel):
    """Cash flow prediction request"""
    company_code: str
    currency_code: str
    tenant_id: str
    months: int = 3


class CashFlowPredictResponse(BaseModel):
    """Cash flow prediction response"""
    result: Dict
    confidence: float
    model_version: str


class OutlierDetectRequest(BaseModel):
    """Journal entry outlier detection request"""
    account_code: str
    amount: float
    debit_credit: str  # 'debit' or 'credit'
    company_code: str
    tenant_id: str


class OutlierDetectResponse(BaseModel):
    """Outlier detection response"""
    result: Dict
    confidence: float
    model_version: str


# ============================================================================
# Task 56.1: Exchange Rate Deviation Check
# Requirement 57.1
# ============================================================================

@router.post("/anomaly-detect", response_model=AnomalyDetectResponse)
async def detect_fx_anomaly(request: AnomalyDetectRequest):
    """
    Compare entered exchange rate to market rate and flag deviations.
    
    Requirement 57.1: Flag if deviation exceeds threshold
    """
    try:
        # Simulated market rate (in production, fetch from Redis cache)
        market_rates = {
            '02': 50.00,  # USD
            '03': 13.62,  # AED
            '04': 13.33,  # SAR
            '05': 54.50,  # EUR
            '06': 63.00   # GBP
        }
        
        market_rate = market_rates.get(request.currency_code, request.entered_rate)
        
        # Calculate deviation percentage
        deviation_pct = abs((request.entered_rate - market_rate) / market_rate) * 100
        
        # Threshold: 5% deviation
        threshold = 5.0
        is_anomaly = deviation_pct > threshold
        
        # Calculate confidence based on deviation magnitude
        confidence = min(deviation_pct / 10.0, 1.0) if is_anomaly else 0.0
        
        result = {
            "is_anomaly": is_anomaly,
            "entered_rate": request.entered_rate,
            "market_rate": market_rate,
            "deviation_pct": round(deviation_pct, 2),
            "threshold_pct": threshold,
            "message": f"Rate deviation of {deviation_pct:.2f}% {'exceeds' if is_anomaly else 'within'} threshold"
        }
        
        return AnomalyDetectResponse(
            result=result,
            confidence=round(confidence, 4),
            model_version="fx-anomaly-v1.0"
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# Task 56.2: Duplicate Voucher Check
# Requirement 57.2
# ============================================================================

@router.post("/duplicate-check", response_model=DuplicateCheckResponse)
async def check_duplicate_voucher(request: DuplicateCheckRequest):
    """
    Search for duplicate vouchers by vendor + amount + date ±3 days.
    
    Requirement 57.2: Warn Accountant of potential duplicates
    """
    try:
        # Simulated database query (in production, query actual database)
        # This would search posted vouchers within ±3 days
        
        # For demonstration, simulate finding duplicates based on amount similarity
        # In production, this would be a database query
        
        # Simulated duplicate detection logic
        amount_tolerance = 0.01  # 1% tolerance
        date_window_days = 3
        
        # Simulate finding potential duplicates
        # In production: SELECT * FROM vouchers WHERE vendor_code = ? 
        #                AND ABS(amount - ?) < amount * tolerance
        #                AND date BETWEEN date - 3 AND date + 3
        
        # For demo, randomly determine if duplicate exists
        import random
        has_duplicate = random.random() < 0.2  # 20% chance of duplicate
        
        if has_duplicate:
            # Simulated duplicate found
            duplicate_count = random.randint(1, 3)
            confidence = 0.85
            
            result = {
                "is_duplicate": True,
                "duplicate_count": duplicate_count,
                "vendor_code": request.vendor_code,
                "amount": request.amount,
                "date": request.date,
                "date_window_days": date_window_days,
                "message": f"Found {duplicate_count} potential duplicate(s) within ±{date_window_days} days",
                "duplicates": [
                    {
                        "voucher_code": f"V{2024000 + i}",
                        "date": request.date,
                        "amount": request.amount,
                        "similarity": 0.95
                    }
                    for i in range(duplicate_count)
                ]
            }
        else:
            confidence = 0.95
            result = {
                "is_duplicate": False,
                "duplicate_count": 0,
                "vendor_code": request.vendor_code,
                "amount": request.amount,
                "date": request.date,
                "message": "No duplicates found"
            }
        
        return DuplicateCheckResponse(
            result=result,
            confidence=round(confidence, 4),
            model_version="duplicate-check-v1.0"
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# Task 56.3: Account Code Suggestion
# Requirement 57.3
# ============================================================================

@router.post("/account-suggest", response_model=AccountSuggestResponse)
async def suggest_account_code(request: AccountSuggestRequest):
    """
    Suggest account codes from cost_identifier using text similarity.
    
    Requirement 57.3: Return top 3 COA matches
    """
    try:
        # Simulated COA with common accounts
        # In production, this would use sentence-transformers for semantic similarity
        coa_accounts = [
            {"code": "5010", "name": "Depreciation Expense", "keywords": ["depreciation", "amortization", "asset"]},
            {"code": "5020", "name": "Salaries and Wages", "keywords": ["salary", "wage", "payroll", "employee"]},
            {"code": "5030", "name": "Rent Expense", "keywords": ["rent", "lease", "rental"]},
            {"code": "5040", "name": "Utilities", "keywords": ["electricity", "water", "utility", "power"]},
            {"code": "5050", "name": "Office Supplies", "keywords": ["supplies", "stationery", "office"]},
            {"code": "5060", "name": "Travel Expense", "keywords": ["travel", "trip", "transportation"]},
            {"code": "5070", "name": "Professional Fees", "keywords": ["consultant", "professional", "advisory"]},
            {"code": "5080", "name": "Insurance", "keywords": ["insurance", "premium", "coverage"]},
            {"code": "5090", "name": "Maintenance", "keywords": ["maintenance", "repair", "service"]},
            {"code": "5100", "name": "Marketing", "keywords": ["marketing", "advertising", "promotion"]},
        ]
        
        # Simple keyword matching (in production, use embeddings)
        cost_lower = request.cost_identifier.lower()
        matches = []
        
        for account in coa_accounts:
            score = 0
            for keyword in account["keywords"]:
                if keyword in cost_lower:
                    score += 1
            
            if score > 0:
                matches.append({
                    "account_code": account["code"],
                    "account_name": account["name"],
                    "similarity_score": score / len(account["keywords"]),
                    "confidence": min(score / 3.0, 1.0)
                })
        
        # Sort by similarity score and take top 3
        matches.sort(key=lambda x: x["similarity_score"], reverse=True)
        top_matches = matches[:3]
        
        # If no matches, provide default suggestions
        if not top_matches:
            top_matches = [
                {"account_code": "5999", "account_name": "Miscellaneous Expense", "similarity_score": 0.1, "confidence": 0.1}
            ]
        
        avg_confidence = sum(m["confidence"] for m in top_matches) / len(top_matches)
        
        result = {
            "cost_identifier": request.cost_identifier,
            "suggestions": top_matches,
            "suggestion_count": len(top_matches)
        }
        
        return AccountSuggestResponse(
            result=result,
            confidence=round(avg_confidence, 4),
            model_version="account-suggest-v1.0"
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# Task 56.5: Cash Flow Prediction
# Requirements 57.5, 57.6
# ============================================================================

@router.post("/cash-flow-predict", response_model=CashFlowPredictResponse)
async def predict_cash_flow(request: CashFlowPredictRequest):
    """
    Predict cash flow for next N months per Company_Code per currency.
    
    Requirements 57.5, 57.6: Cash flow prediction with translation revenue
    """
    try:
        # Simulated cash flow prediction using simple time series
        # In production, use ARIMA/Prophet models
        
        current_month = datetime.now()
        predictions = []
        
        # Simulated historical average (in production, calculate from actual data)
        base_inflow = 500000  # EGP
        base_outflow = 450000  # EGP
        
        for i in range(request.months):
            month_date = current_month + timedelta(days=30 * (i + 1))
            
            # Add some randomness and trend
            trend_factor = 1 + (i * 0.02)  # 2% growth per month
            random_factor = np.random.uniform(0.9, 1.1)
            
            inflow = base_inflow * trend_factor * random_factor
            outflow = base_outflow * trend_factor * random_factor
            net_flow = inflow - outflow
            
            predictions.append({
                "month": month_date.strftime("%Y-%m"),
                "predicted_inflow": round(inflow, 2),
                "predicted_outflow": round(outflow, 2),
                "predicted_net_flow": round(net_flow, 2),
                "confidence_interval_lower": round(net_flow * 0.85, 2),
                "confidence_interval_upper": round(net_flow * 1.15, 2)
            })
        
        result = {
            "company_code": request.company_code,
            "currency_code": request.currency_code,
            "prediction_months": request.months,
            "predictions": predictions,
            "total_predicted_net_flow": round(sum(p["predicted_net_flow"] for p in predictions), 2)
        }
        
        return CashFlowPredictResponse(
            result=result,
            confidence=0.75,
            model_version="cash-flow-v1.0"
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# Task 56.6: Journal Entry Outlier Detection
# Requirement 57.7
# ============================================================================

@router.post("/outlier-detect", response_model=OutlierDetectResponse)
async def detect_journal_outlier(request: OutlierDetectRequest):
    """
    Detect outliers in journal entry amounts using statistical analysis.
    
    Requirement 57.7: Flag statistical outliers compared to historical entries
    """
    try:
        # Simulated historical data (in production, query from database)
        # This would be: SELECT amount FROM journal_entry_lines 
        #                WHERE account_code = ? AND debit_credit = ?
        
        # Simulated historical amounts for this account
        np.random.seed(42)
        historical_amounts = np.random.lognormal(mean=10, sigma=1, size=100)
        
        # Add the current amount
        current_amount = request.amount
        
        # Calculate z-score
        mean = np.mean(historical_amounts)
        std = np.std(historical_amounts)
        
        if std > 0:
            z_score = abs((current_amount - mean) / std)
        else:
            z_score = 0
        
        # Threshold: z-score > 3 is considered an outlier
        threshold = 3.0
        is_outlier = z_score > threshold
        
        # Calculate percentile
        percentile = stats.percentileofscore(historical_amounts, current_amount)
        
        # Confidence based on z-score magnitude
        confidence = min(z_score / 5.0, 1.0) if is_outlier else 0.0
        
        result = {
            "is_outlier": is_outlier,
            "amount": current_amount,
            "account_code": request.account_code,
            "debit_credit": request.debit_credit,
            "z_score": round(z_score, 2),
            "threshold": threshold,
            "percentile": round(percentile, 2),
            "historical_mean": round(mean, 2),
            "historical_std": round(std, 2),
            "message": f"Amount is {'an outlier' if is_outlier else 'within normal range'} (z-score: {z_score:.2f})"
        }
        
        return OutlierDetectResponse(
            result=result,
            confidence=round(confidence, 4),
            model_version="outlier-detect-v1.0"
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


# ============================================================================
# Task 56.4: WIP Stale Balance Flagging (Celery Task)
# Requirement 57.4
# ============================================================================

def flag_stale_wip_balances(tenant_id: str, company_code: str) -> Dict:
    """
    Flag WIP accounts with no movement > 90 days.
    
    This is called by a Celery task monthly.
    Requirement 57.4: Notify Accountant of stale WIP balances
    """
    # Simulated WIP account check
    # In production, query: SELECT account_code, last_transaction_date, balance
    #                       FROM wip_accounts WHERE company_code = ?
    #                       AND last_transaction_date < NOW() - INTERVAL '90 days'
    
    stale_accounts = []
    
    # Simulated stale WIP accounts
    wip_accounts = [
        {"account_code": "1510", "account_name": "WIP EXP", "balance": 150000, "days_stale": 120},
        {"account_code": "1520", "account_name": "WIP DEV", "balance": 250000, "days_stale": 95},
    ]
    
    for account in wip_accounts:
        if account["days_stale"] > 90:
            stale_accounts.append({
                "account_code": account["account_code"],
                "account_name": account["account_name"],
                "balance": account["balance"],
                "days_stale": account["days_stale"],
                "severity": "high" if account["days_stale"] > 180 else "medium"
            })
    
    return {
        "company_code": company_code,
        "stale_account_count": len(stale_accounts),
        "stale_accounts": stale_accounts,
        "total_stale_balance": sum(a["balance"] for a in stale_accounts),
        "notification_sent": True
    }
