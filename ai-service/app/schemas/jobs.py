"""Job envelope models for ``POST /v1/jobs`` and ``GET /v1/jobs/{id}`` (spec 10.3).

Per-job-type payload and result models live in their own modules next to this one.
"""

from __future__ import annotations

from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field

JobStatusName = Literal["queued", "running", "succeeded", "failed"]


class JobSubmit(BaseModel):
    """Body of ``POST /v1/jobs``. PHP polls for the result, so there is no callback."""

    model_config = ConfigDict(extra="forbid")

    type: str = Field(pattern=r"^[a-z][a-z_]{0,39}$")
    payload: dict[str, Any] = Field(default_factory=dict)
    callback: Literal["none"] | None = None


class JobAccepted(BaseModel):
    job_id: str


class JobErrorInfo(BaseModel):
    code: str
    message: str


class UsageRecord(BaseModel):
    """One LLM call, copied by PHP into ``ai_usage`` (spec 6.5)."""

    provider: str
    model: str
    tokens_in: int = Field(ge=0)
    tokens_out: int = Field(ge=0)
    latency_ms: int = Field(ge=0)
    outcome: str


class JobView(BaseModel):
    """Body of ``GET /v1/jobs/{id}``. ``result`` only when succeeded, ``error`` only when failed."""

    status: JobStatusName
    progress: int = Field(ge=0, le=100)
    result: dict[str, Any] | None = None
    error: JobErrorInfo | None = None
    usage: list[UsageRecord]
