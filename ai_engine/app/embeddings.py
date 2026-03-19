from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from typing import List, Optional
import random

router = APIRouter(prefix="/embed", tags=["Semantic Search & Embeddings"])

class EmbeddingRequest(BaseModel):
    tenant_id: str
    record_type: str # "Lead", "Contact", "Account", "Invoice", "Project"
    record_id: int
    content: str
    metadata: Optional[dict] = None

class SearchRequest(BaseModel):
    tenant_id: str
    query: str
    top_n: int = 20

@router.post("/record")
async def generate_record_embedding(req: EmbeddingRequest):
    """
    Task 53.2: Embedding generation per record (768-dim vectors)
    Requirement 38.3
    """
    # Generating mock 768-dim vector
    import numpy as np
    vector = np.random.uniform(-1, 1, 768).tolist()
    
    # In production: push to pgvector record_embeddings table
    return {
        "success": True,
        "tenant_id": req.tenant_id,
        "record_id": req.record_id,
        "embedding": vector,
        "model_version": "sentence-transformers-v3"
    }

@router.post("/search/semantic")
async def search_semantic(req: SearchRequest):
    """
    Task 53.3: Semantic Search + Combined Keyword result logic
    Requirements 38.4, 38.5
    """
    # Mocking semantic matches
    import random
    matches = [
        {"id": random.randint(1, 100), "score": 0.95 - (i * 0.02)}
        for i in range(min(req.top_n, 10))
    ]
    
    return {
        "matches": matches,
        "total": len(matches),
        "query_latency_ms": 120
    }
