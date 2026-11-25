from __future__ import annotations

import json
import threading
import time
from collections.abc import Iterator
from typing import Any

import pytest
from fastapi.testclient import TestClient

from app.config import load_settings
from app.jobs.runner import Handler, JobContext, JobFailure
from app.main import create_app
from app.schemas.jobs import UsageRecord

from .conftest import TEST_SECRET, signed_headers

release = threading.Event()


def dummy(payload: dict[str, Any], ctx: JobContext) -> dict[str, Any]:
    ctx.set_progress(50)
    ctx.add_usage(
        UsageRecord(
            provider="gemini", model="test", tokens_in=10, tokens_out=5, latency_ms=7, outcome="ok"
        )
    )
    return {"echo": payload, "doi": None}


def blocking(payload: dict[str, Any], ctx: JobContext) -> dict[str, Any]:
    release.wait(timeout=5)
    return {}


def failing(payload: dict[str, Any], ctx: JobContext) -> dict[str, Any]:
    raise JobFailure("unreadable", "The PDF is encrypted")


def crashing(payload: dict[str, Any], ctx: JobContext) -> dict[str, Any]:
    raise RuntimeError("secret internal detail")


HANDLERS: dict[str, Handler] = {
    "dummy": dummy,
    "blocking": blocking,
    "failing": failing,
    "crashing": crashing,
}


@pytest.fixture
def jobs_client() -> Iterator[TestClient]:
    release.clear()
    app = create_app(
        load_settings(environ={"AI_SERVICE_SECRET": TEST_SECRET}, env_file=None),
        handlers=HANDLERS,
    )
    with TestClient(app) as client:  # runs the lifespan, so the pool shuts down
        yield client
    release.set()


def submit(client: TestClient, job_type: str, payload: dict[str, Any] | None = None) -> str:
    body = json.dumps({"type": job_type, "payload": payload or {}}).encode()
    response = client.post("/v1/jobs", content=body, headers=signed_headers(body))
    assert response.status_code == 202, response.text
    job_id = response.json()["job_id"]
    assert isinstance(job_id, str)
    return job_id


def poll(client: TestClient, job_id: str) -> dict[str, Any]:
    headers = signed_headers(b"")
    response = client.get(f"/v1/jobs/{job_id}", headers=headers)
    assert response.status_code == 200, response.text
    data = response.json()
    assert isinstance(data, dict)
    return data


def wait_for(client: TestClient, job_id: str, statuses: set[str]) -> dict[str, Any]:
    deadline = time.monotonic() + 5
    while time.monotonic() < deadline:
        data = poll(client, job_id)
        if data["status"] in statuses:
            return data
        time.sleep(0.01)
    raise AssertionError(f"job {job_id} never reached {statuses}")


def test_dummy_job_goes_from_queued_to_succeeded(jobs_client: TestClient) -> None:
    # Fill both workers so the dummy job has to wait in the queue first.
    blockers = [submit(jobs_client, "blocking") for _ in range(2)]
    job_id = submit(jobs_client, "dummy", {"source_uuid": "abc"})
    assert poll(jobs_client, job_id) == {"status": "queued", "progress": 0, "usage": []}

    release.set()
    data = wait_for(jobs_client, job_id, {"succeeded", "failed"})
    assert data == {
        "status": "succeeded",
        "progress": 100,
        "result": {"echo": {"source_uuid": "abc"}, "doi": None},  # nulls inside result are kept
        "usage": [
            {
                "provider": "gemini",
                "model": "test",
                "tokens_in": 10,
                "tokens_out": 5,
                "latency_ms": 7,
                "outcome": "ok",
            }
        ],
    }
    for blocker in blockers:
        assert wait_for(jobs_client, blocker, {"succeeded"})["status"] == "succeeded"


def test_at_most_two_jobs_run_at_once(jobs_client: TestClient) -> None:
    ids = [submit(jobs_client, "blocking") for _ in range(3)]
    wait_for(jobs_client, ids[0], {"running"})
    wait_for(jobs_client, ids[1], {"running"})
    time.sleep(0.05)
    assert poll(jobs_client, ids[2])["status"] == "queued"
    release.set()
    for job_id in ids:
        wait_for(jobs_client, job_id, {"succeeded"})


def test_expected_failure_is_reported(jobs_client: TestClient) -> None:
    job_id = submit(jobs_client, "failing")
    data = wait_for(jobs_client, job_id, {"succeeded", "failed"})
    assert data["status"] == "failed"
    assert data["error"] == {"code": "unreadable", "message": "The PDF is encrypted"}
    assert "result" not in data


def test_crash_is_reported_without_internal_details(jobs_client: TestClient) -> None:
    job_id = submit(jobs_client, "crashing")
    data = wait_for(jobs_client, job_id, {"succeeded", "failed"})
    assert data["status"] == "failed"
    assert data["error"]["code"] == "internal_error"
    assert "secret internal detail" not in json.dumps(data)


def test_unknown_job_type_is_422(jobs_client: TestClient) -> None:
    body = json.dumps({"type": "not_a_job", "payload": {}}).encode()
    response = jobs_client.post("/v1/jobs", content=body, headers=signed_headers(body))
    assert response.status_code == 422
    assert response.json()["error"]["code"] == "unknown_job_type"


@pytest.mark.parametrize(
    "body",
    [
        {"payload": {}},  # missing type
        {"type": "Dummy!", "payload": {}},  # bad characters
        {"type": "dummy", "payload": {}, "callback": "https://evil.example"},
        {"type": "dummy", "payload": {}, "extra": 1},
    ],
)
def test_invalid_submit_body_is_rejected(jobs_client: TestClient, body: dict[str, Any]) -> None:
    raw = json.dumps(body).encode()
    response = jobs_client.post("/v1/jobs", content=raw, headers=signed_headers(raw))
    assert response.status_code == 422


def test_callback_none_is_accepted(jobs_client: TestClient) -> None:
    for callback in ("none", None):
        raw = json.dumps({"type": "dummy", "payload": {}, "callback": callback}).encode()
        response = jobs_client.post("/v1/jobs", content=raw, headers=signed_headers(raw))
        assert response.status_code == 202


def test_unknown_job_id_is_404(jobs_client: TestClient) -> None:
    response = jobs_client.get("/v1/jobs/does-not-exist", headers=signed_headers(b""))
    assert response.status_code == 404
    assert response.json()["error"]["code"] == "job_not_found"


def test_job_routes_require_signature(jobs_client: TestClient) -> None:
    assert jobs_client.post("/v1/jobs", json={"type": "dummy"}).status_code == 401
    assert jobs_client.get("/v1/jobs/anything").status_code == 401


def test_production_app_has_no_job_types_yet(client: TestClient) -> None:
    body = json.dumps({"type": "extract_source", "payload": {}}).encode()
    response = client.post("/v1/jobs", content=body, headers=signed_headers(body))
    assert response.status_code == 422
