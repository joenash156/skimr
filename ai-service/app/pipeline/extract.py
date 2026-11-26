"""Structure and metadata from an intake document (spec 6.3 "Structure", brief B1).

- Sections: heading candidates (short lines that are bold, larger than body text, or
  numbered) matched against EN/FR/ES patterns.
- Metadata: the work's DOI from the first 2 pages, title from the largest font on page 1
  (skipping logos), year, best-effort authors, language.
- References: the reference section split into entries, each with DOI and year.
"""

from __future__ import annotations

import re
from collections import Counter
from dataclasses import dataclass

from app.pipeline.intake.pdf import Line, PdfDocument, join_hyphenated
from app.pipeline.textnorm import clean_line, fold
from app.schemas.extract import (
    Author,
    Language,
    PageText,
    PdfExtraction,
    Reference,
    Section,
    SectionKind,
    SourceMetadata,
)

# ---------------------------------------------------------------- sections

# Phrases are accent-free and lower-case (matched against ``fold(heading)``).
_SECTION_PHRASES: dict[SectionKind, tuple[str, ...]] = {
    "abstract": ("abstract", "resume", "resumen", "summary", "executive summary"),
    "introduction": ("introduction", "introduccion", "introducao", "background of the study"),
    "literature_review": (
        "literature review", "review of literature", "review of the literature",
        "theoretical framework", "theoretical background", "conceptual framework",
        "revue de la litterature", "revue de litterature", "cadre theorique", "cadre conceptuel",
        "marco teorico", "estado del arte", "revision de la literatura", "revision de literatura",
    ),
    "methods": (
        "methods", "method", "methodology", "materials and methods", "research design",
        "methodologie", "methode", "methodes", "cadre methodologique",
        "metodologia", "metodos", "metodo", "materiales y metodos",
    ),
    "results": ("results", "findings", "resultats", "resultados"),
    "discussion": ("discussion", "discussions", "discusion"),
    "conclusion": (
        "conclusion", "conclusions", "concluding remarks", "conclusiones",
    ),
    "references": (
        "references", "reference list", "bibliography", "bibliographie", "referencias",
        "references bibliographiques", "works cited", "literature cited", "bibliografia",
    ),
}
# One regex per kind: whole words, so "method" doesn't match inside "methodological".
def _phrase_regex(phrases: tuple[str, ...]) -> re.Pattern[str]:
    longest_first = sorted(phrases, key=len, reverse=True)
    return re.compile(r"\b(" + "|".join(re.escape(p) for p in longest_first) + r")\b")


_SECTION_RES: dict[SectionKind, re.Pattern[str]] = {
    kind: _phrase_regex(phrases) for kind, phrases in _SECTION_PHRASES.items()
}
_NUMBERING = re.compile(r"^(?:\d{1,2}(?:\.\d{1,2})*\.?|[ivxlc]{1,5}\.|[a-h][.)])\s+", re.IGNORECASE)
MAX_HEADING_WORDS = 8
MAX_HEADING_CHARS = 80
MAX_STANDALONE_WORDS = 4  # a short line alone in its block can be a heading without styling


def _heading_kind(line: Line, body_size: float, standalone: bool = False) -> SectionKind | None:
    """The section kind if ``line`` looks like a heading, else None.

    ``standalone``: the line is the only line of its text block. OCR text layers carry
    no bold or size information, so this is how their headings stand out.
    """
    text = line.text
    if len(text) > MAX_HEADING_CHARS or text.endswith(":"):
        return None
    if ":" in text and text.split(":", 1)[1].strip():
        return None  # "Methodology: A. Author" or "Results: we found..." is not a heading
    numbered = bool(_NUMBERING.match(text))
    core = fold(_NUMBERING.sub("", text)).strip(" .")
    words = core.split()
    if not words or len(words) > MAX_HEADING_WORDS:
        return None
    styled = (
        line.bold
        or line.size >= body_size + 1.0
        or numbered
        or (standalone and len(words) <= MAX_STANDALONE_WORDS)
    )
    best: tuple[int, SectionKind] | None = None
    for kind, pattern in _SECTION_RES.items():
        match = pattern.search(core)
        if match and (best is None or match.start() < best[0]):
            best = (match.start(), kind)
    if best is None:
        return None
    exact = any(core == phrase for phrase in _SECTION_PHRASES[best[1]])
    return best[1] if (styled or exact) else None


