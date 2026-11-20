# Skimr — P4 Implementation Brief: Dashboard Frontend (React)

**Spec version:** 1.1 (19 Nov 2026) · **Owner:** [P4 name]

> **How to use this file:** Give your AI coding agent two files: this brief and `00-MASTER-SPEC.md`. Work **one batch at a time**, and one task at a time within a batch. A batch is done when every acceptance check passes and the PR is merged into `dev`.

---

## 0. Instructions for the AI agent (read first, follow always)

You are a senior frontend engineer building the dashboard of **Skimr**, a research literature synthesis web app. `00-MASTER-SPEC.md` is the single source of truth (especially sections 5.2, 5.3, 11.1, 13 and 14). This brief is my personal task list.

1. Follow the spec exactly. If this brief and the spec disagree, **the spec wins**. Tell me about the conflict.
2. Use **only** the libraries in spec section 8. Ask before adding anything.
3. Edit files **only inside `frontend/`** (except `src/features/admin/`, which is P5's) and `shared/design-tokens.css`.
4. **API types come from `openapi-typescript`** (`npm run api:types`). Never hand-write API types. If an endpoint is missing, work against the mock (`npm run mock`) and tell me what to ask P1 or P2 for.
5. **Tokens:**
    - the access token lives in memory only (`lib/auth/tokenStore.ts`); never in localStorage, sessionStorage or URLs
    - call `POST /api/v1/auth/refresh` on load, with `withCredentials` and `X-Requested-With: skimr`
    - on a 401, make one shared (single-flight) refresh, then retry once; if that fails, clear state and go to `/app/login`
6. `dangerouslySetInnerHTML` is banned. **Every string** uses i18n keys; give me en, fr and es JSON.
7. Every screen must:
    - work in light and dark mode, and at 360 px and 1440 px wide
    - be keyboard accessible (WCAG 2.2 AA)
    - have loading, empty, error and filled states
8. For each task, give me:
    - complete files with full paths
    - then Vitest tests
    - then the commands
    - then a Conventional Commit message
9. Design: deep black, white and teal; flat surfaces with 1 px borders. **No gradients, glows, glassmorphism or emoji** (spec 14).
10. Ask before guessing.

---

## 1. Project in one paragraph

Skimr is a research workspace. Users create projects and add sources through one **"Add source" menu** with five options:

- upload PDFs
- upload images (read with OCR)
- add a link
- paste text
- find sources

They then search, see themes, compare methods, read an evidence-backed synthesis, explore a citation map and a gap report, and export. **Findings** (verbatim quotes with a source chip) must look clearly different from **AI interpretations** (a teal-outlined box labelled "AI interpretation"). The React app lives at `/app` on the same domain as the API (`/api/v1`).

## 2. What you own

- `frontend/` (everything except `src/features/admin/`)
- `shared/design-tokens.css` (P5 is the required second reviewer)

**Don't touch:** `backend/`, `ai-service/`, `frontend/src/features/admin/` (P5).

**Stack:** Vite, React 19, TypeScript (strict), Tailwind CSS v4, react-router v7, @tanstack/react-query v5, axios, react-hook-form + zod, i18next + react-i18next, @radix-ui/react-*, lucide-react, cytoscape + react-cytoscapejs, @marsidev/react-turnstile, Vitest + Testing Library, Playwright, ESLint, Prettier, openapi-typescript.

**Folders:**

```
frontend/src/
├─ app/            router.tsx, providers.tsx, layout/ (AppShell, Sidebar, Topbar)
├─ components/ui/  Button, Input, PasswordInput, Select, Checkbox, Switch, Card, Badge, Dialog,
│                  ConfirmDialog, DropdownMenu, Tooltip, Toast, Tabs, DataTable, Pagination,
│                  Skeleton, EmptyState, ProgressBar, Avatar, FileDropzone,
│                  SourceChip, SourceTypeBadge, FindingBlock, InterpretationBlock
├─ lib/            api/ (client.ts, schema.ts generated), auth/, i18n.ts, theme.ts
├─ features/       auth, overview, projects, sources, find-sources, search, themes, methods,
│                  synthesis, citations, gaps, export, sharing, notifications, settings, chat (stretch)
└─ locales/{en,fr,es}/  one JSON file per feature
```

## 3. Contracts

| You provide | To | By |
| --- | --- | --- |
| Design tokens + `components/ui` | P5 | End of B1 |
| API client, auth store, `RequireAuth`, `RequireRole`, `AppShell` with an admin nav slot | P5 | End of B1 |

| You need | From | By |
| --- | --- | --- |
| Auth endpoints | P1 | Middle of B1 (mock until then) |
| Projects, sources, jobs and research endpoints | P2 | Each batch (contract first) |
| **Sidebar + theme toggle reference samples** | Nana | Before B1 |

## 3a. Notes from P1: repo setup you need to know

- **CSP nonce for the theme script (spec 11.3, 14.4).** The API's Content-Security-Policy blocks inline scripts unless they carry the request's nonce. PHP serves `/app/*` itself and swaps a placeholder for that nonce. So the no-flash theme script in `index.html` **must** be written exactly as:

    ```html
    <script nonce="__CSP_NONCE__">/* read localStorage.theme, set class="dark" */</script>
    ```

    This is the **only** inline script allowed. Everything else goes in bundled modules. Vite's dev server (port 5173) doesn't send the CSP, so test this against the build served by PHP: `npm run build`, then http://localhost:8080/app/.
- **CI runs these scripts as soon as `frontend/package.json` exists** (Node 20, inside `frontend/`): `npm ci`, `npm run lint`, `npm run typecheck`, `npm run i18n:check`, `npm test -- --run`, `npm run build`, `npm audit --audit-level=high`. **Define every one of these scripts** and commit `package-lock.json`.
- **Mock API:** `npm run mock` in the **repository root** (not `frontend/`) serves `docs/api/openapi.yaml` on http://localhost:4010. The mock's paths have no `/api/v1` prefix (`http://localhost:4010/auth/login`), so point the Vite proxy at it with a rewrite while you work against mocks.
- **The contract has every auth and `/me` endpoint already** (OpenAPI v0). Run `npm run api:types` against it. Notable details:
    - `GET /me` returns a `features` object (`upload_images`, `add_link`, `paste_text`, `discover`, `chat`). Use it for the "Add source" menu flags in B2.
    - An account locked after 5 failed logins returns **429** with code `ACCOUNT_LOCKED` (plus `Retry-After`); other rate limits return 429 `RATE_LIMITED`.
    - Error bodies include `request_id`. Show it in error toasts ("Reference: ...") so users can report it.
    - A signed-in user with `status=pending_verification` gets **403 `EMAIL_NOT_VERIFIED`** from every endpoint except `GET /me` and `/auth/*`. Route them to the verify screen when you see it.
    - **401 `UNAUTHENTICATED`** on any call means the access token expired or was revoked (logout elsewhere, suspension). Call `/auth/refresh` once; if that also fails, go to login.
    - Turnstile widgets: pass `action` = `register`, `login` or `forgot_password` (the server checks it) and send the token as `turnstile_token`. Bot-check failure is 403 `TURNSTILE_FAILED`.
    - `/auth/refresh` and `/auth/logout` need the header `X-Requested-With: skimr`.
- **Packages P5 needs from you:** P5's admin screens use `recharts` from your `package.json`. Add it in B0 so P5 never has to edit your `package.json`.
- **`shared/design-tokens.css` early:** P5's public site builds its CSS from the same file in B0. Open it as its **own small PR first**, so P5 isn't blocked.
- **Integration branch is `dev`.** Branch from it; open PRs into it.

---

## B0 — Foundations (week 1)

1. **Vite + React 19 + TypeScript (strict):**
    - `base: '/app/'`
    - `build.outDir: '../backend/public/app'`
    - dev proxy `/api` → `http://localhost:8080`
2. **Tailwind v4:**
    - import `shared/design-tokens.css`: every spec 14.1 colour as a CSS variable, for light and dark
    - `@custom-variant dark (&:where(.dark, .dark *))`
    - self-hosted Inter
3. **No-flash theme:** an inline script in `index.html` reads `localStorage.theme` (`light` / `dark` / `system`) and sets `class="dark"` before the page paints (spec 14.4). Write it as `<script nonce="__CSP_NONCE__">` (see 3a); without the nonce the CSP blocks it.
4. **Tooling and scripts** (CI needs `lint`, `typecheck`, `i18n:check`, `test` and `build`; see 3a):
    - ESLint with `react/no-danger` and `@typescript-eslint/no-explicit-any` as errors
    - Prettier, Vitest
    - `npm run api:types` = `openapi-typescript ../docs/api/openapi.yaml -o src/lib/api/schema.ts`
    - `npm run i18n:check` fails when a key exists in `en` but is missing from `fr` or `es`
5. **`lib/api/client.ts`:**
    - axios with base `/api/v1`, `withCredentials`, and the `X-Requested-With` header
    - a request interceptor adds `Authorization: Bearer`
    - a response interceptor does the single-flight refresh + retry on 401
    - errors are normalised to `{code, message, fields}`
6. **`lib/auth/`:** `tokenStore.ts`, `AuthProvider.tsx` (refresh on load, then `GET /me`), `useAuth.ts`.
7. **`lib/i18n.ts`:** react-i18next, with namespaces loaded lazily from `locales/`; the language comes from `user.locale`, then `navigator.language`, then `en`.

**Acceptance:**

- [ ] `npm run dev` shows a stub shell in light and dark mode.
- [ ] `npm run build` outputs to `backend/public/app`.
- [ ] A Vitest test proves that 3 simultaneous 401s trigger exactly **one** refresh call.

---

## B1 — Shell, components, auth screens (weeks 2–3)

1. **UI components** (spec 14.5): Button (primary, secondary, ghost, danger; sm, md, lg; loading), Input, PasswordInput (show/hide + strength meter), Select, Checkbox, Switch, Card, Badge, Dialog, ConfirmDialog (with an optional reason field), DropdownMenu, Tooltip, Toast, Tabs, Skeleton, EmptyState, ProgressBar. Each must work in dark mode and with the keyboard.
2. **`AppShell`:**
    - **collapsible sidebar matching Nana's sample**: 256 px expanded, 64 px collapsed with tooltips, state saved in localStorage, an off-canvas drawer below 1024 px
    - nav items: Overview, Projects, Find sources, Shared with me, Notifications, Settings, and Admin (only for `admin` / `super_admin`)
    - **topbar**: global search placeholder, language switcher, **theme toggle matching the sample** (light / dark / system), notification bell, avatar menu
3. **Routing:**
    - public auth routes: `/app/login`, `/app/register`, `/app/verify-email`, `/app/forgot-password`, `/app/reset-password`, `/app/accept-invite`, `/app/complete-profile`
    - everything else inside `RequireAuth`
    - admin routes inside `RequireRole(['admin','super_admin'])`
    - a 404 page
4. **Auth screens** using react-hook-form + zod, mirroring spec 5.2:
    - the Turnstile widget on login, register and forgot password
    - a "Continue with Google" link to `/api/v1/auth/google/redirect`
    - a password strength meter
    - API `fields` errors shown under their inputs
    - the "Verify your email" screen for `pending_verification` users, with a resend button (respect the rate-limit message)
    - `/app/login?link=google` shows a "Confirm your password to link Google" form
5. **Language switcher:** calls `i18n.changeLanguage`, plus `PATCH /me` when signed in. **Theme toggle:** updates localStorage, plus `PATCH /me` when signed in.

**Acceptance:** a full sign-up → verify → login → logout cycle against P1's API, at 360 px and 1440 px wide, in both themes and all 3 languages.

---

## B2 — Overview, projects, Sources tab (PDF), settings (weeks 4–5)

1. **Overview:** recent projects, sources currently processing (with progress), quota meters (`GET /me/usage`), quick actions.
2. **Projects:** list (card or table view, search, sort, archived filter), plus the create and edit dialogs (title, research question, discipline, language).
3. **Project workspace:**
    - a header (title, research question) and URL-based tabs: `/app/projects/:id/{sources|find|search|themes|methods|synthesis|citations|gaps|export|sharing}`
    - call `GET /ai/warmup` on open; if it isn't ready, show "Waking up the AI service..."
4. **Sources tab:**
    - an **"Add source" menu** with 5 items: Upload PDF, Upload images, Add link, Paste text, Find sources. In this batch only **Upload PDF** is on; the others are hidden behind feature flags from `GET /me` until B4 and B5.
    - **Upload PDF:** `FileDropzone` (PDF only, 10 MB, with rejection messages), uploads one at a time with `onUploadProgress`
    - **sources table:** title, `SourceTypeBadge`, year, status (uploaded, processing with a progress bar, ready, failed with the reason, unreadable with actions, abstract only)
    - poll `GET /jobs/{id}` every 3 seconds with React Query `refetchInterval` while a job is running
    - a metadata edit dialog, delete with confirmation, and retry
5. **Settings:**
    - **Profile:** names, phone, alt phone, institution, discipline, avatar
    - **Security:** change password, change email, Google link status, sign out everywhere
    - **Preferences:** language, theme, citation style
    - **Notifications:** an email switch for each type
    - **Data:** export my data, delete account

**Acceptance:**

- [ ] 15 PDFs uploaded through the UI show `ready`.
- [ ] Quota errors appear as friendly toasts.
- [ ] Every screen works at 360 px.

---

## B3 — Core research screens (weeks 6–7)

1. **Search tab:** query box; results with a snippet (highlighting built from text ranges; no HTML), source title, `SourceTypeBadge`, page, section, score and a `SourceChip`.
2. **Themes tab:**
    - theme cards: name, description, source count, 2 quotes
    - rename; merge (pick 2 themes)
    - "Re-run clustering", with a confirmation that it uses 1 AI request
3. **Methods tab:** a `DataTable` (design, sample size, country, period, data collection, analysis, key finding); each cell's quote in a tooltip; column filters; CSV export.
4. **Synthesis tab:**
    - "Generate" button and a version picker
    - **`FindingBlock`:** the text + a `SourceChip`
    - **`InterpretationBlock`:** 1 px `--primary` border, `--primary-subtle` background, an "AI interpretation" label with an icon, and a list of its supporting findings
    - `SourceChip` opens a side panel with the full passage, the page **image beside the text for OCR sources**, and a "View original" link (signed URL)
    - editable user-text blocks
    - a notice when `dropped_count` is above 0

**Acceptance:** findings and interpretations can be told apart at a glance in both themes, and every chip opens its source passage.

---

## B4 — Links, paste, Find sources, citation map (weeks 8–9)

1. **Add link dialog:**
    - URL field, then a submit that shows processing
    - for `unreadable` sources, show "We couldn't read this page" with **Upload as PDF** and **Paste text** buttons, pre-filled with the source's title and URL
2. **Paste text dialog:** title, text (50,000-character counter), optional citation fields (author, year, publisher, URL).
3. **Find sources** (the sidebar page and the project tab, F16 + F21):
    - a **suggested searches** panel (`POST /projects/{id}/suggestions/queries`), with editable query chips; clicking one runs it
    - **results:** title, authors, year, venue, citation count, an open-access badge, and relevance (when in a project); "Add to project"
    - **citation chasing:** on any source (in the Sources table and the side panel), "References" and "Cited by" lists from `/sources/{sid}/references` and `/cited-by`, each with "Add", and marked if already added
    - for abstract-only results, the note "Get the full text through your library, then upload the PDF"
4. **Citation map tab:**
    - cytoscape with a force layout; nodes sized by `cited_by_count`, coloured by theme, shaped by source type
    - click a node to open its source panel
    - side lists "Most cited in this set" and "Bridging sources"
    - a **table view** toggle for accessibility

**Acceptance:** a DOI link, a direct PDF link and a web page can be added from the UI. An unreadable page offers both fallbacks. A suggested search adds an open-access paper.

---

## B5 — Images + OCR review, gaps, export, sharing, notifications (weeks 10–11)

1. **Upload images dialog:**
    - accepts 1–20 JPG, PNG or WebP files (5 MB each) as thumbnails, with **drag to reorder pages**
    - a citation form: title, authors, year, type (book, chapter, report, article, other), publisher, page range
    - photo tips (flat page, good light, no shadows); HEIC is rejected with "Export as JPG"
2. **OCR review view** (from a source's menu):
    - the page image on the left and the editable OCR text on the right, with page navigation and per-page confidence
    - Save calls `PATCH /sources/{sid}/text`
    - after saving, show a banner listing the findings marked "Source text changed: review"
3. **Gaps tab:** gap cards, each with its evidence (counts + finding chips), a "Find papers for this gap" button (opens Find sources pre-filled), and Generate / Regenerate.
4. **Export tab:** format (BibTeX, RIS, Word) and citation style (APA 7, MLA 9, Harvard), then download: a blob with the filename from `Content-Disposition`.
5. **Sharing tab:**
    - members table, invite by email with a role, remove a member
    - a **"Publish public page"** toggle that shows the public URL with a copy button and a clear warning that the page becomes public
6. **Notifications:** a page plus the bell (unread count, mark as read), and the **Shared with me** list.

**Acceptance:** 6 page photos are uploaded in order, OCR text is corrected, and the affected findings are flagged. Export downloads open correctly.

---

## B6 — Quality pass + stretch (week 12)

1. Accessibility pass with axe (no serious issues), and a keyboard-only walkthrough of every screen.
2. Responsive pass at 360, 390, 768, 1024 and 1440 px.
3. FR/ES review: no overflowing buttons or table headers.
4. Playwright end to end: sign up → verify → log in → create project → add a PDF, an image source and a link → synthesis → export.
5. **Stretch (F15), only if everything else is done:** a Chat tab with a message list, answers carrying finding chips, the "Not supported by your sources" state and a remaining-quota counter.

---

## Definition of done (every PR)

- [ ] Matches the spec; API types are generated, not hand-written
- [ ] Vitest passes; lint, typecheck and `i18n:check` pass; CI green
- [ ] Works in light and dark mode, at 360 px and 1440 px, with the keyboard; all 4 data states handled
- [ ] Screenshots or a short recording in the PR
- [ ] Branch `feature/p4-Fxx-short-name`; Conventional Commits with the feature id
