"""PDF intake: text page by page with PyMuPDF (spec 6.2, brief B1).

- Repeated running headers and footers (lines in the top or bottom margin of more than
  half the pages, page numbers ignored) are marked and left out of the page text.
- Words hyphenated across a line break are joined.
- ``needs_ocr`` is set when there are fewer than 200 text characters per page on
  average: the PDF is treated as a scan (OCR itself arrives in B5).

The file is untrusted: PyMuPDF only parses it, nothing in it is executed, and image
data is never loaded (text-only extraction flags).
"""

from __future__ import annotations

import re
from collections import Counter
from dataclasses import dataclass, field, replace
from typing import Any

import pymupdf

from app.pipeline.textnorm import clean_line

NEEDS_OCR_CHARS_PER_PAGE = 200
REPEAT_FRACTION = 0.5  # a line on more than half the pages is a running header/footer
MARGIN_FRACTION = 0.12  # ...but only when it sits in the top or bottom 12% of the page
MIN_PAGES_FOR_REPEATS = 3

_BOLD_FONT = re.compile(r"bold|black|heavy|semibold|demi", re.IGNORECASE)
_DIGITS = re.compile(r"\d+")
# letter + "-" + line break + lower-case letter: a word split across lines
_HYPHEN_BREAK = re.compile(r"([^\W\d_])-\n([^\W\d_])")


class PdfError(Exception):
    """The file can't be read as a PDF. ``code`` matches the contract's error codes."""

    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass(frozen=True)
class Line:
    page: int  # 1-based
    block: int
    text: str
    size: float
    bold: bool
    y0: float
    y1: float
    repeated: bool = False  # running header or footer


@dataclass
class PdfPage:
    number: int  # 1-based
    width: float
    height: float
    lines: list[Line] = field(default_factory=list)

    @property
    def text(self) -> str:
        """Clean page text: blocks separated by blank lines, headers/footers removed."""
        blocks: dict[int, list[str]] = {}
        for line in self.lines:
            if not line.repeated:
                blocks.setdefault(line.block, []).append(line.text)
        return join_hyphenated("\n\n".join("\n".join(rows) for rows in blocks.values()))


@dataclass
class PdfDocument:
    pages: list[PdfPage]
    metadata_title: str
    body_size: float

    @property
    def page_count(self) -> int:
        return len(self.pages)

    @property
    def avg_chars_per_page(self) -> int:
        chars = sum(len(line.text.replace(" ", "")) for page in self.pages for line in page.lines)
        return chars // max(self.page_count, 1)

    @property
    def needs_ocr(self) -> bool:
        return self.avg_chars_per_page < NEEDS_OCR_CHARS_PER_PAGE

    def lines(self) -> list[Line]:
        return [line for page in self.pages for line in page.lines]


def join_hyphenated(text: str) -> str:
    """Join ``word-\\nbreak`` into ``wordbreak`` when the next line starts in lower case."""

    def _join(match: re.Match[str]) -> str:
        first, second = match.group(1), match.group(2)
        return f"{first}{second}" if second.islower() else match.group(0)

    return _HYPHEN_BREAK.sub(_join, text)


def read_pdf(data: bytes) -> PdfDocument:
    """Parse PDF bytes. Raises ``PdfError`` (``invalid_pdf`` / ``encrypted_pdf``)."""
    if not data.startswith(b"%PDF-"):
        raise PdfError("invalid_pdf", "The file is not a PDF")
    try:
        doc = pymupdf.open(stream=data, filetype="pdf")  # type: ignore[no-untyped-call]  # untyped lib
    except Exception as exc:
        raise PdfError("invalid_pdf", "The PDF could not be opened") from exc
    with doc:
        if doc.needs_pass:
            raise PdfError("encrypted_pdf", "The PDF is password-protected")
        if doc.page_count == 0:
            raise PdfError("invalid_pdf", "The PDF has no pages")
        try:
            pages = [_read_page(doc[index], index + 1) for index in range(doc.page_count)]
        except Exception as exc:
            raise PdfError("invalid_pdf", "The PDF is damaged") from exc
        metadata = doc.metadata or {}
        title = clean_line(str(metadata.get("title") or ""))
    _mark_repeated(pages)
    return PdfDocument(pages=pages, metadata_title=title, body_size=_body_size(pages))


def _read_page(page: Any, number: int) -> PdfPage:
    rect = page.rect
    result = PdfPage(number=number, width=float(rect.width), height=float(rect.height))
    content = page.get_text("dict", flags=pymupdf.TEXTFLAGS_TEXT)
    for block_no, block in enumerate(content.get("blocks", [])):
        if block.get("type") != 0:
            continue
        for raw_line in block.get("lines", []):
            spans = [s for s in raw_line.get("spans", []) if s.get("text", "").strip()]
            if not spans:
                continue
            text = clean_line("".join(s["text"] for s in raw_line["spans"]))
            if not text:
                continue
            main = max(spans, key=lambda s: len(s["text"].strip()))
            flags = int(main.get("flags", 0))
            bold = bool(flags & 16) or bool(_BOLD_FONT.search(main.get("font", "")))
            _, y0, _, y1 = raw_line["bbox"]
            result.lines.append(
                Line(
                    page=number,
                    block=block_no,
                    text=text,
                    size=round(float(main.get("size", 0.0)), 1),
                    bold=bold,
                    y0=float(y0),
                    y1=float(y1),
                )
            )
    return result


def _repeat_key(text: str) -> str:
    return _DIGITS.sub("#", text.casefold())


def _in_margin(line: Line, height: float) -> bool:
    margin = height * MARGIN_FRACTION
    return line.y1 <= margin or line.y0 >= height - margin


def _mark_repeated(pages: list[PdfPage]) -> None:
    if len(pages) < MIN_PAGES_FOR_REPEATS:
        return
    counts: Counter[str] = Counter()
    for page in pages:
        counts.update({_repeat_key(ln.text) for ln in page.lines if _in_margin(ln, page.height)})
    threshold = REPEAT_FRACTION * len(pages)
    repeated = {key for key, count in counts.items() if count > threshold}
    if not repeated:
        return
    for page in pages:
        page.lines = [
            replace(ln, repeated=True)
            if _in_margin(ln, page.height) and _repeat_key(ln.text) in repeated
            else ln
            for ln in page.lines
        ]


def _body_size(pages: list[PdfPage]) -> float:
    """The most common font size, weighted by characters."""
    sizes: Counter[float] = Counter()
    for page in pages:
        for line in page.lines:
            sizes[line.size] += len(line.text)
    return sizes.most_common(1)[0][0] if sizes else 0.0
