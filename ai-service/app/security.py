"""HMAC request signing between the PHP app and this service (spec 10.3).

PHP sends two headers on every request except ``GET /health``:

- ``X-Timestamp``: Unix time in whole seconds.
- ``X-Signature``: lowercase hex of HMAC-SHA256(AI_SERVICE_SECRET, timestamp + "." + raw_body).

Requests whose timestamp is more than 300 seconds away from our clock (in either
direction) are refused, as are missing, malformed or wrong signatures.
"""

from __future__ import annotations

import hashlib
import hmac
import logging
import re
import time

from fastapi import Depends, Request
from fastapi.responses import JSONResponse

from app.config import Settings

TIMESTAMP_HEADER = "X-Timestamp"
SIGNATURE_HEADER = "X-Signature"
MAX_SKEW_SECONDS = 300

_TIMESTAMP_RE = re.compile(r"[0-9]{1,12}")
_SIGNATURE_RE = re.compile(r"[0-9a-f]{64}")

logger = logging.getLogger(__name__)


class SignatureError(Exception):
    """A request failed HMAC verification. Turned into a 401 by ``signature_error_handler``."""

    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


def sign(secret: str, timestamp: str, body: bytes) -> str:
    """Return the expected ``X-Signature`` for a timestamp and raw request body."""
    message = timestamp.encode("ascii") + b"." + body
    return hmac.new(secret.encode("utf-8"), message, hashlib.sha256).hexdigest()


async def verify_signature(request: Request) -> None:
    """FastAPI dependency: refuse the request unless it is correctly signed and fresh."""
    timestamp = request.headers.get(TIMESTAMP_HEADER)
    signature = request.headers.get(SIGNATURE_HEADER)
    if timestamp is None or signature is None:
        raise SignatureError("missing_signature", "X-Timestamp and X-Signature are required")
    if not _TIMESTAMP_RE.fullmatch(timestamp):
        raise SignatureError("invalid_timestamp", "X-Timestamp must be Unix seconds")
    if abs(time.time() - int(timestamp)) > MAX_SKEW_SECONDS:
        raise SignatureError("expired_timestamp", "X-Timestamp is more than 300 seconds off")
    if not _SIGNATURE_RE.fullmatch(signature):
        raise SignatureError("invalid_signature", "X-Signature must be 64 lowercase hex characters")

    settings: Settings = request.app.state.settings
    body = await request.body()  # Starlette caches it, so the route can still read it
    expected = sign(settings.ai_service_secret.get_secret_value(), timestamp, body)
    if not hmac.compare_digest(expected, signature):
        raise SignatureError("invalid_signature", "X-Signature does not match")


# Attach to every protected router: APIRouter(dependencies=SIGNED).
SIGNED = [Depends(verify_signature)]


async def signature_error_handler(request: Request, exc: Exception) -> JSONResponse:
    """Render a ``SignatureError`` as 401 without echoing any header values."""
    if not isinstance(exc, SignatureError):  # pragma: no cover - only registered for SignatureError
        raise exc
    client = request.client.host if request.client else "unknown"
    logger.warning("Refused unsigned request: %s from %s to %s", exc.code, client, request.url.path)
    return JSONResponse(
        status_code=401,
        content={"error": {"code": exc.code, "message": exc.message}},
    )
