"""Shared test setup.

Fake values for every setting are placed in the real environment *before* the app is
imported. Real environment variables override ai-service/.env, so tests never pick up
a developer's real keys from that file.
"""

from __future__ import annotations

import os
import time

import pytest
from fastapi.testclient import TestClient

TEST_SECRET = "test-secret-0123456789abcdef0123456789abcdef"

os.environ.update(
    {
        "AI_SERVICE_SECRET": TEST_SECRET,
        "GEMINI_API_KEY": "",
        "GROQ_API_KEY": "",
        "OPENALEX_API_KEY": "",
        "SEMANTIC_SCHOLAR_API_KEY": "",
        "LLM_PROVIDER_ORDER": "gemini,groq",
    }
)

from app.main import app  # noqa: E402  (must come after the environment is set)
from app.security import sign  # noqa: E402


def signed_headers(
    body: bytes, *, timestamp: int | None = None, secret: str = TEST_SECRET
) -> dict[str, str]:
    """Headers PHP would send for this raw body."""
    ts = str(int(time.time()) if timestamp is None else timestamp)
    return {
        "Content-Type": "application/json",
        "X-Timestamp": ts,
        "X-Signature": sign(secret, ts, body),
    }


@pytest.fixture
def client() -> TestClient:
    return TestClient(app)