@dataclass(frozen=True)
class _Found:
    kind: SectionKind
    line: Line
    index: int  # position in the document's line list


def _find_headings(doc: PdfDocument) -> list[_Found]:
    """First heading of each kind, in document order."""
    found: dict[SectionKind, _Found] = {}
    lines = doc.lines()
    block_sizes = Counter((ln.page, ln.block) for ln in lines)
    for index, line in enumerate(lines):
        if line.repeated:
            continue
        standalone = block_sizes[(line.page, line.block)] == 1
        kind = _heading_kind(line, doc.body_size, standalone)
        if kind is not None and kind not in found:
            found[kind] = _Found(kind, line, index)
    return sorted(found.values(), key=lambda f: f.index)


def detect_sections(doc: PdfDocument) -> list[Section]:
    headings = _find_headings(doc)
    if not headings:
        return [Section(kind="body", heading="", page_start=1, page_end=doc.page_count, ordinal=0)]
    sections: list[Section] = []
    for ordinal, found in enumerate(headings):
        nxt = headings[ordinal + 1] if ordinal + 1 < len(headings) else None
        page_end = nxt.line.page if nxt is not None else doc.page_count
        sections.append(
            Section(
                kind=found.kind,
                heading=found.line.text,
                page_start=found.line.page,
                page_end=max(page_end, found.line.page),
                ordinal=ordinal,
            )
        )
    return sections


# ---------------------------------------------------------------- metadata

_DOI = re.compile(r"\b10\.\d{4,9}/[^\s\"'<>\[\]{}⟩]+", re.IGNORECASE)
_DOI_TRAILING = ".,;:)]}⟩'\""
MIN_TITLE_WORDS = 4  # skips logos such as "PLOS ONE" or a journal name in large type
_YEAR = r"(19[5-9]\d|20\d{2})"


def _first_pages_text(doc: PdfDocument, count: int = 2) -> str:
    return "\n".join(ln.text for ln in doc.lines() if ln.page <= count)


def find_doi(text: str) -> str | None:
    """The work's own DOI: the most frequent candidate, preferring full over truncated copies."""
    candidates = [m.group(0).rstrip(_DOI_TRAILING) for m in _DOI.finditer(text)]
    candidates = [c for c in candidates if "/" in c and len(c.split("/", 1)[1]) >= 2]
    if not candidates:
        return None
    counts: Counter[str] = Counter(c.lower() for c in candidates)
    original = {c.lower(): c for c in candidates}
    # A truncated copy (line break inside the DOI) counts towards the longer DOI it starts.
    for short in list(counts):
        for long in counts:
            if long != short and long.startswith(short):
                counts[long] += counts[short]
                counts[short] = 0
    best = max(counts, key=lambda k: (counts[k], len(k)))
    return original[best]


def find_title(doc: PdfDocument) -> str | None:
    lines = [ln for ln in doc.pages[0].lines if not ln.repeated] if doc.pages else []
    for size in sorted({ln.size for ln in lines}, reverse=True):
        run: list[Line] = []
        for ln in sorted(lines, key=lambda x: x.y0):
            if abs(ln.size - size) < 0.3:
                run.append(ln)
            elif run:
                break  # only the first contiguous run at this size
        title = clean_line(join_hyphenated("\n".join(ln.text for ln in run)).replace("\n", " "))
        if len(title.split()) >= MIN_TITLE_WORDS and not _DOI.search(title):
            return title
    return doc.metadata_title or None


