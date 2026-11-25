"""FastAPI entry point: ``uvicorn app.main:app``."""

from __future__ import annotations

from collections.abc import AsyncIterator, Mapping
from contextlib import asynccontextmanager

from fastapi import FastAPI

from app import __version__
from app.config import Settings, get_settings
from app.errors import ServiceError, service_error_handler
from app.jobs.handlers import HANDLERS
from app.jobs.runner import Handler, JobRunner
from app.jobs.store import JobStore
from app.routers import embed, health, jobs
from app.security import SignatureError, signature_error_handler


@asynccontextmanager
async def _lifespan(app: FastAPI) -> AsyncIterator[None]:
    yield
    app.state.runner.shutdown()


def create_app(
    settings: Settings | None = None,
    handlers: Mapping[str, Handler] | None = None,
) -> FastAPI:
    """Build the app. Fails fast if the settings are invalid (e.g. placeholder secret)."""
    settings = settings if settings is not None else get_settings()
    app = FastAPI(
        title="Skimr AI service",
        version=__version__,
        lifespan=_lifespan,
        # Only PHP calls this service, so no public API docs are served.
        docs_url=None,
        redoc_url=None,
        openapi_url=None,
    )
    app.state.settings = settings
    app.state.runner = JobRunner(JobStore(), HANDLERS if handlers is None else handlers)
    app.add_exception_handler(SignatureError, signature_error_handler)
    app.add_exception_handler(ServiceError, service_error_handler)
    # /health is the only open route. Every other router must use dependencies=SIGNED.
    app.include_router(health.router)
    app.include_router(jobs.router)
    app.include_router(embed.router)
    return app


app = create_app()
