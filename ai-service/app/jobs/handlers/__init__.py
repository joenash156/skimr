"""Registry of job handlers by job type. Each batch adds its job types here (spec 6.1)."""

from __future__ import annotations

from app.jobs.runner import Handler

HANDLERS: dict[str, Handler] = {}
