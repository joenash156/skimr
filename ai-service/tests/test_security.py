from __future__ import annotations

import json
import re
import time

import pytest
from fastapi import APIRouter, FastAPI
from fastapi.testclient import TestClient

from app.config import load_settings
from app.main import create_app
from app.security import SIGNED, sign

from .conftest import TEST_SECRET

PATH = "/v1/_signed-echo"


@pytest.fixture
def signed_client() -> TestClient:
    """An app with one signed test route that echoes its JSON body."""
    app = create_app(load_settings(environ={"AI_SERVICE_SECRET": TEST_SECRET}, env_file=None))
    router = APIRouter(prefix="/v1", dependencies=SIGNED)

    @router.post("/_signed-echo")
    def echo(payload: dict[str, object]) -> dict[str, object]:
        return payload

    app.include_router(router)
    return TestClient(app)


def signed_headers(
    body: bytes, *, timestamp: int | None = None, secret: str = TEST_SECRET
) -> dict[str, str]:
    ts = str(int(time.time()) if timestamp is None else timestamp)
    return {
        "Content-Type": "application/json",
        "X-Timestamp": ts,
        "X-Signature": sign(secret, ts, body),
    }


def error_code(response_json: object) -> str:
    assert isinstance(response_json, dict)
    return str(response_json["error"]["code"])


BODY = json.dumps({"type": "ping", "payload": {"note": "héllo"}}).encode()


def test_known_vector_matches_openssl() -> None:
    # Computed independently with:
    #   printf '%s' '1700000000.{"type":"ping"}' | openssl dgst -sha256 -hmac '<TEST_SECRET>'
    # P2's PHP client must produce the same value for the same inputs.
    assert (
        sign(TEST_SECRET, "1700000000", b'{"type":"ping"}')
        == "ab9499695086d393f3e0d63badae2ec8de73efedc7e8ceb06e04ebe7ef355623"
    )


def test_valid_signature_passes_and_body_still_readable(signed_client: TestClient) -> None:
    response = signed_client.post(PATH, content=BODY, headers=signed_headers(BODY))
    assert response.status_code == 200
    assert response.json() == {"type": "ping", "payload": {"note": "héllo"}}


def test_timestamp_within_window_passes(signed_client: TestClient) -> None:
    for offset in (-290, 290):
        headers = signed_headers(BODY, timestamp=int(time.time()) + offset)
        assert signed_client.post(PATH, content=BODY, headers=headers).status_code == 200


def test_bad_signature_is_rejected(signed_client: TestClient) -> None:
    headers = signed_headers(BODY)
    headers["X-Signature"] = "0" * 64
    response = signed_client.post(PATH, content=BODY, headers=headers)
    assert response.status_code == 401
    assert error_code(response.json()) == "invalid_signature"


def test_wrong_secret_is_rejected(signed_client: TestClient) -> None:
    headers = signed_headers(BODY, secret="another-secret-0123456789abcdef0123456789")
    response = signed_client.post(PATH, content=BODY, headers=headers)
    assert response.status_code == 401
    assert error_code(response.json()) == "invalid_signature"


def test_tampered_body_is_rejected(signed_client: TestClient) -> None:
    headers = signed_headers(BODY)
    tampered = BODY.replace(b"ping", b"pong")
    response = signed_client.post(PATH, content=tampered, headers=headers)
    assert response.status_code == 401
    assert error_code(response.json()) == "invalid_signature"


def test_signature_bound_to_timestamp(signed_client: TestClient) -> None:
    headers = signed_headers(BODY)
    headers["X-Timestamp"] = str(int(headers["X-Timestamp"]) - 1)
    response = signed_client.post(PATH, content=BODY, headers=headers)
    assert response.status_code == 401
    assert error_code(response.json()) == "invalid_signature"


@pytest.mark.parametrize("offset", [-310, 310])
def test_expired_or_future_timestamp_is_rejected(signed_client: TestClient, offset: int) -> None:
    headers = signed_headers(BODY, timestamp=int(time.time()) + offset)
    response = signed_client.post(PATH, content=BODY, headers=headers)
    assert response.status_code == 401
    assert error_code(response.json()) == "expired_timestamp"


@pytest.mark.parametrize("missing", ["X-Timestamp", "X-Signature"])
def test_missing_header_is_rejected(signed_client: TestClient, missing: str) -> None:
    headers = signed_headers(BODY)
    del headers[missing]
    response = signed_client.post(PATH, content=BODY, headers=headers)
    assert response.status_code == 401
    assert error_code(response.json()) == "missing_signature"


@pytest.mark.parametrize("bad_ts", ["abc", "-5", "1700000000.5", "", "9" * 13])
def test_malformed_timestamp_is_rejected(signed_client: TestClient, bad_ts: str) -> None:
    headers = signed_headers(BODY)
    headers["X-Timestamp"] = bad_ts
    response = signed_client.post(PATH, content=BODY, headers=headers)
    assert response.status_code == 401
    assert error_code(response.json()) == "invalid_timestamp"


def test_uppercase_or_short_signature_is_rejected(signed_client: TestClient) -> None:
    headers = signed_headers(BODY)
    for bad in (headers["X-Signature"].upper(), headers["X-Signature"][:-1]):
        response = signed_client.post(PATH, content=BODY, headers={**headers, "X-Signature": bad})
        assert response.status_code == 401
        assert error_code(response.json()) == "invalid_signature"


def test_error_does_not_echo_header_values(signed_client: TestClient) -> None:
    headers = signed_headers(BODY)
    headers["X-Signature"] = "a" * 64
    response = signed_client.post(PATH, content=BODY, headers=headers)
    assert "a" * 64 not in response.text


def test_health_needs_no_signature(client: TestClient) -> None:
    assert client.get("/health").status_code == 200


OPEN_ROUTES = {("get", "/health")}


def unsigned_status_codes(client: TestClient) -> dict[tuple[str, str], int]:
    """Call every route in the app's schema with no signature; return each status code."""
    app = client.app
    assert isinstance(app, FastAPI)
    results: dict[tuple[str, str], int] = {}
    for path, methods in app.openapi()["paths"].items():
        url = re.sub(r"\{[^}]+\}", "x", path)  # fill path parameters
        for method in methods:
            results[(method, path)] = client.request(method.upper(), url).status_code
    return results


def test_guard_sees_signed_and_open_routes(signed_client: TestClient) -> None:
    codes = unsigned_status_codes(signed_client)
    assert codes[("post", PATH)] == 401
    assert codes[("get", "/health")] == 200


def test_every_route_except_health_refuses_unsigned_requests(client: TestClient) -> None:
    """Guard: a new router that forgets dependencies=SIGNED fails this test."""
    codes = unsigned_status_codes(client)
    assert ("get", "/health") in codes
    for route, status in codes.items():
        if route not in OPEN_ROUTES:
            assert status == 401, f"{route} answered {status} without a signature"
