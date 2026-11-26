# AI service contract (PHP ⇄ Python)

| | |
| --- | --- |
| **Status** | **Draft v0.1**, waiting for P2's sign-off. It is frozen at the end of B0. |
| **Owners** | P3 (Kwantsimah) and P2 (Prince); changes need both (see `.github/CODEOWNERS`). |
| **Source of truth** | Master spec §6, §10.3, §11.2 and §9.2. If this file and the spec disagree, the spec wins; tell P1. |
| **Code** | Pydantic models in `ai-service/app/schemas/` mirror this file. |

Only PHP calls this service. Python never calls PHP and never touches the database (spec §6).

**Items marked ❓ are proposals P2 needs to agree or change before we freeze.**

---

## 1. Transport

- Base URL: `AI_SERVICE_URL`. Locally `http://localhost:8000`; staging `https://skimr-ai.onrender.com`.
- Requests and responses are JSON, UTF-8, `Content-Type: application/json`.
- Render's free tier sleeps after 15 minutes idle and takes about 1 minute to wake. `GET /health` wakes it (spec §17.3).
- ❓ **Request size:** PHP keeps every request body under **2 MB**. The largest expected request is `cluster_project` or `synthesize`, at roughly 300 KB.

## 2. Authentication (HMAC)

Every request except `GET /health` carries two headers:

| Header | Value |
| --- | --- |
| `X-Timestamp` | Current Unix time in **whole seconds**, e.g. `1700000000` |
| `X-Signature` | `hex(HMAC-SHA256(AI_SERVICE_SECRET, X-Timestamp + "." + raw_body))`, **lowercase hex**, 64 characters |

- `raw_body` is the exact bytes sent. Sign the body string *after* `json_encode`, and send exactly that string. A GET has an empty body, so the message is `"1700000000."`.
- Python refuses the request if the timestamp is more than **300 seconds** off its own clock, in either direction.
- `AI_SERVICE_SECRET` is the same value on both sides: 64 random hex characters (at least 32 characters). Python refuses to start with a placeholder.

PHP reference:

```php
$body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$ts   = (string) time();
$sig  = hash_hmac('sha256', $ts . '.' . $body, $secret);   // lowercase hex by default
// send $body unchanged, with X-Timestamp: $ts and X-Signature: $sig
```

**Test vector.** Python has a unit test for it (`ai-service/tests/test_security.py`); P2 should add the same test in PHP. The secret is a test-only value.

```
secret    = test-secret-0123456789abcdef0123456789abcdef
timestamp = 1700000000
body      = {"type":"ping"}
signature = ab9499695086d393f3e0d63badae2ec8de73efedc7e8ceb06e04ebe7ef355623
```

## 3. Errors

Every non-2xx response has the same shape. Messages never contain secrets, header values or source text.

```json
{"error": {"code": "invalid_signature", "message": "X-Signature does not match"}}
```

| HTTP | `code` | Meaning, and what PHP does |
| --- | --- | --- |
| 401 | `missing_signature`, `invalid_timestamp`, `expired_timestamp`, `invalid_signature` | Signing bug or clock skew. Don't retry; log and alert. |
| 404 | `job_not_found` | The job id is unknown: Python restarted, or the job finished more than 30 minutes ago. **Resubmit** (at most 2 times, spec §6.1). |
| 422 | `invalid_request` | Body doesn't match the schema. The message names the field. A bug: don't retry. |
| 422 | `unknown_job_type` | Job type not implemented (yet). |
| 5xx | – | Render waking up or crashing. Retry later; the job may be lost (then 404). |

## 4. Endpoints

### `GET /health` (open)

`200 {"status": "ok"}`

### `POST /v1/jobs`

```json
{"type": "extract_source", "payload": { ... }, "callback": "none"}
```

- `type` is one of the job types in §6.
- `callback` is optional and may only be `"none"` or `null`. PHP always polls.
- No other top-level fields are allowed.

Response `202 {"job_id": "3f2b0c1e9a8d4f6b8c7d6e5f4a3b2c1d"}`. The id is 32 lowercase hex characters; PHP stores it in `ai_jobs.remote_job_id`.

### `GET /v1/jobs/{job_id}`

```json
{
  "status": "succeeded",
  "progress": 100,
  "result": { ... },
  "usage": [
    {"provider": "gemini", "model": "gemini-2.5-flash", "tokens_in": 5120, "tokens_out": 830,
     "latency_ms": 2140, "outcome": "ok"}
  ]
}
```

