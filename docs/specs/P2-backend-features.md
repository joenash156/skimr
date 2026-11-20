# Skimr — P2 Implementation Brief: Backend Features

**Spec version:** 1.1 (19 Nov 2026) · **Owner:** [P2 name]

> **How to use this file:** Give your AI coding agent two files: this brief and `00-MASTER-SPEC.md`. Work **one batch at a time**, and one task at a time within a batch. A batch is done when every acceptance check passes and the PR is merged into `dev`.

---

## 0. Instructions for the AI agent (read first, follow always)

You are a senior engineer implementing part of **Skimr**, a research literature synthesis web app. `00-MASTER-SPEC.md` is the single source of truth. This brief is my personal task list.

1. Follow the spec exactly: architecture, folders, naming, API contract (spec 10), data model (spec 9) and security (spec 11). If this brief and the spec disagree, **the spec wins**. Tell me about the conflict.
2. Use **only** the libraries in spec section 8. Ask before adding anything.
3. Edit files **only inside my folders** (section 2). For anything else, tell me what to ask the owner for.
4. Never hard-code user-facing text. API error messages and emails use translation keys; give me the EN, FR and ES strings.
5. Security is mandatory:
    - prepared statements only, and **no SQL outside repositories**
    - `ProjectPolicy` on every project-scoped query
    - validate every input
    - never log secrets
6. For each task, give me:
    - complete files with full paths
    - then the tests (PHPUnit)
    - then the commands to run on Windows
    - then a Conventional Commit message
7. Keep the evidence rule (spec 2 and 6.4). PHP **re-checks** that every interpretation links to at least one valid finding before saving it.
8. Before any endpoint is written, it must be in `docs/api/openapi.yaml`. Give me the YAML snippet to send P1.
9. Ask before guessing.

---

## 1. Project in one paragraph

Skimr lets students and researchers build an evidence-backed literature review. Users add **sources** in five ways:

- PDF uploads
- image uploads (photos or scans of pages, read with OCR)
- web links
- pasted text
- papers found through search

The Python AI service extracts and analyses them. PHP is the hub: it owns MySQL, calls Cloudinary, Resend and OpenAlex, sends jobs to Python (HMAC-signed) and **polls** Python for results. Python never calls PHP, because the free host blocks requests that don't come from a browser.

## 2. What you own

**Your folders:**

- `backend/src/Projects/`, `Sources/`, `Ai/`, `Research/`, `Admin/`, `Media/`, `Mail/`
- `backend/config/routes/{projects,sources,research,admin}.php` and the matching `config/container/*.php`
- every migration except the auth and settings tables (P1) and `contact_messages` (P5)
- `backend/templates/emails/`
- `backend/translations/api/{module}.{en,fr,es}.json` for your modules

**Don't touch:** `src/Auth`, `Security`, `Shared`, `Users` (P1; ask them), `ai-service/` (P3), `frontend/` (P4/P5), public templates (P5).

**Stack:** PHP 8.2+, Slim 4, Doctrine DBAL 4 + Migrations, cloudinary/cloudinary_php 2, resend/resend-php, guzzle 7, phpoffice/phpword 1, ramsey/uuid, PHP's GD extension, PHPUnit.

## 3. Contracts

| You provide | To | By |
| --- | --- | --- |
| `MailService::send(template, to, locale, vars, optional=false)` | P1, P5 | Middle of B1 |
| Projects, sources, jobs and research endpoints (contract first) | P4 | Contract at the start of each batch; working by the end |
| Admin users API | P5 | Start of B3 |
| Super-admin API (admins, settings, stats, audit logs) | P5 | Start of B4 |

| You need | From | By |
| --- | --- | --- |
| `AuthMiddleware`, `RoleGuard`, `Policy`, `AuditLogger`, `UserTokenService` | P1 | Middle of B1 |
| `docs/api/ai-service.md`: payload and result JSON for every job type (frozen) | P3 (co-written with you) | End of B0 |

## 3a. Notes from P1: what the backend skeleton already gives you

P1's B0 is in place. Build on it; don't recreate or edit these files (they're P1's).

