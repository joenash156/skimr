from __future__ import annotations

import json
from typing import Any

import pytest
from fastapi.testclient import TestClient

from app.pipeline.embed import EMBEDDING_DIM, embed_texts

from .conftest import signed_headers


def post_embed(client: TestClient, body: dict[str, Any]) -> Any:
    raw = json.dumps(body).encode()
    return client.post("/v1/embed", content=raw, headers=signed_headers(raw))


def test_returns_one_384_dim_vector_per_text(client: TestClient) -> None:
    texts = ["AI adoption in Ghanaian SMEs", "adopción de IA", "adoption de l'IA"]
    response = post_embed(client, {"texts": texts})
    assert response.status_code == 200
    vectors = response.json()["vectors"]
    assert len(vectors) == len(texts)
    assert all(len(v) == EMBEDDING_DIM for v in vectors)


def test_accepts_exactly_32_texts(client: TestClient) -> None:
    response = post_embed(client, {"texts": [f"t{i}" for i in range(32)]})
    assert response.status_code == 200
    assert len(response.json()["vectors"]) == 32


@pytest.mark.parametrize(
    "body",
    [
        {"texts": [f"t{i}" for i in range(33)]},  # too many
        {"texts": []},  # empty
        {},  # missing
        {"texts": "not a list"},
        {"texts": ["ok"], "extra": 1},
    ],
)
def test_invalid_requests_are_rejected(client: TestClient, body: dict[str, Any]) -> None:
    assert post_embed(client, body).status_code == 422


def test_requires_signature(client: TestClient) -> None:
    assert client.post("/v1/embed", json={"texts": ["x"]}).status_code == 401


def test_stub_returns_zero_vectors() -> None:
    vectors = embed_texts(["a", "b"])
    assert vectors == [[0.0] * EMBEDDING_DIM, [0.0] * EMBEDDING_DIM]
