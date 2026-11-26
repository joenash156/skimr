"""B1 acceptance on the 10 fixture PDFs (brief B1; ground truth in fixtures/pdfs/expected.json).

- at least 9 of 10 titles correct
- every DOI printed in the PDF is found (and no wrong DOI for the others)
- at least 80% of the listed sections detected (kind and start page)
- the scanned PDF is flagged needs_ocr
"""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

import pytest

from app.pipeline.extract import extract_pdf
from app.pipeline.intake.pdf import read_pdf
from app.schemas.extract import PdfExtraction

PDFS = Path(__file__).parent / "fixtures" / "pdfs"
EXPECTED: dict[str, dict[str, Any]] = json.loads(
    (PDFS / "expected.json").read_text(encoding="utf-8")
)["files"]


def norm(text: str | None) -> str:
    return " ".join((text or "").replace("’", "'").replace("‘", "'").casefold().split())


@pytest.fixture(scope="module")
def results() -> dict[str, PdfExtraction]:
    return {name: extract_pdf(read_pdf((PDFS / name).read_bytes())) for name in EXPECTED}


def test_titles_at_least_9_of_10(results: dict[str, PdfExtraction]) -> None:
    correct = [
        n for n, e in EXPECTED.items() if norm(results[n].metadata.title) == norm(e["title"])
    ]
    wrong = {n: results[n].metadata.title for n in EXPECTED if n not in correct}
    assert len(correct) >= 9, wrong


def test_every_printed_doi_is_found(results: dict[str, PdfExtraction]) -> None:
    for name, e in EXPECTED.items():
        found = results[name].metadata.doi
        if e["doi_in_pdf"]:
            assert found is not None and found.lower() == e["doi_in_pdf"].lower(), name
        else:
            assert found is None, f"{name}: picked up {found} (not the work's DOI)"


def test_at_least_80_percent_of_sections(results: dict[str, PdfExtraction]) -> None:
    total = hit = 0
    missing: list[str] = []
    for name, e in EXPECTED.items():
        got = {(s.kind, s.page_start) for s in results[name].sections}
        for s in e["sections"] or []:
            total += 1
            if (s["kind"], s["page_start"]) in got:
                hit += 1
            else:
                missing.append(f"{name}: {s['kind']} p{s['page_start']}")
    assert total > 0
    assert hit / total >= 0.80, missing


def test_scanned_pdf_needs_ocr_and_others_do_not(results: dict[str, PdfExtraction]) -> None:
    for name, e in EXPECTED.items():
        assert results[name].needs_ocr is e["needs_ocr"], name


def test_page_counts(results: dict[str, PdfExtraction]) -> None:
    for name, e in EXPECTED.items():
        assert results[name].page_count == e["page_count"], name
        assert len(results[name].pages) == e["page_count"], name


def test_language_of_text_pdfs(results: dict[str, PdfExtraction]) -> None:
    for name, e in EXPECTED.items():
        if not e["needs_ocr"]:
            assert results[name].metadata.language == e["language"], name


def test_years_mostly_right(results: dict[str, PdfExtraction]) -> None:
    text_pdfs = [n for n, e in EXPECTED.items() if not e["needs_ocr"]]
    right = [n for n in text_pdfs if results[n].metadata.year == EXPECTED[n]["year"]]
    assert len(right) >= len(text_pdfs) - 1, {n: results[n].metadata.year for n in text_pdfs}


def test_reference_lists_are_split(results: dict[str, PdfExtraction]) -> None:
    for name, e in EXPECTED.items():
        has_refs = any(s["kind"] == "references" for s in e["sections"] or [])
        if has_refs:
            assert len(results[name].references) >= 10, name


def test_running_headers_removed_from_page_text(results: dict[str, PdfExtraction]) -> None:
    tunisia = results["06-fr-survey-identites-dirigeants-pme-tunisie.pdf"]
    body_pages = tunisia.pages[2:]
    assert not any("Revue Interdisciplinaire Droit et Organisations" in p.text for p in body_pages)
