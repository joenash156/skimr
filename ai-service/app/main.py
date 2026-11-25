"""FastAPI entry point: ``uvicorn app.main:app``."""

from __future__ import annotations

from fastapi import FastAPI

from app import __version__
from app.config import Settings, get_settings
from app.routers import health


def create_app(settings: Settings | None = None) -> FastAPI:
    """Build the app. Fails fast if the settings are invalid (e.g. placeholder secret)."""
    settings = settings if settings is not None else get_settings()
    app = FastAPI(
        title="Skimr AI service",
        version=__version__,
        # Only PHP calls this service, so no public API docs are served.
        docs_url=None,
        redoc_url=None,
        openapi_url=None,
    )
    app.state.settings = settings
    app.include_router(health.router)
    return app


app = create_app()
