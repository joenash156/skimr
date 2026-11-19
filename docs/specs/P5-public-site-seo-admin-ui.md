# Skimr — P5 Implementation Brief: Public Site, SEO & Admin UI

**Spec version:** 1.1 (19 Nov 2026) · **Owner:** [P5 name]

> **How to use this file:** Give your AI coding agent two files: this brief and `00-MASTER-SPEC.md`. Work **one batch at a time**, and one task at a time within a batch. A batch is done when every acceptance check passes and the PR is merged into `develop`.

---

## 0. Instructions for the AI agent (read first, follow always)

You are a senior web engineer building the public site, the SEO and the admin console of **Skimr**, a research literature synthesis web app. `00-MASTER-SPEC.md` is the single source of truth (especially sections 4, 5.1, 5.4, 12, 13 and 14). This brief is my personal task list.

1. Follow the spec exactly. If this brief and the spec disagree, **the spec wins**. Tell me about the conflict.
2. Use **only** the libraries in spec section 8. **No React on public pages**: every public page must be complete HTML from the server (Slim + Twig).
3. Edit files **only inside my folders** (section 2). For anything else, tell me what to ask the owner for.
4. Never hard-code user-facing text. Public pages use the `trans()` Twig function; admin screens use react-i18next. Give me the EN, FR and ES strings.
5. **SEO on every public page:**
    - a unique title (at most 60 characters) and description (at most 155)
    - a canonical link and hreflang (en, fr, es, plus x-default)
    - Open Graph tags
    - valid JSON-LD
    - one `<h1>` and semantic landmarks
    - an entry in the sitemap
6. Design: deep black, white and teal; flat surfaces with 1 px borders. **No gradients, glows, glassmorphism, emoji or robot/brain stock images.** Light and dark themes with no flash.
7. For each task, give me:
    - complete files with full paths
    - then tests (PHPUnit for PHP, Vitest for React)
    - then the commands
    - then a Conventional Commit message
8. Ask before guessing.

---

## 1. Project in one paragraph

Skimr is a research workspace. Users add sources in five ways:

- PDFs
- photos or scans of pages (read with OCR)
- web links
- pasted text
- papers found by the built-in search

The AI turns them into themes, method comparisons, gap reports and an evidence-backed synthesis, where every finding quotes its source and AI interpretations are clearly labelled. The public site must explain this in 3 languages and rank well in search. Users can also publish a synthesis as a public, indexable page. Admins manage users; super admins manage admins and settings.

## 2. What you own

**Your folders:**

- `backend/src/Public/` (page controllers, `Seo/`, `Contact/`, `Twig/`, `Http/LocaleMiddleware.php`)
- `backend/config/routes/public.php` (including the language slug map), `backend/config/container/public.php`
- `backend/templates/public/`, `backend/translations/public/`, `backend/content/guides/`, `backend/public/assets/`
- the `contact_messages` migration (your only migration)
- `frontend/src/features/admin/` and `frontend/src/locales/{en,fr,es}/admin.json`

**Don't touch:** auth and security code (P1), research modules (P2), `ai-service/` (P3), the rest of `frontend/` (P4). Changes to `shared/design-tokens.css` go through P4; you're its required second reviewer.

**Stack:**

- **Public:** Slim 4 + Twig + Tailwind CSS v4 (CLI build) + Alpine.js, league/commonmark 2 (raw HTML escaped)
- **Admin UI:** React + TypeScript using P4's `components/ui`, the API client and React Query; recharts

## 3. Contracts

| You provide | To | By |
| --- | --- | --- |
| English public site live on staging | Team, facilitator | End of B1 |
| Admin users screens | Admins | End of B3 |
| Super-admin screens | Super admin | End of B4 |
| Public synthesis pages + sitemap | Users who publish | End of B5 |

| You need | From | By |
| --- | --- | --- |
| `Translator`, `RateLimiter`, `TurnstileVerifier`, `JsonResponder` | P1 | Middle of B1 |
| `MailService` | P2 | Middle of B1 |
| `components/ui`, the API client, `RequireRole`, the `AppShell` admin nav slot | P4 | End of B1 |
| Admin users API | P2 | Start of B3 |
| Super-admin API | P2 | Start of B4 |
| Publish endpoint + public synthesis data | P2 | B5 |

---

## B0 — Foundations (week 1)

1. **`templates/public/base.html.twig`:**
    - blocks for `title`, `description`, `canonical`, `alternates`, `og`, `jsonld`, `content`
    - `<html lang="{{ locale }}">`
    - a "Skip to content" link
    - the inline **no-flash theme script** (same logic as the app: `localStorage.theme` = light / dark / system)
    - preloaded Inter WOFF2
    - `meta theme-color` for light and dark
    - favicon set + `manifest.webmanifest`
2. **Partials:**
    - header: logo, nav, language switcher, theme toggle, and **Log in / Sign up buttons linking to `/app/login` and `/app/register`**
    - footer
    - mobile menu (Alpine.js)
