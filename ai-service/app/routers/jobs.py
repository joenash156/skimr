"""Job submission and polling (spec 6.1, 10.3). HMAC-signed like every route but /health."""

from __future__ import annotations

from fastapi import APIRouter, Request
from fastapi.responses import JSONResponse

from app.errors import ServiceError
from app.jobs.runner import JobRunner
from app.schemas.jobs import JobAccepted, JobSubmit
from app.security import SIGNED

router = APIRouter(prefix="/v1/jobs", tags=["jobs"], dependencies=SIGNED)


def _runner(request: Request) -> JobRunner:
    runner: JobRunner = request.app.state.runner
    return runner


@router.post("", status_code=202)
def submit_job(body: JobSubmit, request: Request) -> JobAccepted:
    job_id = _runner(request).submit(body.type, body.payload)
    return JobAccepted(job_id=job_id)


@router.get("/{job_id}")
def get_job(job_id: str, request: Request) -> JSONResponse:
    view = _runner(request).store.view(job_id)
    if view is None:
        raise ServiceError(404, "job_not_found", "Unknown or expired job id")
    # exclude_unset drops result/error when absent but keeps nulls inside the result.
    return JSONResponse(view.model_dump(mode="json", exclude_unset=True))
