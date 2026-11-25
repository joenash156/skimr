"""Liveness check. The only route without HMAC auth (spec 10.3); PHP also uses it to wake Render."""

from __future__ import annotations

from typing import Literal

from fastapi import APIRouter
from pydantic import BaseModel

router = APIRouter(tags=["health"])


class HealthResponse(BaseModel):
    status: Literal["ok"] = "ok"


@router.get("/health")
def health() -> HealthResponse:
    return HealthResponse()
