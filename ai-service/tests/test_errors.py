from __future__ import annotations

import json

from fastapi.testclient import TestClient

from .conftest import signed_headers


def test_validation_error_uses_the_common_error_shape(client: TestClient) -> None:
    raw = json.dumps({"texts": [f"t{i}" for i in range(33)]}).encode()
    response = client.post("/v1/embed", content=raw, headers=signed_headers(raw))
    assert response.status_code == 422
    body = response.json()
    assert set(body) == {"error"}
    assert body["error"]["code"] == "invalid_request"
    assert body["error"]["message"].startswith("texts:")


def test_validation_error_does_not_echo_input(client: TestClient) -> None:
    marker = "PRIVATE-SOURCE-TEXT-123"
    raw = json.dumps({"texts": marker}).encode()  # wrong type: string instead of list
    response = client.post("/v1/embed", content=raw, headers=signed_headers(raw))
    assert response.status_code == 422
    assert marker not in response.text


def test_malformed_json_is_422_invalid_request(client: TestClient) -> None:
    raw = b"{not json"
    response = client.post("/v1/embed", content=raw, headers=signed_headers(raw))
    assert response.status_code == 422
    assert response.json()["error"]["code"] == "invalid_request"
