"""Structure and metadata extracted from a source (contract: docs/api/ai-service.md, 6.1)."""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, Field

Language = Literal["en", "fr", "es"]
SectionKind = Literal[
    "abstract",
    "introduction",
    "literature_review",
    "methods",
    "results",
    "discussion",
    "conclusion",
    "references",
    "body",
    "other",
]
WorkType = Literal["journal_article", "book", "chapter", "report", "web_page", "thesis", "other"]


class Author(BaseModel):
    given: str = ""
    family: str = ""


class SourceMetadata(BaseModel):
    title: str | None = None
    authors: list[Author] = Field(default_factory=list)
    year: int | None = None
    doi: str | None = None
    openalex_id: str | None = None
    url: str | None = None
    venue: str | None = None
    publisher: str | None = None
    site_name: str | None = None
    published_date: str | None = None
    page_range: str | None = None
    abstract: str = ""
    language: Language | None = None
    work_type: WorkType | None = None
    is_peer_reviewed: bool | None = None
    page_count: int | None = None


class Section(BaseModel):
    kind: SectionKind
    heading: str
    page_start: int = Field(ge=1)
    page_end: int = Field(ge=1)
    ordinal: int = Field(ge=0)


class Reference(BaseModel):
    raw_text: str
    doi: str | None = None
    title: str | None = None
    year: int | None = None


class PageText(BaseModel):
    number: int = Field(ge=1)
    text: str


class PdfExtraction(BaseModel):
    """Result of offline PDF extraction (B1). B2 wraps it into the extract_source result."""

    metadata: SourceMetadata
    needs_ocr: bool
    page_count: int
    avg_chars_per_page: int
    sections: list[Section]
    references: list[Reference]
    pages: list[PageText]
