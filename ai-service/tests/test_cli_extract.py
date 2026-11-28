from __future__ import annotations

import json
from pathlib import Path

import pytest

from app.tools.extract import main

PDFS = Path(__file__).parent / "fixtures" / "pdfs"
PDF = PDFS / "05-en-mixed-small-micro-enterprises-ethiopia.pdf"


def test_prints_json(capsys: pytest.CaptureFixture[str]) -> None:
    assert main([str(PDF)]) == 0
    data = json.loads(capsys.readouterr().out)
    assert data["metadata"]["doi"] == "10.1371/journal.pone.0320681"
    assert data["page_count"] == 19
    assert len(data["pages"]) == 19


def test_no_text_option(capsys: pytest.CaptureFixture[str]) -> None:
    assert main([str(PDF), "--no-text"]) == 0
    assert json.loads(capsys.readouterr().out)["pages"] == []


def test_invalid_pdf(tmp_path: Path, capsys: pytest.CaptureFixture[str]) -> None:
    bad = tmp_path / "bad.pdf"
    bad.write_bytes(b"not a pdf")
    assert main([str(bad)]) == 1
    assert "invalid_pdf" in capsys.readouterr().err


def test_missing_file(tmp_path: Path, capsys: pytest.CaptureFixture[str]) -> None:
    assert main([str(tmp_path / "nope.pdf")]) == 2
    assert "cannot read" in capsys.readouterr().err
