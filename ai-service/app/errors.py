"""Expected API errors, rendered as ``{"error": {"code", "message"}}``."""

from __future__ import annotations

from fastapi import Request
from fastapi.responses import JSONResponse


class ServiceError(Exception):
    """An error the client can act on. Never put secrets or source text in the message."""

    def __init__(self, status_code: int, code: str, message: str) -> None:
        super().__init__(message)
        self.status_code = status_code
        self.code = code
        self.message = message


async def service_error_handler(request: Request, exc: Exception) -> JSONResponse:
    if not isinstance(exc, ServiceError):  # pragma: no cover - only registered for ServiceError
        raise exc
    return JSONResponse(
        status_code=exc.status_code,
        content={"error": {"code": exc.code, "message": exc.message}},
    )
