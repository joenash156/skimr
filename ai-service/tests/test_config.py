from __future__ import annotations

from pathlib import Path

import pytest
from pydantic import ValidationError

from app.config import load_settings, read_env_file

GOOD_SECRET = "s" * 32


def test_reads_values_from_environ() -> None:
    settings = load_settings(
        environ={"AI_SERVICE_SECRET": GOOD_SECRET, "LLM_PROVIDER_ORDER": " Groq , gemini "},
        env_file=None,
    )
    assert settings.ai_service_secret.get_secret_value() == GOOD_SECRET
    assert settings.llm_provider_order == ("groq", "gemini")
    assert settings.gemini_api_key.get_secret_value() == ""


def test_default_provider_order() -> None:
    settings = load_settings(environ={"AI_SERVICE_SECRET": GOOD_SECRET}, env_file=None)
    assert settings.llm_provider_order == ("gemini", "groq")


def test_environ_overrides_env_file(tmp_path: Path) -> None:
    env_file = tmp_path / ".env"
    env_file.write_text(
        f"AI_SERVICE_SECRET={'f' * 40}\nGROQ_API_KEY=from-file\n", encoding="utf-8"
    )
    settings = load_settings(environ={"AI_SERVICE_SECRET": GOOD_SECRET}, env_file=env_file)
    assert settings.ai_service_secret.get_secret_value() == GOOD_SECRET
    assert settings.groq_api_key.get_secret_value() == "from-file"


def test_secrets_are_hidden_in_repr() -> None:
    settings = load_settings(
        environ={"AI_SERVICE_SECRET": GOOD_SECRET, "GEMINI_API_KEY": "real-key"},
        env_file=None,
    )
    text = repr(settings) + str(settings)
    assert GOOD_SECRET not in text
    assert "real-key" not in text


@pytest.mark.parametrize(
    "environ",
    [
        {},  # missing
        {"AI_SERVICE_SECRET": ""},
        {"AI_SERVICE_SECRET": "short"},
        {"AI_SERVICE_SECRET": "change-me-shared-hmac-secret-padded-to-length"},
    ],
)
def test_rejects_missing_weak_or_placeholder_secret(environ: dict[str, str]) -> None:
    with pytest.raises(ValidationError):
        load_settings(environ=environ, env_file=None)


@pytest.mark.parametrize("order", ["", " , ", "gemini,gemini", "openai"])
def test_rejects_bad_provider_order(order: str) -> None:
    with pytest.raises(ValidationError):
        load_settings(
            environ={"AI_SERVICE_SECRET": GOOD_SECRET, "LLM_PROVIDER_ORDER": order},
            env_file=None,
        )


def test_read_env_file_parsing(tmp_path: Path) -> None:
    env_file = tmp_path / ".env"
    env_file.write_text(
        "\n".join(
            [
                "# a comment",
                "",
                "PLAIN=value",
                'DOUBLE="quoted # not a comment"',
                "SINGLE='single'",
                "export EXPORTED=yes",
                "TRAILING=abc   # inline comment",
                "EMPTY=",
                "no_equals_sign",
                "=no_key",
            ]
        ),
        encoding="utf-8",
    )
    assert read_env_file(env_file) == {
        "PLAIN": "value",
        "DOUBLE": "quoted # not a comment",
        "SINGLE": "single",
        "EXPORTED": "yes",
        "TRAILING": "abc",
        "EMPTY": "",
    }


def test_read_env_file_missing(tmp_path: Path) -> None:
    assert read_env_file(tmp_path / "nope.env") == {}