3. **Tailwind CLI build** of `public/assets/site.css` from `shared/design-tokens.css`: add `npm run build:public` in `backend/package.json`, purged to under 30 KB gzipped.
4. **Locale and URLs:**
    - `src/Public/Twig/TransExtension` (wraps P1's `Translator`)
    - `LocaleMiddleware`: `/{lang}` accepts only en, fr and es; anything else is a 404
    - `/` → 301 → `/en/`
    - trailing slashes → 301 to the version without
    - a real `404` page

**Acceptance:** an empty home page renders in both themes with no flash, and passes the W3C HTML validator. PHPUnit covers the redirects.

---

## B1 — English site & SEO base (weeks 2–3)

1. **Pages (EN):**
    - **Home:** hero; how it works in 3 steps; a **"Five ways to add sources"** section (PDF, photos with OCR, links, pasted text, Find sources); a feature grid; "Findings vs AI interpretations" explained; FAQ; call to action
    - **Features:** one section per feature, F07–F23
    - **How it works**, **About**
    - **Privacy:** includes the Gemini free-tier data note (spec 6.7), and that photos have their location data removed
    - **Terms:** users must have the right to use what they upload; sources are stored privately
    - **Cookies**
2. **`src/Public/Seo/`:**
    - `MetaBuilder`: title, description, canonical, alternates
    - `JsonLd` builders: `Organization`, `WebSite`
    - `RobotsController`: exactly as spec 12.5, plus `noindex` on everything when `APP_ENV != production`
    - `SitemapController`: a sitemap index + `sitemap-pages.xml` with `xhtml:link` hreflang alternates and `lastmod`, cached 1 hour
3. Deploy to staging with P1. **In week 2, test the home page with Google Search Console's URL Inspection** (live test). This checks early whether InfinityFree's browser check blocks Googlebot (spec 21).

**Acceptance:** Lighthouse on Home (mobile) scores Performance at least 90 and SEO, Accessibility and Best Practices at least 95. Search Console's live test renders the page content.

---

## B2 — French, Spanish, structured data, contact (weeks 4–5)

1. Translate every page into FR and ES with **localised slugs** (the map in `config/routes/public.php`, e.g. `/fr/fonctionnalites`, `/es/funciones`). Get them reviewed and log the reviewed strings in `docs/i18n-review.md`.
2. Add hreflang on every page and in the sitemap. The language switcher goes to the equivalent page.
3. **JSON-LD:**
    - `SoftwareApplication` (applicationCategory EducationalApplication, price 0) and `FAQPage` on Home
    - validate with Google's Rich Results Test
4. **OG image template** on Cloudinary: 1200 × 630, title text overlay, flat black + teal, no gradient.
5. **Contact page:**
    - Turnstile
    - `POST /api/v1/contact`, rate-limited to 3 per hour per IP
    - store the message in `contact_messages` (write that migration)
    - email the team inbox via `MailService`

**Acceptance:** every page exists in 3 languages with correct hreflang, and the Rich Results Test is valid.

---

## B3 — Admin users screens (weeks 6–7)

1. **`features/admin/users`:**
    - a **Users** page: server-paginated `DataTable` (search; status and role filters; sort by sign-up date; columns name, email, status, verified, projects, sources, last login)
    - **User detail:** profile, usage, activity, actions
2. **Actions:** suspend, reactivate, delete, reset quota, resend verification. Each goes through `ConfirmDialog` with a **required reason**, invalidates the React Query cache afterwards, and shows the API error messages.
3. Hide actions the current role can't perform, and still handle 403 cleanly. The server is the real guard.

**Acceptance:** an admin can moderate a user. Admin and super-admin rows show no actions for an admin, and the API returns 403 if they're forced.

---

## B4 — Super-admin screens (weeks 8–9)

1. **Admins:**
    - list, and an invite form (first name, last name, email)
    - resend invite, suspend, delete, demote
    - show the "last super admin" protection message
2. **Settings:** LLM provider order (a reorderable list), quota defaults (including links per day, images per source, OCR fallback pages), feature flags (switches), maintenance mode.
3. **Audit log:** filters (actor, action, target, date range), CSV export.
4. **Stats:** recharts line and bar charts for sign-ups per day, **sources processed by type**, AI calls per provider, and failure rate. Use the token colours, and give each chart a table fallback.

**Acceptance:** the super admin invites an admin end to end. The audit log shows the invite, and stats render in both themes.

---

## B5 — Public synthesis pages & guides (weeks 10–11)

1. **`/{lang}/s/{slug}`**, server-rendered and read-only:
    - title, owner's display name, published date
    - themes, findings with citations and type badges, interpretations clearly labelled
    - a reference list (web sources show their URL and accessed date)
    - **short quotes only, at most 2 sentences each**; no full text, page images or snapshots (spec 6.7)
    - `Article` JSON-LD with `citation` entries (`ScholarlyArticle` with DOI where available)
    - breadcrumbs, OG tags
    - an unpublished page returns `410 Gone`
2. **`sitemap-syntheses.xml`:** published pages only.
3. **Guides:**
    - 3 articles in EN (FR and ES if time allows): "How to write a literature review", "Findings vs interpretations: reading AI output critically" and "How to find research gaps"
    - Markdown in `content/guides/`
    - `BlogPosting` + `BreadcrumbList` JSON-LD
    - listed in `sitemap-guides.xml`

**Acceptance:** a published synthesis is viewable logged out, appears in the sitemap, passes the Rich Results Test, and returns 410 after it's unpublished.

---

## B6 — Launch SEO (week 12)

1. Run the full spec 12.6 checklist on every public page, and add Lighthouse CI to the pipeline with P1.
2. Submit the sitemaps to Google Search Console and Bing Webmaster Tools, and fix any coverage errors.
3. Final FR/ES copy review.

---

## Definition of done (every PR)

- [ ] Matches the spec; for public pages: title and description in 3 languages, canonical + hreflang, one h1, valid JSON-LD, in the sitemap, Lighthouse report attached
- [ ] Tests pass; CI green
- [ ] Light and dark mode, 360 px and 1440 px, keyboard accessible
- [ ] Branch `feature/p5-Fxx-short-name`; Conventional Commits with the feature id
