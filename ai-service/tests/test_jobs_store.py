from __future__ import annotations

from app.jobs.store import FINISHED_TTL_SECONDS, JobStore
from app.schemas.jobs import UsageRecord


class FakeClock:
    def __init__(self) -> None:
        self.now = 1000.0

    def __call__(self) -> float:
        return self.now


USAGE = UsageRecord(
    provider="gemini", model="m", tokens_in=1, tokens_out=2, latency_ms=3, outcome="ok"
)


def test_lifecycle_queued_running_succeeded() -> None:
    store = JobStore()
    job_id = store.create("dummy", {"a": 1})
    view = store.view(job_id)
    assert view is not None
    assert (view.status, view.progress, view.usage) == ("queued", 0, [])

    assert store.start(job_id) == {"a": 1}
    store.set_progress(job_id, 40)
    store.add_usage(job_id, USAGE)
    view = store.view(job_id)
    assert view is not None
    assert (view.status, view.progress, view.usage) == ("running", 40, [USAGE])
    assert view.result is None and view.error is None

    store.succeed(job_id, {"ok": True, "doi": None})
    view = store.view(job_id)
    assert view is not None
    assert (view.status, view.progress) == ("succeeded", 100)
    assert view.result == {"ok": True, "doi": None}
    assert view.error is None


def test_start_releases_the_payload() -> None:
    store = JobStore()
    job_id = store.create("dummy", {"big": "x" * 10})
    store.start(job_id)
    assert store._jobs[job_id].payload is None


def test_fail_records_error() -> None:
    store = JobStore()
    job_id = store.create("dummy", {})
    store.start(job_id)
    store.fail(job_id, "bad_input", "Nope")
    view = store.view(job_id)
    assert view is not None
    assert view.status == "failed"
    assert view.error is not None and view.error.code == "bad_input"
    assert view.result is None


def test_progress_is_clamped_and_only_while_running() -> None:
    store = JobStore()
    job_id = store.create("dummy", {})
    store.set_progress(job_id, 50)  # still queued: ignored
    view = store.view(job_id)
    assert view is not None and view.progress == 0
    store.start(job_id)
    store.set_progress(job_id, 250)
    view = store.view(job_id)
    assert view is not None and view.progress == 100
    store.set_progress(job_id, -5)
    view = store.view(job_id)
    assert view is not None and view.progress == 0


def test_unknown_job_and_updates_to_it_are_harmless() -> None:
    store = JobStore()
    assert store.view("missing") is None
    store.set_progress("missing", 10)
    store.add_usage("missing", USAGE)
    store.succeed("missing", {})
    store.fail("missing", "x", "y")
    assert len(store) == 0


def test_finished_jobs_are_purged_after_30_minutes() -> None:
    clock = FakeClock()
    store = JobStore(clock=clock)
    done = store.create("dummy", {})
    store.start(done)
    store.succeed(done, {})
    running = store.create("dummy", {})
    store.start(running)

    clock.now += FINISHED_TTL_SECONDS - 1
    assert store.view(done) is not None

    clock.now += 1
    assert store.view(done) is None  # purged on read
    assert store.view(running) is not None  # unfinished jobs are kept
    assert len(store) == 1


def test_purge_expired_counts_removed_jobs() -> None:
    clock = FakeClock()
    store = JobStore(ttl_seconds=10, clock=clock)
    for _ in range(3):
        job_id = store.create("dummy", {})
        store.start(job_id)
        store.fail(job_id, "x", "y")
    clock.now += 10
    assert store.purge_expired() == 3
    assert len(store) == 0