| Field | Notes |
| --- | --- |
| `status` | `queued`, `running`, `succeeded` or `failed` |
| `progress` | 0–100 |
| `result` | **Only** when `succeeded`. Its shape depends on the job type (§6). |
| `error` | **Only** when `failed`: `{"code", "message"}`. Codes: `internal_error` (a bug or crash on our side), `download_failed`, `provider_unavailable` (every LLM failed). |
| `usage` | Always present, possibly `[]`. One record per LLM or OCR-fallback call; PHP copies each one into `ai_usage`. It is also filled on failure, because tokens were still spent. |

`usage[].provider` is `gemini` or `groq`. `usage[].outcome` is one of:

- `ok`
- `timeout`
- `rate_limited`
- `server_error`
- `invalid_json`
- `error`

Failed calls are listed too; that is how fallback shows up.

**Rules for polling:**

- PHP polls at most once every 3 seconds per job.
- Python keeps a finished job for **30 minutes**, then returns 404.
- Python runs at most 2 jobs at once; others wait as `queued`.
- PHP marks a job failed after 15 minutes (spec §6.1).

**"Failed job" vs "unreadable source":** a source that can't be read (a paywall, an unreadable photo) is a **successful** job with `result.status = "unreadable"` and an `error_code`, so the UI can offer to upload or paste instead. `status: failed` is only for problems on our side.

### `POST /v1/embed` (synchronous)

```json
// request
{"texts": ["AI adoption in Ghanaian SMEs", "..."]}
// response 200
{"vectors": [[0.0123, -0.0456, ...], ...]}
```

- 1–32 texts. `vectors[i]` belongs to `texts[i]`. Each vector has 384 floats and is L2-normalised, so cosine similarity is a dot product.
- ❓ Each text is at most **10,000 characters** (queries, or title + abstract).
- B0–B2 return **zero vectors** (a stub). The real model arrives in B3, with the same contract.

## 5. Shared types

| Name | Shape |
| --- | --- |
| `Language` | `"en"`, `"fr"` or `"es"`. Generated text uses the **request's** `language`; quotes always stay in the source's own language (spec §6.5). |
| `Author` | `{"given": "Ama", "family": "Mensah"}`. Either part may be `""`. |
| `WorkType` | Matches `sources.work_type`: `journal_article`, `book`, `chapter`, `report`, `web_page`, `thesis` or `other` |
| `SectionKind` | `abstract`, `introduction`, `literature_review`, `methods`, `results`, `discussion`, `conclusion`, `references`, `body` or `other`. Web pages and pasted text without headings get a single `body` section. |
| `Design` | `survey`, `case_study`, `mixed_methods`, `experiment`, `quasi_experiment`, `qualitative`, `review`, `meta_analysis`, `secondary_data` or `other` |
| `Country` | ISO 3166-1 alpha-2, upper case: `"GH"`, `"FR"` |
| `Quote` | Verbatim text from the referenced chunk. Python has checked it with `evidence.verify()`: lowercased, whitespace collapsed, hyphen line breaks joined and smart quotes made plain, then an exact substring match (spec §6.4). Anything that fails is dropped and counted in `dropped_count`. |
| `prompt_version` | Every job that calls an LLM returns `prompt_version` (e.g. `"methods@3"`). PHP puts it in `ai_cache` keys (spec §6.5). |

**Id rule.** At extraction, chunks have no database ids yet, so `extract_source` results refer to chunks by `chunk_ordinal`, the 0-based position in `chunks[]`. Every later job receives real `chunk_id`s, `source_id`s and so on from PHP, and its results use those. Python only echoes ids back; it never invents them.

## 6. Job types

| `type` | Available from | Typical time |
| --- | --- | --- |
| `extract_source` | pdf: B2 · url, text, discovered: B4 · images and scanned PDFs: B5 | 20 s – 3 min |
| `compare_methods` | B2 | 10–60 s |
| `cluster_project` | B3 | 5–30 s |
| `synthesize` | B3 | 30 s – 3 min |
| `suggest_queries` | B4 | 5–15 s |
| `citation_analysis` | B4 | 5–60 s |
| `gap_report` | B5 | 10–60 s |
| `chat_answer` | B6 (stretch, F15) | 5–20 s |

