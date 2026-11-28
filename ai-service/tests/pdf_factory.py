"""Build small PDFs in memory for unit tests."""

from __future__ import annotations

from dataclasses import dataclass, field

import pymupdf


@dataclass
class FakePage:
    body: list[str] = field(default_factory=list)  # one line each, regular font
    header: str | None = None  # top margin
    footer: str | None = None  # bottom margin
    middle: str | None = None  # a line in the middle of the page
    heading: str | None = None  # bold, larger, above the body


def make_pdf(pages: list[FakePage], **save_options: object) -> bytes:
    doc = pymupdf.open()
    for spec in pages:
        page = doc.new_page(width=595, height=842)
        if spec.header:
            page.insert_text((72, 40), spec.header, fontsize=9)
        if spec.heading:
            page.insert_text((72, 120), spec.heading, fontsize=14, fontname="hebo")
        y = 160.0
        for line in spec.body:
            page.insert_text((72, y), line, fontsize=11)
            y += 16
        if spec.middle:
            page.insert_text((72, 420), spec.middle, fontsize=11)
        if spec.footer:
            page.insert_text((72, 815), spec.footer, fontsize=9)
    data = doc.tobytes(**save_options)
    doc.close()
    return bytes(data)


LOREM = "Small firms in the survey reported that cost was the main barrier to adopting new tools."
