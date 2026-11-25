"""In-memory job state (the service is stateless, spec 17.3).

A restart loses every job; PHP then gets 404 and resubmits (spec 6.1).
Finished jobs are removed ``ttl_seconds`` after they finish.
"""

from __future__ import annotations

import threading
import time
import uuid
from collections.abc import Callable
from dataclasses import dataclass, field
from typing import Any

from app.schemas.jobs import JobErrorInfo, JobStatusName, JobView, UsageRecord

FINISHED_TTL_SECONDS = 30 * 60


@dataclass
class _Job:
    id: str
    type: str
    payload: dict[str, Any] | None
    status: JobStatusName = "queued"
    progress: int = 0
    result: dict[str, Any] | None = None
    error: JobErrorInfo | None = None
    usage: list[UsageRecord] = field(default_factory=list)
    finished_at: float | None = None


class JobStore:
    """Thread-safe dictionary of jobs. Every method takes the same lock."""

    def __init__(
        self,
        ttl_seconds: float = FINISHED_TTL_SECONDS,
        clock: Callable[[], float] = time.monotonic,
    ) -> None:
        self._jobs: dict[str, _Job] = {}
        self._lock = threading.Lock()
        self._ttl = ttl_seconds
        self._clock = clock

    def __len__(self) -> int:
        with self._lock:
            return len(self._jobs)

    def create(self, job_type: str, payload: dict[str, Any]) -> str:
        job_id = uuid.uuid4().hex
        with self._lock:
            self._purge_locked()
            self._jobs[job_id] = _Job(id=job_id, type=job_type, payload=payload)
        return job_id

    def start(self, job_id: str) -> dict[str, Any]:
        """Mark the job running and hand its payload to the worker.

        The store drops its own reference so large payloads are freed when the job ends.
        """
        with self._lock:
            job = self._jobs[job_id]
            payload = job.payload if job.payload is not None else {}
            job.payload = None
            job.status = "running"
            return payload

    def set_progress(self, job_id: str, percent: int) -> None:
        with self._lock:
            job = self._jobs.get(job_id)
            if job is not None and job.status == "running":
                job.progress = max(0, min(100, percent))

    def add_usage(self, job_id: str, record: UsageRecord) -> None:
        with self._lock:
            job = self._jobs.get(job_id)
            if job is not None:
                job.usage.append(record)

    def succeed(self, job_id: str, result: dict[str, Any]) -> None:
        with self._lock:
            job = self._jobs.get(job_id)
            if job is not None:
                job.status = "succeeded"
                job.progress = 100
                job.result = result
                job.finished_at = self._clock()

    def fail(self, job_id: str, code: str, message: str) -> None:
        with self._lock:
            job = self._jobs.get(job_id)
            if job is not None:
                job.status = "failed"
                job.error = JobErrorInfo(code=code, message=message)
                job.payload = None
                job.finished_at = self._clock()

    def view(self, job_id: str) -> JobView | None:
        """Public snapshot of a job, or None if it never existed or was purged."""
        with self._lock:
            self._purge_locked()
            job = self._jobs.get(job_id)
            if job is None:
                return None
            fields: dict[str, Any] = {
                "status": job.status,
                "progress": job.progress,
                "usage": list(job.usage),
            }
            if job.status == "succeeded":
                fields["result"] = job.result
            if job.status == "failed":
                fields["error"] = job.error
            return JobView(**fields)

    def purge_expired(self) -> int:
        with self._lock:
            return self._purge_locked()

    def _purge_locked(self) -> int:
        cutoff = self._clock() - self._ttl
        expired = [
            job_id
            for job_id, job in self._jobs.items()
            if job.finished_at is not None and job.finished_at <= cutoff
        ]
        for job_id in expired:
            del self._jobs[job_id]
        return len(expired)