Until a job type exists, `POST /v1/jobs` answers `422 unknown_job_type`. `embed_query` is not a job; it uses `/v1/embed`.

### 6.1 `extract_source`

**Payload**

```json
{
  "source_uuid": "0b6e…",
  "language": "en",
  "ocr_fallback_allowed": true,
  "input": {"kind": "pdf", "file_url": "https://res.cloudinary.com/…signed"}
}
```

| `input.kind` | Other fields of `input` |
| --- | --- |
| `pdf` | `file_url`: signed Cloudinary URL, valid 15 minutes, at most 10 MB |
| `images` | `file_urls`: 1–20 signed URLs **in page order**, at most 5 MB each |
| `url` | `url`: http(s), at most 2,048 characters; `upload_params` (below) |
| `text` | `title`, `text`: 1–50,000 characters. ❓ Also `citation`: optional `{authors[], year, venue, publisher, doi, url}`, as typed by the user. |
| `discovered` | `work`: the OpenAlex work object; `pdf_url`: string or `null`; `upload_params` |

- `language` is the source's language as PHP knows it. It picks the OCR language and is the starting point for detection. Python reports the detected language in `metadata.language`.
- `ocr_fallback_allowed` (from B5): whether this user still has Gemini OCR-fallback pages left today (spec §6.6). Python only uses the fallback when this is `true`, and each fallback page appears as one `usage` record.

❓ **`upload_params`**: when a link turns out to be a PDF, or a web page is saved as a snapshot, Python uploads the file to Cloudinary, because only Python fetched it. PHP signs the upload in advance:

```json
{"url": "https://api.cloudinary.com/v1_1/<cloud>/raw/upload",
 "fields": {"api_key": "…", "timestamp": "…", "signature": "…", "public_id": "skimr-dev/sources/<user>/<source>/file",
            "type": "authenticated"}}
```

Python sends a multipart POST to `url` with every entry of `fields` plus `file`. It never needs the Cloudinary secret. It returns the resulting `public_id` as `uploaded_public_id`, and the snapshot is plain extracted text (`text/plain`), never HTML (spec §11.2).

**Result**

```json
{
  "status": "ready",
  "error_code": null,
  "resolved_kind": "pdf",
  "uploaded_public_id": null,
  "snapshot_public_id": null,
  "prompt_version": "methods@1",
  "metadata": {
    "title": "AI adoption among SMEs in Ghana", "authors": [{"given": "Ama", "family": "Mensah"}],
    "year": 2024, "doi": "10.1234/abcd.5678", "openalex_id": "W4391234567", "url": null,
    "venue": "Journal of …", "publisher": null, "site_name": null, "published_date": null,
    "page_range": null, "abstract": "…", "language": "en", "work_type": "journal_article",
    "is_peer_reviewed": null, "page_count": 14
  },
  "ocr": {"used": false, "mean_confidence": null,
          "pages": [{"ordinal": 1, "confidence": 0.91, "text": "…", "engine": "tesseract"}]},
  "sections": [{"kind": "methods", "heading": "3. Methodology", "page_start": 4, "page_end": 6, "ordinal": 3}],
  "chunks": [{"ordinal": 0, "section_kind": "abstract", "page": 1, "text": "…", "embedding": [0.01, …]}],
  "references": [{"raw_text": "Mensah, A. (2021) …", "doi": null, "title": null, "year": 2021}],
  "methods": {
    "design": "survey", "sample_size": 312, "countries": ["GH"], "period_start": 2021, "period_end": 2022,
    "data_collection": "…", "analysis": "…", "population": "…",
    "evidence": {"design": {"chunk_ordinal": 7, "quote": "…"}, "sample_size": {"chunk_ordinal": 7, "quote": "…"}}
  },
  "findings": [{"text": "…", "chunk_ordinal": 12, "quote": "…", "kind": "key_finding"}],
  "dropped_count": 0
}
```

