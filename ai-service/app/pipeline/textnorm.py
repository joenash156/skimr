"""Small text helpers shared by intake and extraction."""

from __future__ import annotations

import re
import unicodedata

# Code points, not literal characters, so nothing invisible hides in the source.
_SPACE_CODES = [0x00A0, *range(0x2000, 0x200B), 0x202F, 0x205F, 0x3000, 0x0009]
_INVISIBLE_CODES = [0x00AD, 0x200B, 0x200C, 0x200D, 0x2060, 0xFEFF]  # soft hyphen, zero-width
_SPACES: dict[int, str | None] = dict.fromkeys(_SPACE_CODES, " ")
_INVISIBLE: dict[int, str | None] = dict.fromkeys(_INVISIBLE_CODES, None)
_LIGATURES: dict[int, str | None] = {
    0xFB00: "ff",
    0xFB01: "fi",
    0xFB02: "fl",
    0xFB03: "ffi",
    0xFB04: "ffl",
    0xFB05: "st",
    0xFB06: "st",
}
_TABLE = {**_SPACES, **_INVISIBLE, **_LIGATURES}
_MULTISPACE = re.compile(r" {2,}")


def clean_line(text: str) -> str:
    """Plain spaces, no invisible characters or ligatures, single spaces, trimmed."""
    return _MULTISPACE.sub(" ", text.translate(_TABLE)).strip()


def fold(text: str) -> str:
    """Lower-case and strip accents, for matching headings and keywords."""
    decomposed = unicodedata.normalize("NFKD", text.casefold())
    return "".join(ch for ch in decomposed if not unicodedata.combining(ch))
