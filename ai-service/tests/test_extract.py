from __future__ import annotations

import pytest

from app.pipeline.extract import (
    _heading_kind,
    detect_language,
    detect_sections,
    extract_pdf,
    find_doi,
    split_references,
)
from app.pipeline.intake.pdf import Line, read_pdf

from .pdf_factory import LOREM, FakePage, make_pdf

BODY = 10.0


def line(text: str, *, bold: bool = False, size: float = BODY) -> Line:
    return Line(page=1, block=0, text=text, size=size, bold=bold, y0=0, y1=10)


@pytest.mark.parametrize(
    ("text", "bold", "kind"),
    [
        ("3. Methodology", False, "methods"),  # numbered
        ("Research design and methodology", True, "methods"),
        ("2. Cadre méthodologique", False, "methods"),
        ("Metodología", True, "methods"),
        ("Résultats", True, "results"),
        ("Findings and discussion", True, "results"),  # first keyword wins
        ("Discusión y Conclusión", True, "discussion"),
        ("Discussion of findings", True, "discussion"),
        ("Marco Teoríco", True, "literature_review"),
        ("Literature review and hypotheses development", True, "literature_review"),
        ("RESUMEN", True, "abstract"),
        ("Bibliographie", True, "references"),
        ("Background of the study", True, "introduction"),
        ("References", False, "references"),  # plain, but the whole line is the phrase
    ],
)
def test_heading_kinds(text: str, bold: bool, kind: str) -> None:
    assert _heading_kind(line(text, bold=bold), BODY) == kind


@pytest.mark.parametrize(
    ("text", "bold"),
    [
        ("Methodology: Roziah Mohd Rasdi, Nordahlia Umar Baki.", True),  # contributor list
        ("Results:", True),  # structured-abstract label
        ("Our results show that cost was the main barrier for most firms in the sample", True),
        ("The methods we used", False),  # plain body text
        ("Limitations and future research", True),  # not a tracked section
        ("Methodological", True),  # whole words only
    ],
)
def test_not_headings(text: str, bold: bool) -> None:
    assert _heading_kind(line(text, bold=bold), BODY) is None


def test_larger_font_counts_as_styled() -> None:
    assert _heading_kind(line("Our methods", size=BODY + 2), BODY) == "methods"
    assert _heading_kind(line("Our methods"), BODY) is None


def test_short_standalone_line_counts_as_heading() -> None:
    assert _heading_kind(line("Quelques conclusions"), BODY, standalone=True) == "conclusion"
    assert _heading_kind(line("Quelques conclusions"), BODY, standalone=False) is None


def test_sections_get_pages_and_ordinals() -> None:
    pages = [
        FakePage(heading="1. Introduction", body=[LOREM] * 3),
        FakePage(heading="2. Methods", body=[LOREM] * 3),
        FakePage(body=[LOREM] * 3),
        FakePage(heading="References", body=["Smith, J. (2020). A study. Journal, 1, 1-2."]),
    ]
    sections = detect_sections(read_pdf(make_pdf(pages)))
    assert [(s.kind, s.page_start, s.page_end, s.ordinal) for s in sections] == [
        ("introduction", 1, 2, 0),
        ("methods", 2, 4, 1),
        ("references", 4, 4, 2),
    ]


def test_no_headings_gives_one_body_section() -> None:
    sections = detect_sections(read_pdf(make_pdf([FakePage(body=[LOREM] * 3)] * 2)))
    assert [(s.kind, s.page_start, s.page_end) for s in sections] == [("body", 1, 2)]


def test_only_the_first_heading_of_a_kind_counts() -> None:
    pages = [FakePage(heading="Results", body=[LOREM]), FakePage(heading="Results", body=[LOREM])]
    sections = detect_sections(read_pdf(make_pdf(pages)))
    assert [(s.kind, s.page_start) for s in sections] == [("results", 1)]


@pytest.mark.parametrize(
    ("text", "doi"),
    [
        ("https://doi.org/10.1371/journal.pone.0247320", "10.1371/journal.pone.0247320"),
        # a copy broken at a line break counts towards the full DOI
        ("doi.org/10.1371/journal\n.pone.0247320 ... doi.org/10.1371/journal.pone.0247320",
         "10.1371/journal.pone.0247320"),
        ("⟨10.34699/rido.2024.37⟩.", "10.34699/rido.2024.37"),
        ("DOI: 10.1186/s43093-026-01024-z,", "10.1186/s43093-026-01024-z"),
        ("no identifier here, only 10.5 per cent", None),
    ],
)
def test_find_doi(text: str, doi: str | None) -> None:
    assert find_doi(text) == doi


def test_find_doi_prefers_the_most_frequent() -> None:
    text = "10.1111/own.1 header\n10.1111/own.1 footer\ncites 10.2222/other.9"
    assert find_doi(text) == "10.1111/own.1"


def test_split_numbered_references() -> None:
    lines = [
        "1. Fan M, Qalati SA. Effects of orientation. PLoS ONE. 2021;16(4):e0247320.",
        "https://doi.org/10.1371/ journal.pone.0247320",
        "2.",
        "Smith J. Another paper about small firms. J Bus. 2019;3:1-9.",
        "3. Doe A. Third paper on adoption. Res Pol. 2020;1:2-3. doi:10.1000/xyz123",
    ]
    refs = split_references(lines)
    assert len(refs) == 3
    assert refs[0].doi == "10.1371/journal.pone.0247320"  # space after the slash removed
    assert refs[0].year == 2021
    assert refs[1].raw_text.startswith("2. Smith J.")
    assert refs[2].doi == "10.1000/xyz123"


def test_split_author_year_references() -> None:
    lines = [
        "Mensah, A. (2021). Digital tools and small firms in Ghana: a survey of",
        "owner-managers. Journal of African Business, 22(3), 1-20.",
        "Boateng, K., & Diallo, A. (2024). Readiness of West African firms.",
        "Sahel Policy Lab. https://doi.org/10.5555/sahel.2024.1",
    ]
    refs = split_references(lines)
    assert [r.year for r in refs] == [2021, 2024]
    assert refs[0].raw_text.endswith("Journal of African Business, 22(3), 1-20.")
    assert refs[1].doi == "10.5555/sahel.2024.1"


def test_split_references_drops_fragments() -> None:
    assert split_references(["1. Short.", "2. Also tiny", "3. x"]) == []


@pytest.mark.parametrize(
    ("text", "lang"),
    [
        ("The survey shows that the cost of the tools is the main barrier for firms " * 3, "en"),
        ("Les dirigeants des PME sont pour la plupart des hommes et la culture est " * 3, "fr"),
        ("La adopción de la tecnología en las empresas es un factor para el sector " * 3, "es"),
        ("too short", None),
    ],
)
def test_detect_language(text: str, lang: str | None) -> None:
    assert detect_language(text) == lang


def test_scanned_pdf_skips_metadata_and_sections() -> None:
    result = extract_pdf(read_pdf(make_pdf([FakePage(), FakePage()])))
    assert result.needs_ocr
    assert result.metadata.title is None
    assert result.sections == []
    assert result.metadata.page_count == 2
