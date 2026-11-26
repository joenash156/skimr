"""Keep fixture files and their expected.json in sync (no PDF library needed)."""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

FIXTURES = Path(__file__).parent / "fixtures"


def load(folder: str) -> dict[str, Any]:
    data = json.loads((FIXTURES / folder / "expected.json").read_text(encoding="utf-8"))
    assert isinstance(data, dict)
    return data


def test_every_listed_pdf_exists_and_is_a_pdf() -> None:
    files = load("pdfs")["files"]
    assert len(files) == 10
    for name in files:
        path = FIXTURES / "pdfs" / name
        assert path.is_file(), name
        assert path.read_bytes()[:5] == b"%PDF-", name


def test_no_unlisted_pdfs() -> None:
    listed = set(load("pdfs")["files"])
    on_disk = {p.name for p in (FIXTURES / "pdfs").glob("*.pdf")}
    assert on_disk == listed


def test_pdf_set_meets_the_brief() -> None:
    files = load("pdfs")["files"].values()
    languages = [f["language"] for f in files]
    assert languages.count("fr") >= 2
    assert languages.count("es") >= 1
    assert sum(f["needs_ocr"] for f in files) == 1
    designs = {f["design"] for f in files}
    assert {"survey", "qualitative", "mixed_methods"} <= designs


def test_every_listed_web_page_exists() -> None:
    files = load("web")["files"]
    assert len(files) == 5
    for name, spec in files.items():
        assert (FIXTURES / "web" / name).is_file(), name
        assert spec["status"] in {"ready", "unreadable"}
        assert (spec["status"] == "unreadable") == (spec["error_code"] is not None)


def test_web_must_contain_phrases_are_in_the_html() -> None:
    for name, spec in load("web")["files"].items():
        html = (FIXTURES / "web" / name).read_text(encoding="utf-8")
        for phrase in spec.get("must_contain", []) + spec.get("must_not_contain", []):
            assert phrase in html, f"{name}: {phrase!r}"


def test_image_sets_are_described() -> None:
    data = load("images")
    assert set(data["sets"]) == {"good-light", "poor-light", "tilted"}
    assert [s["sheet"] for s in data["sheets"]] == [1, 2, 3]
    assert (FIXTURES / "images" / "to-photograph.pdf").is_file()
    for name, spec in data["sets"].items():
        if spec["status"] == "ready":
            for sheet in (1, 2, 3):
                assert (FIXTURES / "images" / name / f"sheet-{sheet}.jpg").is_file()