_YEAR_RULES = (
    re.compile(r"to cite this version:.{0,400}?\b" + _YEAR + r"\b", re.IGNORECASE | re.DOTALL),
    re.compile(
        r"(?:published|publication|publi[ée]|publicad[oa]|fecha de publicaci[oó]n)\b[^\n]{0,40}?\b"
        + _YEAR + r"\b",
        re.IGNORECASE,
    ),
    re.compile(r"©\s*(?:the authors?\(?s?\)?\s*)?" + _YEAR + r"\b", re.IGNORECASE),
    re.compile(r"\b" + _YEAR + r"[.,]?\s*(?:vol(?:ume)?\.?|issue|n[uú]m\.?)\s*\d", re.IGNORECASE),
    re.compile(r"\(" + _YEAR + r"\)"),
)


def find_year(doc: PdfDocument) -> int | None:
    text = _first_pages_text(doc)
    for rule in _YEAR_RULES:
        match = rule.search(text)
        if match:
            return int(match.group(1))
    years = re.findall(r"\b" + _YEAR + r"\b", "\n".join(ln.text for ln in doc.pages[0].lines))
    return int(Counter(years).most_common(1)[0][0]) if years else None


_NAME_WORD = re.compile(
    r"^[A-ZÀ-ÖØ-Þ][\w'’.\-]*$|^(?:de|da|del|der|di|du|van|von|la|le|ben|bin|al|el|y)$"
)
_AUTHOR_SPLIT = re.compile(r"\s*(?:,|;|&|\band\b|\bet\b|\by\b)\s*")
# Affiliation digits, footnote marks and PLOS's ORCID "ID" glued to names ("KhanID1*").
_MARKERS = re.compile(r"ID(?=[\d*†‡,\s]|$)|[\d*†‡§¶#]+")


def _parse_author_line(text: str) -> list[Author]:
    cleaned = _MARKERS.sub(" ", text)
    authors: list[Author] = []
    for part in _AUTHOR_SPLIT.split(cleaned):
        words = part.split()
        if not words:
            continue
        if not 2 <= len(words) <= 5 or not all(_NAME_WORD.match(w) for w in words):
            return []
        authors.append(Author(given=" ".join(words[:-1]), family=words[-1]))
    return authors


def find_authors(doc: PdfDocument, title: str | None) -> list[Author]:
    """Best effort: the first name-like line after the title on page 1 (or the HAL cover)."""
    if not doc.pages or not title:
        return []
    lines = sorted((ln for ln in doc.pages[0].lines if not ln.repeated), key=lambda x: x.y0)
    title_words = set(fold(title).split())
    after_title = False
    checked = 0
    for ln in lines:
        words = set(fold(ln.text).split())
        if words and words <= title_words:
            after_title = True
            continue
        if not after_title:
            continue
        authors = _parse_author_line(ln.text)
        if authors:
            return authors
        checked += 1
        if checked >= 4:
            break
    return []


_STOPWORDS: dict[Language, frozenset[str]] = {
    "en": frozenset(
        ["the", "and", "of", "to", "in", "is", "that", "for", "with", "are", "this", "on",
         "as", "by", "be", "from"]
    ),
    "fr": frozenset(
        ["le", "la", "les", "des", "et", "du", "est", "que", "une", "dans", "pour", "sur",
         "par", "au", "aux", "ce", "qui"]
    ),
    "es": frozenset(
        ["el", "la", "los", "las", "de", "y", "que", "en", "del", "se", "por", "con", "para",
         "una", "es", "al"]
    ),
}


def detect_language(text: str) -> Language | None:
    words = re.findall(r"[a-zà-ÿ]+", text.casefold()[:20000])
    if len(words) < 30:
        return None
    scores = {lang: sum(w in stop for w in words) for lang, stop in _STOPWORDS.items()}
    lang = max(scores, key=lambda k: scores[k])
    return lang if scores[lang] >= 5 else None


# ---------------------------------------------------------------- references

