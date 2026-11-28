from __future__ import annotations

import pymupdf
import pytest

from app.pipeline.intake.pdf import PdfError, join_hyphenated, read_pdf
from app.pipeline.textnorm import clean_line, fold

from .pdf_factory import LOREM, FakePage, make_pdf


def test_reads_text_page_by_page() -> None:
    doc = read_pdf(make_pdf([FakePage(body=[f"Page one {LOREM}"]), FakePage(body=["Page two."])]))
    assert doc.page_count == 2
    assert "Page one" in doc.pages[0].text
    assert doc.pages[1].text == "Page two."
    assert [p.number for p in doc.pages] == [1, 2]


def test_running_headers_and_footers_are_removed() -> None:
    pages = [
        FakePage(header="Journal of Testing · Vol. 3", footer=f"Page {n} of 4", body=[LOREM] * 8)
        for n in range(1, 5)
    ]
    doc = read_pdf(make_pdf(pages))
    for page in doc.pages:
        assert "Journal of Testing" not in page.text
        assert "of 4" not in page.text
        assert LOREM in page.text


def test_repeated_line_in_the_body_is_kept() -> None:
    pages = [FakePage(middle="Table 1. Sample by country", body=[LOREM]) for _ in range(4)]
    doc = read_pdf(make_pdf(pages))
    assert all("Table 1. Sample by country" in p.text for p in doc.pages)


def test_header_on_half_the_pages_or_fewer_is_kept() -> None:
    pages = [FakePage(header="Draft", body=[LOREM]), FakePage(header="Draft", body=[LOREM])]
    pages += [FakePage(body=[LOREM]), FakePage(body=[LOREM])]
    doc = read_pdf(make_pdf(pages))
    assert "Draft" in doc.pages[0].text


def test_short_documents_keep_their_headers() -> None:
    pages = [FakePage(header="Journal of Testing", body=[LOREM]) for _ in range(2)]
    assert all("Journal of Testing" in p.text for p in read_pdf(make_pdf(pages)).pages)


@pytest.mark.parametrize(
    ("text", "expected"),
    [
        ("entre-\npreneurship", "entrepreneurship"),
        ("in the méth-\nodologie", "in the méthodologie"),
        ("COVID-\n19 pandemic", "COVID-\n19 pandemic"),  # digit: keep
        ("Anglo-\nSaxon", "Anglo-\nSaxon"),  # capital: a real compound, keep
        ("well-known", "well-known"),  # no line break
    ],
)
def test_join_hyphenated(text: str, expected: str) -> None:
    assert join_hyphenated(text) == expected


def test_text_pdf_does_not_need_ocr() -> None:
    doc = read_pdf(make_pdf([FakePage(body=[LOREM] * 4)]))
    assert doc.avg_chars_per_page >= 200
    assert not doc.needs_ocr


def test_almost_empty_pdf_needs_ocr() -> None:
    doc = read_pdf(make_pdf([FakePage(body=["12"]), FakePage(), FakePage()]))
    assert doc.avg_chars_per_page < 200
    assert doc.needs_ocr


@pytest.mark.parametrize("data", [b"", b"hello world", b"<html>not a pdf</html>"])
def test_not_a_pdf(data: bytes) -> None:
    with pytest.raises(PdfError) as excinfo:
        read_pdf(data)
    assert excinfo.value.code == "invalid_pdf"


def test_damaged_pdf() -> None:
    with pytest.raises(PdfError) as excinfo:
        read_pdf(b"%PDF-1.7\n" + b"\x00garbage" * 50)
    assert excinfo.value.code == "invalid_pdf"


def test_password_protected_pdf() -> None:
    data = make_pdf(
        [FakePage(body=[LOREM])],
        encryption=pymupdf.PDF_ENCRYPT_AES_256,
        user_pw="user-secret",
        owner_pw="owner-secret",
    )
    with pytest.raises(PdfError) as excinfo:
        read_pdf(data)
    assert excinfo.value.code == "encrypted_pdf"


def test_owner_only_encryption_is_readable() -> None:
    data = make_pdf(
        [FakePage(body=[LOREM])], encryption=pymupdf.PDF_ENCRYPT_AES_256, owner_pw="owner-secret"
    )
    assert LOREM in read_pdf(data).pages[0].text


def test_invisible_characters_and_ligatures_are_cleaned() -> None:
    # Tested on the helper: PDF base fonts can't even encode these characters.
    ffi, nbsp, zero_width, soft_hyphen, tab = chr(0xFB03), chr(0xA0), chr(0x200B), chr(0xAD), chr(9)
    raw = f"e{ffi}cient{nbsp}and{zero_width}clean{soft_hyphen}  text{tab}"
    assert clean_line(raw) == "efficient andclean text"


def test_fold_strips_accents_and_case() -> None:
    assert fold("Méthodologie RÉSULTATS Teoríco") == "methodologie resultats teorico"
