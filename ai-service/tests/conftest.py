"""Shared test setup.

Fake values for every setting are placed in the real environment *before* the app is
imported. Real environment variables override ai-service/.env, so tests never pick up
a developer's real keys from that file.
"""

from __future__ import annotations

import os

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


@pytest.fixture
def client() -> TestClient:
    return TestClient(app)