# "12. Author..." or "[12] Author...", or the number alone on its line (text follows below).
_NUMBERED_REF = re.compile(r"^\s*(?:\[\d{1,3}\]|\d{1,3}\.)(?:\s+\S|\s*$)")
_AUTHOR_YEAR_START = re.compile(
    r"^(?:[A-ZÀ-ÖØ-Þ][\w'’\-]+(?:\s(?:de|da|del|van|von|le|la|ben|[A-ZÀ-ÖØ-Þ][\w'’\-]+)){0,3},\s"
    r"|[A-ZÀ-ÖØ-Þ][\w'’\-]+\s[A-Z]{1,3}[,.])"
)
_STOP_HEADINGS = re.compile(
    r"^(?:appendix|appendices|annex|annexe|anexo|supporting information|supplementary"
    r"|author contributions?|acknowledg|about the authors?|funding|declarations|notes)\b",
    re.IGNORECASE,
)
_REF_YEAR = re.compile(r"\(" + _YEAR + r"[a-z]?\)|\b" + _YEAR + r"[a-z]?\b")
MAX_REFERENCES = 500
MIN_REFERENCE_CHARS = 20


def _reference_lines(doc: PdfDocument, heading_index: int) -> list[Line]:
    out: list[Line] = []
    for ln in doc.lines()[heading_index + 1 :]:
        if ln.repeated:
            continue
        if (ln.bold or ln.size > doc.body_size + 1) and _STOP_HEADINGS.match(ln.text):
            break
        out.append(ln)
    return out


def _reference(raw: str) -> Reference | None:
    text = clean_line(raw)
    if len(text) < MIN_REFERENCE_CHARS:
        return None
    compact = re.sub(r"(10\.\d{4,9}/)\s+", r"\1", re.sub(r"(doi\.org/)\s+", r"\1", text))
    doi_match = _DOI.search(compact)
    year_match = _REF_YEAR.search(text)
    year = None
    if year_match:
        year = int(next(g for g in year_match.groups() if g))
    return Reference(
        raw_text=text[:1000],
        doi=doi_match.group(0).rstrip(_DOI_TRAILING) if doi_match else None,
        year=year,
    )


def split_references(lines: list[str]) -> list[Reference]:
    numbered = sum(bool(_NUMBERED_REF.match(t)) for t in lines) >= 3
    entries: list[list[str]] = []
    for text in lines:
        starts = _NUMBERED_REF.match(text) if numbered else _AUTHOR_YEAR_START.match(text)
        current_has_year = bool(entries) and bool(_REF_YEAR.search(" ".join(entries[-1])))
        if not entries or (starts and (numbered or current_has_year)):
            entries.append([text])
        else:
            entries[-1].append(text)
    references = []
    for entry in entries:
        ref = _reference(join_hyphenated("\n".join(entry)).replace("\n", " "))
        if ref is not None:
            references.append(ref)
        if len(references) >= MAX_REFERENCES:
            break
    return references


# ---------------------------------------------------------------- entry point


def extract_pdf(doc: PdfDocument) -> PdfExtraction:
    """Sections, metadata and references for an intake document. Pure: no network."""
    headings = _find_headings(doc)
    sections = detect_sections(doc)
    ref_heading = next((h for h in headings if h.kind == "references"), None)
    references = (
        split_references([ln.text for ln in _reference_lines(doc, ref_heading.index)])
        if ref_heading is not None
        else []
    )
    pages = [PageText(number=p.number, text=p.text) for p in doc.pages]
    title = None if doc.needs_ocr else find_title(doc)
    metadata = SourceMetadata(
        title=title,
        authors=find_authors(doc, title),
        year=None if doc.needs_ocr else find_year(doc),
        doi=None if doc.needs_ocr else find_doi(_first_pages_text(doc)),
        language=detect_language(" ".join(p.text for p in pages[:5])),
        page_count=doc.page_count,
    )
    return PdfExtraction(
        metadata=metadata,
        needs_ocr=doc.needs_ocr,
        page_count=doc.page_count,
        avg_chars_per_page=doc.avg_chars_per_page,
        sections=[] if doc.needs_ocr else sections,
        references=references,
        pages=pages,
    )
