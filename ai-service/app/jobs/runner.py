"""Runs jobs on a small thread pool (at most 2 at once, to fit 512 MB; spec 17.3)."""

from __future__ import annotations

import logging
from collections.abc import Callable, Mapping
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass
from typing import Any

from app.errors import ServiceError
from app.jobs.store import JobStore
from app.schemas.jobs import UsageRecord

MAX_WORKERS = 2

logger = logging.getLogger(__name__)


class JobFailure(Exception):
    """Raised by a handler for an expected failure; ``code`` and ``message`` reach PHP."""

    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True)
class JobContext:
    """What a handler may do while running: report progress and LLM usage."""

    job_id: str
    _store: JobStore

    def set_progress(self, percent: int) -> None:
        self._store.set_progress(self.job_id, percent)

    def add_usage(self, record: UsageRecord) -> None:
        self._store.add_usage(self.job_id, record)


Handler = Callable[[dict[str, Any], JobContext], dict[str, Any]]


class JobRunner:
    def __init__(
        self,
        store: JobStore,
        handlers: Mapping[str, Handler],
        max_workers: int = MAX_WORKERS,
    ) -> None:
        self.store = store
        self._handlers = dict(handlers)
        self._executor = ThreadPoolExecutor(max_workers=max_workers, thread_name_prefix="job")

    def submit(self, job_type: str, payload: dict[str, Any]) -> str:
        handler = self._handlers.get(job_type)
        if handler is None:
            raise ServiceError(422, "unknown_job_type", f"Unknown job type: {job_type}")
        job_id = self.store.create(job_type, payload)
        self._executor.submit(self._run, job_id, job_type, handler)
        return job_id

    def _run(self, job_id: str, job_type: str, handler: Handler) -> None:
        payload = self.store.start(job_id)
        try:
            result = handler(payload, JobContext(job_id, self.store))
        except JobFailure as exc:
            self.store.fail(job_id, exc.code, exc.message)
        except Exception:
            # Details stay in our logs; PHP gets a generic message.
            logger.exception("Job %s (%s) crashed", job_id, job_type)
            self.store.fail(job_id, "internal_error", "The job failed unexpectedly")
        else:
            self.store.succeed(job_id, result)

    def shutdown(self) -> None:
        self._executor.shutdown(wait=False, cancel_futures=True)
