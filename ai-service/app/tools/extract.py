"""Print what offline extraction finds in a PDF, as JSON.

Usage::

    python -m app.tools.extract path/to/file.pdf [--no-text]

Needs no secrets and makes no network calls.
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

from app.pipeline.extract import extract_pdf
from app.pipeline.intake.pdf import PdfError, read_pdf


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="python -m app.tools.extract", description=__doc__)
    parser.add_argument("pdf", type=Path, help="path to a PDF file")
    parser.add_argument("--no-text", action="store_true", help="leave out the page text")
    args = parser.parse_args(argv)

    try:
        data = args.pdf.read_bytes()
    except OSError as exc:
        print(f"error: cannot read {args.pdf}: {exc.strerror}", file=sys.stderr)
        return 2
    try:
        result = extract_pdf(read_pdf(data))
    except PdfError as exc:
        print(f"error: {exc.code}: {exc.message}", file=sys.stderr)
        return 1
    if args.no_text:
        result.pages = []
    # Windows consoles default to cp1252, which can't print French/Spanish text or "⟩".
    reconfigure = getattr(sys.stdout, "reconfigure", None)
    if callable(reconfigure):
        reconfigure(encoding="utf-8")
    print(result.model_dump_json(indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
