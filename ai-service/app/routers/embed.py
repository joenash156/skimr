"""Synchronous embeddings for search queries and result ranking (spec 7, F21)."""

from __future__ import annotations

from fastapi import APIRouter

from app.pipeline.embed import embed_texts
from app.schemas.embed import EmbedRequest, EmbedResponse
from app.security import SIGNED

router = APIRouter(prefix="/v1", tags=["embed"], dependencies=SIGNED)


@router.post("/embed")
def embed(body: EmbedRequest) -> EmbedResponse:
    return EmbedResponse(vectors=embed_texts(body.texts))
