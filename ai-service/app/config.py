"""Service settings: environment variables read into a validated Pydantic model.

Values come from ``ai-service/.env`` (local development) and from real environment
variables (Render), with real environment variables taking precedence. Secrets are
held as ``SecretStr`` so they never appear in reprs, logs or tracebacks.
"""

from __future__ import annotations

import os
from collections.abc import Mapping
from functools import lru_cache
from pathlib import Path
from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, SecretStr, field_validator

# ai-service/.env (this file is ai-service/app/config.py).
ENV_FILE = Path(__file__).resolve().parent.parent / ".env"

MIN_SECRET_LENGTH = 32  # same minimum the PHP app uses for its secrets

ProviderName = Literal["gemini", "groq"]


class Settings(BaseModel):
    """Validated service configuration. Field names map to UPPER_CASE env vars."""

    model_config = ConfigDict(frozen=True, extra="ignore")

    ai_service_secret: SecretStr
    gemini_api_key: SecretStr = SecretStr("")
    groq_api_key: SecretStr = SecretStr("")
    openalex_api_key: SecretStr = SecretStr("")
    semantic_scholar_api_key: SecretStr = SecretStr("")
    llm_provider_order: tuple[ProviderName, ...] = Field(default=("gemini", "groq"), min_length=1)

    @field_validator("ai_service_secret")
    @classmethod
    def _secret_is_real(cls, value: SecretStr) -> SecretStr:
        secret = value.get_secret_value()
        if "change-me" in secret:
            raise ValueError("AI_SERVICE_SECRET is still the placeholder; set the real value")
        if len(secret) < MIN_SECRET_LENGTH:
            raise ValueError(f"AI_SERVICE_SECRET must be at least {MIN_SECRET_LENGTH} characters")
        return value

    @field_validator("llm_provider_order", mode="before")
    @classmethod
    def _split_provider_order(cls, value: object) -> object:
        if isinstance(value, str):
            names = tuple(part.strip().lower() for part in value.split(",") if part.strip())
            if len(set(names)) != len(names):
                raise ValueError("LLM_PROVIDER_ORDER must not repeat a provider")
            return names
        return value


def read_env_file(path: Path) -> dict[str, str]:
    """Parse ``KEY=VALUE`` lines from a dotenv-style file.

    Skips blank lines and ``#`` comments, accepts an optional ``export`` prefix, strips
    matching surrounding quotes, and drops `` # comment`` tails from unquoted values.
    A missing file gives an empty dict.
    """
    values: dict[str, str] = {}
    if not path.is_file():
        return values
    for raw_line in path.read_text(encoding="utf-8-sig").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#"):
            continue
        if line.startswith("export "):
            line = line[len("export ") :].lstrip()
        key, sep, value = line.partition("=")
        key = key.strip()
        if not sep or not key:
            continue
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in {'"', "'"}:
            value = value[1:-1]
        else:
            value = value.split(" #", 1)[0].rstrip()
        values[key] = value
    return values


def load_settings(
    environ: Mapping[str, str] | None = None,
    env_file: Path | None = ENV_FILE,
) -> Settings:
    """Build settings from the env file, overridden by real environment variables."""
    merged: dict[str, str] = read_env_file(env_file) if env_file is not None else {}
    merged.update(os.environ if environ is None else environ)
    data = {
        name: merged[name.upper()]
        for name in Settings.model_fields
        if name.upper() in merged
    }
    return Settings.model_validate(data)


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    """Process-wide settings, loaded once."""
    return load_settings()
