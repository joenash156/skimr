"""Models for the synchronous ``POST /v1/embed`` route (spec 10.3)."""

from __future__ import annotations

from pydantic import BaseModel, ConfigDict, Field

MAX_TEXTS = 32


class EmbedRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    texts: list[str] = Field(min_length=1, max_length=MAX_TEXTS)


class EmbedResponse(BaseModel):
    """``vectors[i]`` is the embedding of ``texts[i]``."""

    vectors: list[list[float]]