| Field | Notes |
| --- | --- |
| `status` | `ready`, `unreadable` or `abstract_only` (a discovered paper with no PDF: chunks come from the abstract only). These match `sources.status`. |
| `error_code` | `null` unless the status is `unreadable`. One of: `invalid_pdf`, `encrypted_pdf`, `file_too_large`, `login_required`, `js_required`, `blocked`, `too_short`, `ocr_unreadable`, `empty_text`, `text_too_long`. (A failed download of a PHP-signed Cloudinary URL is not the source's fault: the job **fails** with `download_failed`, and PHP resubmits.) When `unreadable`, every list is empty and `metadata` holds whatever was found. |
| `resolved_kind` | What the source turned out to be, e.g. a `url` that resolved to a `pdf` |
| `uploaded_public_id` | Set when Python uploaded a PDF it fetched |
| `snapshot_public_id` | Set when Python uploaded a web-page text snapshot |
| `metadata` | Any field may be `null`, or `""` / `[]`. OpenAlex/Crossref values replace values guessed from the PDF. `page_count` counts images for `images` sources. |
| `ocr` | `used: false` with `pages: []` for text PDFs, links and pasted text. Otherwise there is one entry per page or image: `confidence` is 0–1 (the mean word confidence), `engine` is `tesseract` or `gemini`, and `text` goes into `source_files.ocr_text`. `mean_confidence` goes into `sources.ocr_confidence`. |
| `sections[].page_*` | 1-based pages (or image numbers). Pasted text uses page 1. |
| `chunks` | About 1,800 characters each, 15% overlap, never crossing a section; `page` is where the chunk starts. `embedding` is 384 floats (store as float32: 1,536 bytes). |
| `methods` | `null` when no method is described (common for web pages and pasted text). Any field whose quote fails verification is `null` and counted in `dropped_count`. `evidence` holds only the fields that have a value. |
| `findings[].kind` | `key_finding` |
| `dropped_count` | The number of items removed because their quote didn't verify. The UI shows "N unsupported statements were removed". |

Progress: download 10, extract 30, embed 60, LLM 90, done 100.

### 6.2 `compare_methods`

Re-runs method extraction only, for sources that are missing it.

```json
// payload
{"language": "en",
 "sources": [{"source_id": 41, "chunks": [{"chunk_id": 4812, "section_kind": "methods", "page": 4, "text": "…"}]}]}
// result
{"prompt_version": "methods@1",
 "sources": [{"source_id": 41,
              "methods": {"design": "survey", "…": "…",
                          "evidence": {"design": {"chunk_id": 4812, "quote": "…"}}},
              "findings": [{"text": "…", "chunk_id": 4812, "quote": "…", "kind": "key_finding"}],
              "dropped_count": 0}]}
```

The fields are the same as in `extract_source`, but evidence uses `chunk_id` instead of `chunk_ordinal`. PHP sends only the Methods and Results chunks, at most 20 per source.

### 6.3 `cluster_project`

```json
// payload: one entry per ready source; vector = mean of its chunk vectors
{"language": "en",
 "sources": [{"source_id": 41, "vector": [0.01, …],
              "samples": [{"chunk_id": 4812, "text": "…"}]}]}
// result
{"prompt_version": "themes@1",
 "themes": [{"name": "Cost and infrastructure barriers",
             "description": "One sentence in the requested language.",
             "source_ids": [41, 43, 57], "scores": [0.92, 0.81, 0.77]}],
 "unclustered": [60]}
```

- Needs at least 2 sources; at most 30.
- `samples` holds up to 5 chunks per source, the ones PHP finds closest to the source vector.
- `scores[i]` (0–1) belongs to `source_ids[i]` and goes into `theme_sources.score`.
- Each `name` is at most 6 words.
- There are 2–6 themes. A source appears in at most one theme, or in `unclustered`.

### 6.4 `synthesize`

```json
// payload
{"language": "en", "research_question": "…",
 "themes": [{"theme_id": 7, "name": "…", "description": "…",
             "chunks": [{"chunk_id": 4812, "source_id": 41, "source_type": "journal_article", "text": "…"}]}]}
// result
{"prompt_version": "synthesize@1",
 "themes": [{"theme_id": 7,
             "findings": [{"key": "f1", "text": "Cost was the main barrier for 62% of firms",
                           "chunk_id": 4812, "quote": "62% of respondents cited cost as the primary barrier"}],
             "interpretations": [{"text": "Cost barriers dominate in survey-based studies",
                                  "supported_by": ["f1", "f3"]}],
             "dropped_count": 0}],
 "dropped_count": 0}
```

- At most 12 chunks per theme.
- `source_type` is the source's `WorkType`.
- Checks follow spec §6.4:
  - every `chunk_id` must be one that was sent
  - every quote must verify
  - every `supported_by` key must point to a finding that passed
  - if more than 30% of items fail, Python retries once
- Finding `key`s are unique within a theme.
- The top-level `dropped_count` is the total across themes.

### 6.5 `suggest_queries` (F21)

```json
// payload
{"language": "en", "research_question": "…", "theme_names": ["…"], "keywords": ["…"]}
// result
{"prompt_version": "queries@1", "queries": [{"text": "SME AI adoption barriers Ghana", "rationale": "…"}]}
```

There are 5–8 queries. `rationale` is in `language`; the query text may be in any language.

### 6.6 `citation_analysis`

```json
// payload: every ready source in the project
{"sources": [{"source_id": 41, "doi": "10.1234/x", "title": "…", "year": 2024, "openalex_id": "W123",
              "references": [{"reference_id": 900, "raw_text": "…", "doi": null, "title": "…", "year": 2021}]}]}
// result
{"edges": [{"citing_source_id": 41, "cited_source_id": 43, "reference_id": 900,
            "match_method": "doi", "confidence": 1.0}],
 "metrics": [{"source_id": 43, "in_degree": 3, "betweenness": 0.12}],
 "cited_by": [{"source_id": 41, "cited_by_count": 17, "provider": "openalex"}]}
```

- `match_method` is `doi` or `title`.
- A title match needs a normalised `token_sort_ratio` of at least 90, with the year within ±1. Its `confidence` is the ratio divided by 100.
- `cited_by.provider` is `openalex`, `semantic_scholar` or `null` (if the count wasn't found; `cited_by_count` is then `null`).
- This job makes no LLM calls, so there's no `prompt_version` and `usage` is `[]`.

### 6.7 `gap_report` (F14)

```json
// payload
{"language": "en", "research_question": "…",
 "methods": [{"source_id": 41, "design": "survey", "sample_size": 312, "countries": ["GH"],
              "period_start": 2021, "period_end": 2022, "population": "…"}],
 "themes": [{"theme_id": 7, "name": "…", "source_ids": [41, 43]}],
 "findings": [{"finding_id": 501, "source_id": 41, "text": "…"}]}
// result
{"prompt_version": "gaps@1",
 "counts": {"countries": {"GH": 9, "NG": 2}, "years": {"2019": 1, "2024": 5},
            "designs": {"survey": 9, "case_study": 4}, "populations": {"…": 3},
            "thin_themes": [{"theme_id": 9, "source_count": 1}]},
 "gaps": [{"text": "No longitudinal studies were found.",
           "evidence": {"counts": {"designs.longitudinal": 0}, "finding_ids": [501, 502]}}],
 "dropped_count": 0}
```

- There are at most 8 gaps.
- Each `evidence` must cite counts that appear in `counts`, or `finding_ids` that were sent. Gaps without evidence are dropped.
- `gaps[].text` maps to `gap_items.text` and `evidence` to `gap_items.evidence`.

### 6.8 `chat_answer` (stretch, F15)

```json
// payload
{"language": "en", "question": "…",
 "chunks": [{"chunk_id": 4812, "source_id": 41, "text": "…"}],
 "history": [{"role": "user", "text": "…"}, {"role": "assistant", "text": "…"}]}
// result
{"prompt_version": "chat@1",
 "answer": "Short answer in the requested language.",
 "findings": [{"key": "f1", "text": "…", "chunk_id": 4812, "quote": "…"}],
 "interpretations": [{"text": "…", "supported_by": ["f1"]}],
 "reason": null, "dropped_count": 0}
```

- Send at most 8 chunks and the last 6 messages of `history`.
- The same checks as `synthesize` apply.
- If nothing supports an answer: `"answer": null, "reason": "not_supported"`, and both lists are empty.

## 7. Open questions for P2 (❓)

1. Request body under 2 MB, and `/v1/embed` texts at most 10,000 characters each: OK?
2. The `upload_params` shape (§6.1): does it fit how you sign Cloudinary uploads in PHP? `fields` should contain everything Cloudinary's upload API needs except `file`.
3. Should `text` sources send a `citation` object, or will PHP keep the typed citation itself and ignore our `metadata`?
4. `cluster_project.samples`: will PHP pick the 5 chunks closest to each source vector, or should Python pick them, in which case PHP sends all chunk vectors (much bigger)?
5. Anything PHP needs in the results that isn't listed above?

## 8. Changelog

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-11-26 | First draft by P3, for review with P2 |