- **Your routes:** create `backend/config/routes/{projects,sources,research,admin}.php`. Each file returns `function (Slim\App $app): void { ... }` and is loaded automatically by `config/bootstrap.php`. Use full paths (`/api/v1/projects`). Never register a path another module already has: Slim throws on duplicates.
- **Your services:** create `backend/config/container/{projects,sources,research,admin}.php`. Each returns an array of PHP-DI definitions. Autowiring is on, so most classes need no definition at all.
- **Settings:** inject the container key `'settings'` (built in `config/settings.php`). Never call `getenv()` or read `$_ENV`. If you need a new `.env` variable, ask P1 to add it to `settings.php` and `.env.example`.
- **Database:** inject `Doctrine\DBAL\Connection`. It's already configured with utf8mb4 and `time_zone = '+00:00'`, so every `DATETIME(3)` is UTC. Prepared statements or the query builder only, and only inside repositories.
- **Errors:** throw the exceptions in `App\Shared\Exception\`: `NotFoundException`, `ForbiddenException`, `ValidationException(['field' => 'translation.key'])`, `ConflictException`, `QuotaExceededException($retryAfterSeconds)`, `PayloadTooLargeException`, `BadRequestException`. `ErrorHandler` turns them into the spec 10.1 envelope with the right status, a `request_id` and the translated message. Don't catch-and-format errors in controllers.
- **Validation:** `App\Shared\Http\Input::fromRequest($request)` reads the JSON body field by field (`requiredString`, `optionalString`, `email`, `optionalPhone`, `optionalLocale`, `accepted`, `confirms`, `addError`) and `validate()` throws one 422 with every field error. The error keys (`validation.required`, `validation.too_long`, ...) are already translated in `shared.*.json`. See `App\Auth\Http\AuthController` for examples.
- **Responses:** inject `App\Shared\Http\JsonResponder` and use `success()`, `created()`, `paginated()` and `noContent()`. Never `json_encode` in a controller.
- **Messages:** put your API strings in `backend/translations/api/{module}.{en,fr,es}.json` (nested JSON becomes dotted keys). Inject `App\Shared\Translator`. Add machine-drafted FR/ES strings to `docs/i18n-review.md`.
- **Time:** inject `App\Shared\Clock\Clock` instead of `new DateTimeImmutable()`. Use `FrozenClock` in tests.
- **Auth (working since P1's B1, PR "auth core"):**
    - Protect a group with `->add(RoleGuard::allow('admin', 'super_admin'))->add(AuthMiddleware::class)` (Slim runs the last-added first, so Auth runs before RoleGuard). Plain user routes need only `->add(AuthMiddleware::class)`.
    - The signed-in user is `$request->getAttribute(RequestAttributes::USER)`, an `App\Users\User` (readonly: `id`, `uuid`, `role`, `status`, `email`, `locale`, ...; helpers `isAdmin()`, `isSuperAdmin()`). Never put a `User` straight into a response: it carries the password hash.
    - Admin actions on another account: inject `App\Security\RolePolicy` and call `assertCanActOn($actor, $target, RolePolicy::ACTION_SUSPEND)` (also `ACTION_REACTIVATE`, `ACTION_DELETE`, `ACTION_CHANGE_ROLE`, `ACTION_RESET_QUOTA`, `ACTION_VIEW`). This is the brief's "`RoleGuard::assertCanActOn`". It throws 403 and writes the `security.forbidden` audit row itself.
    - `ProjectPolicy` implements `App\Security\Policy` (`can(User $user, string $ability, object $resource): bool`).
    - Audit: inject `App\Security\Audit\AuditLogger` and call `log($actor, AuditEvent::ADMIN_USER_SUSPENDED, 'user', $target->uuid, $reason, $meta, ClientIp::of($request))`. Event names are constants on `App\Security\Audit\AuditEvent`. No secrets in `reason` or `meta`.
    - Invite tokens: inject `App\Auth\Token\UserTokenService`. `issue($userId, TokenType::ADMIN_INVITE, 86400)` returns the raw token for the email link; issuing again cancels the previous link (so "resend" needs nothing extra); `revoke($userId, $type)` cancels without issuing.
    - Rate limits: inject `App\Security\RateLimit\RateLimiter` and call `consume(RateLimit::upload(), 'user:' . $user->uuid)` (also `RateLimit::addLink()`). It throws 429 `RATE_LIMITED` with `Retry-After`. The general 120-per-minute API limit is already applied globally.
- **Email interface:** your `MailService` must implement `App\Shared\Mail\Mailer` (`send(string $template, string $to, string $locale, array $vars, bool $optional = false): void`). Until it exists, P1's auth code sends through `LogMailer`, which writes each email to `backend/var/mail.log`. When yours is ready, bind it in `backend/config/container/mail.php`: `Mailer::class => \DI\get(MailService::class)`. That file loads after P1's `auth.php`, so it wins. Templates P1's auth code sends, with their variables:
    - `verify_email`: `first_name`, `link`, `expires_hours` (24)
    - `reset_password`: `first_name`, `link`, `expires_minutes` (60)
    - `new_sign_in`: `first_name`, `at` (ISO 8601, UTC), `ip`, `user_agent`
    - `password_changed`: `first_name`, `at`
    - `suspicious_activity`: `first_name`, `at`

    For `admin_invite`, build the link with `App\Auth\AuthLinks::acceptInvite($rawToken)` (it points at `/app/accept-invite?token=...`).
- **Tests with the database:** `composer test` creates and migrates a separate `skimr_test` database first (your migrations included), so your dev data is never touched. Extend `App\Tests\DatabaseTestCase` for DB tests: it empties P1's tables before each test and gives you `db()`, `service(Class::class)` and `createUser([...])`. Empty your own tables in your test's `setUp()`.
- **Migrations:** P1's first migration is `Version20261119230000` (users, user_tokens, login_events, rate_limits, audit_logs, settings). Yours must have a **later timestamp** (`vendor/bin/doctrine-migrations generate`). Write raw MySQL in `addSql()` with `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`. Foreign keys to `users.id` are `BIGINT UNSIGNED`. Every `down()` must undo its `up()`: CI runs migrations up, down to the first version, then up again, on a real MySQL 8.
- **CLI commands** (e.g. `app:seed-demo` in B6): register them in your container file with `'console.commands' => \DI\add([YourCommand::class])`. Don't edit `bin/console`.
- **`/me` endpoints you own** (`/me/avatar`, `/me/usage`, `/me/export`): put them in your own routes file. P1 owns `GET/PATCH/DELETE /me`.
- **`GET /me` returns `features`** (`upload_images`, `add_link`, `paste_text`, `discover`, `chat`). P4 hides menu items from it. In B4, when you build `/admin/settings`, store the flags in the `settings` table; P1 reads them for `/me`.
- **Local checks before every PR** (in `backend/`): `composer test`, `composer stan`, `composer cs`. CI runs the same, plus `composer audit`.
- **Integration branch is `dev`.** Branch from it; open PRs into it.

---

## B0 — Foundations (week 1)

### Tasks

1. **Migrations** for every non-auth table in spec 9.2:
    - `notifications`, `projects`, `project_members`
    - **`sources`**, **`source_files`**, `source_sections`, `source_chunks`, `source_references`, `citation_edges`, `source_methods`
    - `findings`, `themes`, `theme_sources`
    - `syntheses`, `synthesis_items`, `interpretation_support`
    - `gap_reports`, `gap_items`, `chat_threads`, `chat_messages`
    - `ai_jobs`, `ai_usage`, `ai_cache`, `usage_counters`

    Include every enum exactly as in spec 9.2: `source_type`, `work_type`, `status` (including `unreadable` and `abstract_only`), and `source_files.kind`.

    **Indexes:**
    - every foreign key
    - `sources(project_id, status)`
    - `sources(project_id, source_type)`
    - `source_files(source_id, ordinal)`
    - `source_chunks(project_id)`
    - `ai_jobs(user_id, status)`
    - `usage_counters(user_id, day)` UNIQUE
    - `FULLTEXT(source_chunks.text)`
2. **`Media/CloudinaryService`:**
    - `uploadPrivate(localPath, publicId, resourceType)` for PDFs and images (`type=authenticated`)
    - `uploadAvatar(localPath, userUuid)`
    - `signedUrl(publicId, ttlSeconds, transformation=null)`
    - `signedUploadParams(publicId, resourceType)`, which Python uses to upload open-access PDFs and web snapshots
    - `delete(publicId)`

    Folder layout follows spec 16: `skimr/sources/{user_uuid}/{source_uuid}/...`.
3. **`Mail/MailService`:**
    - renders Twig HTML + text from `templates/emails/{locale}/`, then sends via Resend
    - logs and swallows failures
    - has an `optional` flag that skips the email once 80 have been sent that day (spec 15.3)
4. **`Ai/AiServiceClient`:**
    - methods: `health()`, `submitJob(type, payload)`, `getJob(remoteId)`, `embed(texts)`
    - headers: `X-Timestamp`, `X-Signature` = HMAC-SHA256(`timestamp + "." + body`, `AI_SERVICE_SECRET`). Exact format, agreed with P3: `X-Timestamp` is Unix seconds, and `X-Signature` is lowercase hex (`hash_hmac('sha256', $ts . '.' . $body, $secret)`), signed over the exact bytes you send as the body.
    - Guzzle with a 20-second timeout and 1 retry on connection errors
5. With P3, **write and freeze `docs/api/ai-service.md`**: job `extract_source`, with every `input.kind` (pdf, images, url, text, discovered), plus `cluster_project`, `compare_methods`, `synthesize`, `citation_analysis`, `gap_report`, `suggest_queries` and `chat_answer`.

### Acceptance

- [ ] Migrations run up and down cleanly.
- [ ] A test email arrives at `delivered@resend.dev`.
- [ ] `AiServiceClient::health()` reaches P3's local service; the signature tests pass against a mocked Guzzle handler.

---

## B1 — Emails & admin invites (weeks 2–3)

1. With Nana, verify the Resend domain on `mail.foliony.app` (spec 15.1).
2. **Email templates** in EN, FR and ES, HTML + text:
    - verify email, reset password, admin invite
    - new sign-in, password changed, email changed
    - suspicious activity (token reuse)
    - account suspended, account reactivated
3. **`POST /admin/admins`** (super admin only):
    - create the user with `role=admin`, `status=invited`
    - issue an `admin_invite` token through `UserTokenService` (valid 24 hours)
    - send the email and write the audit entry `superadmin.admin.invited`
4. **`POST /admin/admins/{id}/resend`:** cancel the old token and send a new one.

**Acceptance:** an invited admin receives the email in their language, sets a password through P1's `/auth/invite/accept`, and logs in.

---

## B2 — Projects, PDF sources, jobs, quotas (weeks 4–5)

1. **`Projects/ProjectPolicy`:** owner, or accepted member (a viewer can read, an editor can write). It is called by **every** Sources and Research repository method, and returns `NotFoundException` when there's no access.
2. **Projects CRUD:** `/projects`, `/projects/{id}`, with archive and soft delete.
3. **`POST /projects/{id}/sources` for `type=pdf`:**
    - check quotas (spec 6.6)
    - `finfo` MIME type + `%PDF-` first bytes; 10 MB limit (else 413)
    - SHA-256 duplicate check within the project (409)
    - upload to Cloudinary
    - create the `sources` row (`source_type=pdf`, `status=processing`) and the `source_files` row
    - create the `extract_source` job with `input={kind:"pdf", file_url:<signed 15 min>}`
    - return `{source, job_id}`
4. **Other source endpoints:**
    - `GET /projects/{id}/sources`, filterable by type and status
    - `GET` / `PATCH` / `DELETE /projects/{id}/sources/{sid}` (DELETE also deletes the Cloudinary files)
    - `GET .../sources/{sid}/files`: signed URLs, 10 minutes
    - `POST .../sources/{sid}/retry`
5. **`Ai/JobService`:**
    - `create()`, `submit()`
    - `refresh(job)`: polls Python at most once every 3 seconds; resubmits on 404 (max 2); fails after 15 minutes
    - `applyResult(job)`: one DB transaction per job type. For `extract_source`, it writes `source_sections`, `source_chunks` (vectors packed with `pack('g*', ...$floats)`), `source_references`, `source_methods` and `findings`, maps `chunk_ordinal` to the real chunk ids, and updates `sources` metadata and status. An `unreadable` result sets `status=unreadable` with `error_code`.
    - copies `usage[]` into `ai_usage`
6. **`GET /jobs/{id}`** (calls `refresh`, returns status + progress) and **`GET /ai/warmup`** (pings `/health`, returns `{data:{ready:bool}}`).
7. **Quotas:** `usage_counters` increments, `QuotaService::assert(user, kind)` throwing `QuotaExceededException`, and `GET /me/usage`.
8. **More:** `POST /me/avatar` (GD re-encode, 2 MB), `GET /me/export`, notifications (`GET` / `PATCH /notifications`), and the batched **"Your sources are ready"** email (one email when all the sources added together have finished).

**Acceptance:**

- [ ] 15 sample PDFs end `ready`, with sections, chunks and references.
- [ ] User B gets 404 on user A's project and sources.
- [ ] The 31st source in one project returns `QUOTA_EXCEEDED`.

---

## B3 — Core research endpoints + admin users (weeks 6–7)

1. **Search (`GET /projects/{id}/search?q=`):**
    - embed the query with `AiServiceClient::embed`
    - load the project's chunk vectors (`unpack('g*')`), take the cosine similarity in PHP, and keep the top 50
    - run MySQL `MATCH ... AGAINST` for the top 50 keyword hits
    - combine with reciprocal rank fusion (k = 60) and return the top 10, each with source, type badge, page, section and snippet
2. **Themes:**
    - `POST` submits `cluster_project` with each source's mean vector + 3 sample chunks
    - store `themes` and `theme_sources`
    - `PATCH` renames (`user_renamed=1`) or merges
3. **Methods:** `GET` builds the comparison table from `source_methods`, with each cell linked to its finding.
4. **Syntheses:**
    - `POST` submits `synthesize` with the top 12 chunks per theme
    - store `syntheses` (next version), `synthesis_items` and `interpretation_support`
    - **drop any interpretation with no valid finding**
    - `PATCH` lets users edit only `user_text` items and headings
5. **Cache:** before submitting any job, check `ai_cache` for `sha256(type + PROMPT_VERSION + canonical input)`.
6. **Admin users API** (P5 needs it now):
    - `GET /admin/users` (filters, sort, pagination) and `/admin/users/{id}`
    - `POST /admin/users/{id}/suspend|reactivate|delete|reset-quota|resend-verification`
    - a reason is required; check `RolePolicy::assertCanActOn` (see 3a); write an audit entry for every action

**Acceptance:**

- [ ] The worked example in spec section 2 runs end to end on PDFs.
- [ ] A cached rerun returns in under 1 second.
- [ ] An admin acting on an admin gets 403.

---

## B4 — Links, pasted text, source hunting, citations, super-admin API (weeks 8–9)

1. **`POST /projects/{id}/sources` for `type=url`:**
    - validate the URL (http/https, at most 2,048 characters)
    - check the daily link quota (30)
    - create the `sources` row (`source_type=url`, `url`, `accessed_at=now`)
    - create the job with `input={kind:"url", url, upload_params: signedUploadParams(...)}`
    - when the job finishes, save the `source_files` row for the PDF or snapshot it returns
2. **`POST /projects/{id}/sources` for `type=text`:** title, text (at most 50,000 characters) and citation fields, then `input={kind:"text", ...}`.
3. **`POST /projects/{id}/suggestions/queries`:** submit `suggest_queries` with the research question, theme names and top keywords. Cache for 7 days.
4. **Discover:**
    - `GET /discover?q=&project_id=` calls **OpenAlex and Semantic Scholar directly** (outbound is allowed), merges by DOI and caches 7 days
    - with `project_id`, it ranks results by cosine similarity between each title + abstract and the research question (`/v1/embed`)
    - `POST /projects/{id}/discover/add` creates a `source_type=discovered` source; it submits `extract_source` with `input={kind:"discovered", work, pdf_url?, upload_params}`, or sets `abstract_only` when there's no PDF
5. **Citation chasing** (no AI):
    - `GET .../sources/{sid}/references` uses OpenAlex `referenced_works`
    - `GET .../sources/{sid}/cited-by` uses the OpenAlex `filter=cites:{id}`
    - mark results already in the project
6. **Citations:** submit `citation_analysis`, store `citation_edges` and `cited_by_count`, and return `{nodes:[{id,title,year,source_type,theme_id,cited_by_count}], edges:[{source,target}]}`.
7. **Super-admin API:**
    - `GET` / `POST /admin/admins` and `/admin/admins/{id}/suspend|delete|demote` (never the last super admin)
    - `GET` / `PATCH /admin/settings` (validated keys only)
    - `GET /admin/stats` and `GET /admin/audit-logs` (admins see only their own)

**Acceptance:**

- [ ] A DOI link, a direct PDF link and a World Bank web page each become a `ready` source.
- [ ] A login-only page becomes `unreadable`.
- [ ] Discover adds an open-access paper together with its PDF.

---

## B5 — Images, OCR text, gaps, export, sharing, publishing (weeks 10–11)

1. **`POST /projects/{id}/sources` for `type=images`:**
    - accept `files[]` in page order: 1–20 files, 5 MB each
    - check the first bytes (JPEG, PNG or WebP) and **re-encode with GD**, which strips EXIF and GPS data and rejects fake images
    - require the citation fields
    - upload each file to `.../{source_uuid}/p{ordinal}` and create the `source_files` rows
    - submit the job with `input={kind:"images", file_urls:[...], language}`
2. **`GET` / `PATCH .../sources/{sid}/text`:**
    - `GET` returns the text page by page
    - `PATCH` corrects one page's OCR text: save `ocr_text` and set `ocr_text_edited=1`, then re-chunk and re-embed that page via `embed`, re-check every finding from that page, and set `needs_review=1` on findings that no longer match
3. **Gaps:** submit `gap_report`, store `gap_reports` and `gap_items`.
4. **Export:**
    - BibTeX and RIS built in PHP from `sources`; web sources include `url` and `accessed_at`
    - Word via PhpWord: title → each theme → findings with in-text citations → interpretations in a shaded box labelled "AI interpretation" → references
    - a `CitationFormatter` for APA 7, MLA 9 and Harvard, covering journal articles, books, chapters, reports and web pages
5. **Sharing:**
    - members `GET` / `POST` / `DELETE`
    - an invite email with a 7-day token
    - `POST /invites/accept`
6. **Publish:**
    - `POST .../syntheses/{sid}/publish` toggles `is_public`
    - make a unique `public_slug` from the title and set `published_at`
    - audit `synthesis.published` / `unpublished`
    - the slug is kept when unpublished, so P5 can return 410

**Acceptance:**

- [ ] 6 photos of a book chapter become a `ready` source.
- [ ] Downloaded images have no EXIF data.
- [ ] Editing OCR text flags the affected findings.
- [ ] The BibTeX file imports into Zotero, and the .docx opens in Word.

---

## B6 — Launch + stretch (week 12)

1. **`app:seed-demo` command:** a demo user and the "AI adoption in Ghanaian SMEs" project (PDFs, 1 link, 1 image source) with pre-computed results.
2. Fix bugs from the end-to-end run, and confirm every endpoint matches `openapi.yaml`.
3. **Stretch (F15), only if everything else is done:** `GET` / `POST /projects/{id}/chat` submits `chat_answer` with the question + top 8 chunks + the last 6 messages, stores the messages, and counts toward the chat quota.

---

## Definition of done (every PR)

- [ ] Matches the spec; the endpoint is in `openapi.yaml` before merge
- [ ] PHPUnit tests pass; CI green
- [ ] `ProjectPolicy` is checked in every new repository method
- [ ] Translation keys in en, fr and es
- [ ] No secrets or debug output; the folder owner has approved
- [ ] Branch `feature/p2-Fxx-short-name`; Conventional Commits with the feature id
