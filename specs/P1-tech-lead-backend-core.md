# [app-name] — P1 Implementation Brief: Tech Lead & Backend Core

**Spec version:** 1.1 (19 Nov 2026) · **Owner:** [P1 name]

> **How to use this file:** Give your AI coding agent two files: this brief and `00-MASTER-SPEC.md`. Work **one batch at a time**, and one task at a time within a batch. A batch is done when every acceptance check passes and the PR is merged into `develop`. Only then start the next batch.

---

## 0. Instructions for the AI agent (read first, follow always)

You are a senior engineer implementing part of **[app-name]**, a research literature synthesis web app. `00-MASTER-SPEC.md` is the single source of truth. This brief is my personal task list.

1. Follow the spec exactly: architecture, folder structure, naming, API contract, data model and security rules. If this brief and the spec disagree, **the spec wins**. Tell me about the conflict.
2. Use **only** the libraries in spec section 8. If something seems to need a new library, stop and ask me.
3. Create or edit files **only inside my folders** (section 2 below). If a change is needed elsewhere, tell me exactly what to ask that folder's owner for.
4. Never hard-code user-facing text. Use translation keys and give me the EN, FR and ES strings.
5. Security is mandatory:
    - prepared statements only
    - validate every input
    - check permissions on the server
    - never log secrets, tokens or passwords
6. For each task, give me:
    - complete files (not fragments), each with its full path
    - then the tests
    - then the exact commands to run on Windows (Laragon, PowerShell)
    - then a suggested Conventional Commit message
7. Keep the evidence rule (spec 2 and 6.4): AI output is either a quoted finding or a labelled interpretation linked to findings.
8. If anything is unclear, ask before guessing.

Start every session by confirming the batch and task we're on.

---

## 1. Project in one paragraph

[app-name] lets students and researchers build an evidence-backed literature review. Users add sources in five ways:

- PDFs
- photos or scans of pages (read with OCR)
- web links
- pasted text
- papers found by the built-in search

The system extracts the text, makes it searchable by meaning, clusters it into themes, compares methods, finds gaps and writes a synthesis. Every statement cites the exact source passage.

**Architecture:**

- One PHP app (Slim 4) serves the public Twig pages, the JSON API (`/api/v1`) and the built React dashboard (`/app`), all on one domain.
- A separate Python FastAPI service does all AI work. Only PHP calls it, using HMAC-signed requests, and PHP polls it for results.
- MySQL is reached only by PHP.
- Files go to Cloudinary, email goes through Resend, bots are stopped by Cloudflare Turnstile, and Google OAuth handles sign-in.

## 2. What you own

**Your folders:**

- `backend/bin/`
- `backend/config/` core files (`settings.php`, `container.php`, `middleware.php`, the routes/container loader, `routes/auth.php`, `container/auth.php`)
- `backend/src/Shared/`, `backend/src/Security/`, `backend/src/Auth/`, `backend/src/Users/`
- migrations for the auth and settings tables: `users`, `user_tokens`, `login_events`, `rate_limits`, `audit_logs`, `settings`
- `docs/api/openapi.yaml`
- `deploy/`, `.github/`, and root config files (`README.md`, `.gitignore`, `.editorconfig`, `.env.example`)

**Don't touch:**

- `src/Projects`, `Sources`, `Ai`, `Research`, `Admin`, `Media`, `Mail` (P2)
- `ai-service/` (P3)
- `frontend/` (P4 and P5)
- `templates/public`, `src/Public` (P5)

**Stack:** PHP 8.2+, Slim 4, PHP-DI 7, Doctrine DBAL 4 + Migrations 3, vlucas/phpdotenv, firebase/php-jwt 6, league/oauth2-google, respect/validation, guzzle 7, monolog 3, symfony/console 7, PHPUnit 11, PHPStan level 6, php-cs-fixer (PSR-12).

## 3. Contracts

| You provide | To | By |
| --- | --- | --- |
| Running skeleton, CI, `.env.example`, OpenAPI v0 + mock server | Everyone | End of B0 |
| `AuthMiddleware`, `RoleGuard`, the `Policy` interface, `RateLimiter`, `TurnstileVerifier`, `AuditLogger`, `Translator`, `JsonResponder`, `ErrorHandler` | P2, P5 | Middle of B1 |
| A token service (`UserTokenService::issue/consume`) that P2 can use for admin invites | P2 | Middle of B1 |
| Every auth endpoint | P4, P5 | Middle of B1 |

| You need | From | By |
| --- | --- | --- |
| `MailService::send(template, to, locale, vars, optional=false)` | P2 | Middle of B1 (until then, a `LogMailer` writes emails to `var/mail.log`) |

---

## B0 — Foundations (week 1)

**Goal:** a teammate clones the repo, follows the README, and has all three services running in under 30 minutes. CI is green.

### Tasks

1. **GitHub repo** `[app-slug]` (you do this by hand):
    - add all 5 members and create `develop`
    - protect `main` and `develop`: PR required, 1 approval, CI must pass, no force-push
    - **turn off squash merging and rebase merging**; allow merge commits only
    - turn on secret scanning and push protection
2. **Monorepo skeleton**, exactly as in spec 18: `backend/`, `frontend/`, `ai-service/`, `shared/`, `docs/`, `deploy/`, `.github/`. Add a `README.md` (Windows/Laragon setup for all three services), `.gitignore` (`.env`, `vendor/`, `node_modules/`, `backend/public/app/`, `__pycache__/`, `.venv/`), `.editorconfig`, and `.env.example` (copy spec 17.4).
3. **`backend/composer.json`** with the spec 8 packages. Add scripts: `test`, `stan`, `cs`, `cs:fix`, `migrate`.
4. **Entry and config:**
    - `public/index.php` and `public/.htaccess`: send everything to index.php, force HTTPS when `APP_ENV != local`, and send `/app/*` that isn't a file to the React `index.html`
    - `config/settings.php` reads `.env`
    - `config/container.php`
    - **a loader that `require`s every file in `config/routes/*.php` and `config/container/*.php`**, so modules never edit a shared routes file
5. **`src/Shared`:**
    - `JsonResponder`: the `{data, meta}` and `{error:{code,message,fields}}` envelopes
    - `ErrorHandler`: maps `ValidationException` → 422, `NotFoundException` → 404, `ForbiddenException` → 403, `UnauthorizedException` → 401, `ConflictException` → 409, `QuotaExceededException` → 429, `PayloadTooLargeException` → 413, and anything else → 500. It always adds `request_id`, and never shows a stack trace when `APP_DEBUG=false`.
    - `Db` (a DBAL connection factory)
    - `Translator` (JSON catalogues in `translations/`, by locale)
    - `Clock`
6. **Middleware stubs**, registered in the spec 11 order: `SecurityHeadersMiddleware`, `RequestIdMiddleware`, `RateLimitMiddleware`, `TurnstileMiddleware` (opt-in per route), `AuthMiddleware`, `RoleGuard`. Policy checks live in services, and validation lives in request DTOs.
7. **Doctrine Migrations config**, plus the first migration: `users` (every spec 9.1 column), `user_tokens`, `login_events`, `rate_limits`, `audit_logs` and `settings`. Use `utf8mb4_unicode_ci`, `DATETIME(3)`, and a `uuid CHAR(36)` on `users`.
8. **`GET /api/v1/health`** returning `{"data":{"status":"ok","time":"<ISO8601>"}}`.
9. **`docs/api/openapi.yaml` v0:**
    - info, servers, the `bearerAuth` scheme
    - schemas for `Error`, `Pagination`, `User`
    - **every auth endpoint** from spec 10.2
    - add `npm run mock` in a root `package.json`, running `npx @stoplight/prism-cli mock docs/api/openapi.yaml -p 4010` (a dev tool only)
10. **`.github/workflows/ci.yml`**, with jobs that run only when their folder changed:
    - **backend:** `composer validate`, `cs --dry-run`, `stan`, `test`, `composer audit`
    - **frontend:** lint, typecheck, test, build, `npm audit --audit-level=high`
    - **ai-service:** ruff, mypy, pytest, pip-audit
11. **Repo files:** `CODEOWNERS` (folder → owner, as spec 18), `pull_request_template.md` (with the spec 20 definition-of-done checklist), and issue templates (bug, task).
12. **`deploy/deploy-staging.ps1`** (and a `.sh` version):
    - build the frontend into `backend/public/app`
    - run `composer install --no-dev --optimize-autoloader`
    - upload `backend/` over FTP to InfinityFree
    - then deploy a "hello" page to `https://[app-domain]`

### Acceptance

- [ ] CI is green on `develop`.
- [ ] `GET /api/v1/health` works locally (`http://localhost:8080`) and on staging over HTTPS.
- [ ] Migrations run `migrate` and `migrate:prev` cleanly.
- [ ] A teammate reaches a running setup using only the README.
- [ ] PHPUnit covers `JsonResponder` and `ErrorHandler`.

---

## B1 — Authentication (weeks 2–3)

**Goal:** the complete auth system from spec 5.2 and 11.1 works and is tested.

### Tasks

1. **`PasswordHasher`** (bcrypt via `password_hash(..., PASSWORD_BCRYPT, ['cost'=>12])`) and **`PasswordPolicy`**:
    - 10–72 characters
    - at least 1 letter and 1 number
    - not in `resources/common-passwords.txt` (the top 10,000)
2. **`TurnstileVerifier`:** POST to Cloudflare's siteverify and check `success`, `hostname == APP host` and `action`. Locally, use Cloudflare's test keys from `.env.example`.
3. **`RateLimiter`** (DB fixed windows) and `RateLimitMiddleware`, with the named buckets from spec 11.4.
4. **`JwtService`:**
    - issue and verify **access tokens** (HS256, `JWT_ACCESS_SECRET`, 15 minutes; claims `sub`, `role`, `tv`, `iat`, `exp`, `iss`, `aud=api`, `jti`)
    - issue and verify **refresh tokens** (HS256, `JWT_REFRESH_SECRET`, 14 days; claims `sub`, `jti`, `iat`, `exp`)
5. **`RefreshCookie`:** `__Secure-rt`, HttpOnly, Secure, SameSite=Strict, `Path=/api/v1/auth`, `Max-Age=1209600`.
6. **`UserTokenService`:** `issue(userId, type, ttl, payload)` returns the raw token and stores only its SHA-256. `consume(rawToken, type)` checks it's unexpired and unused, then marks it used. The types are `verify_email`, `reset_password`, `admin_invite` and `change_email`.
7. **`POST /auth/register`:**
    - Turnstile and validation (spec 5.2 fields; phone in E.164)
    - lowercase the email
    - create the user with `status=pending_verification`
    - send a verify-email token (valid 24 hours) by email
8. **`POST /auth/login`:**
    - Turnstile and rate limit
    - **the same error for an unknown email and a wrong password**
    - lock the account for 15 minutes after 5 failures
    - write `login_events`
    - send a new-device email when the IP + user-agent hash is new
    - return `{access_token, user}` and set the refresh cookie
    - unverified users get a token but `user.status=pending_verification`, so the frontend shows the verify screen
9. **`POST /auth/refresh`:**
    - require an `Origin` header equal to `APP_URL`, and `X-Requested-With: [app-slug]`
    - verify the cookie JWT and compare its SHA-256 with `refresh_token_hash`
    - **rotate**: issue a new token, move the old hash to `refresh_token_prev_hash`
    - **reuse detection**: if the token matches `refresh_token_prev_hash`, clear both hashes, increase `token_version`, send the security email, and return 401
10. **`POST /auth/logout`** (clear the hashes and expire the cookie), plus an `AuthService::signOutEverywhere(user)` helper.
11. **Remaining endpoints:**
    - `POST /auth/verify-email` and `/auth/verify-email/resend` (3 per hour)
    - `POST /auth/password/forgot`: always returns 200; the link is valid 60 minutes
    - `POST /auth/password/reset`: signs out everywhere
    - `POST /auth/invite/accept`: sets the password, `status=active`, `email_verified_at=now`
12. **Google OAuth** (`league/oauth2-google`):
    - `/auth/google/redirect` puts state + the PKCE verifier into a short-lived signed cookie
    - `/auth/google/callback` verifies the state, exchanges the code and requires `email_verified`
    - **no account:** create one (`password_hash=null`) and redirect to `/app/complete-profile`
    - **existing account with the same `google_sub`:** log in
    - **existing password account with that email:** redirect to `/app/login?link=google`, so the user confirms with `POST /auth/google/link` (password required)
    - on success, set the refresh cookie and redirect to `/app`
13. **`AuthMiddleware`:** verify the JWT and `tv == users.token_version`, and require `status=active` (pending users may only call `/me`, `/auth/*` and `/auth/verify-email/resend`). It puts the user on the request attribute `user`.
14. **`RoleGuard`:**
    - route options `role:admin` and `role:super_admin`
    - `RoleGuard::assertCanActOn(actor, target)`: an admin can only act on `role=user`, nobody can change their own role, and the last active super admin can't be demoted, suspended or deleted
    - any violation returns 403 and writes the audit entry `security.forbidden`
15. **The `Policy` interface** (`can(User $user, string $ability, object $resource): bool`) for P2's `ProjectPolicy`.
16. **`AuditLogger::log(actor, action, targetType, targetId, reason, meta)`**, using the event names in spec 11.5.
17. **CLI:** `php bin/console app:create-super-admin --email=... --first=... --last=...` creates an invited super admin and prints a one-time link.

### Acceptance (PHPUnit)

- [ ] register → verify → login → refresh → logout works end to end
- [ ] a replayed old refresh token clears the session and increases `token_version`
- [ ] 5 failed logins lock the account; the 6th is refused even with the right password
- [ ] an expired or already-used token is rejected
- [ ] an admin acting on an admin gets 403 and an audit row is written
- [ ] Google linking to a password account requires the password
- [ ] P4 can log in from the React app against your local API

---

## B2 — Profile & hardening (weeks 4–5)

1. **`SecurityHeadersMiddleware`:**
    - send the exact CSP and headers from spec 11.3
    - send `X-Robots-Tag: noindex, nofollow` on `/app*` and `/api*`, and on **every** response when `APP_ENV != production`
2. **Profile endpoints:**
    - **`GET` / `PATCH /me`:** names, phone, alt_phone, institution, discipline, locale, theme, citation_style, notification_prefs
    - **`POST /me/password`:** requires the current password; signs out everywhere
    - **`POST /me/email`:** verify the new address, notify the old one
    - **`DELETE /me`:** requires the password; soft delete; P2's purge happens after 30 days
3. Review P2's source-upload code against spec 11.2 ("Malicious uploads" and "SSRF").

**Acceptance:** staging scores an A on securityheaders.com, and every `/me` test passes.

## B3 — Review & contract (weeks 6–7)

1. Keep `openapi.yaml` in step with every endpoint P2 adds, including all `/sources/*` endpoints. **Reject any PR that adds an endpoint without updating the contract.**
2. Be the main reviewer for P2's research endpoints, checking that `ProjectPolicy` runs on every query.
3. At the end of the batch, merge `develop` → `main`, tag `v0.3` and deploy to staging.

## B4 — Security test (weeks 8–9)

1. With one teammate, run the **whole spec 20 security checklist** on staging, including the new SSRF and image tests. Log each issue as a GitHub issue labelled `security`, and fix or assign each one.
2. Add database indexes for audit-log filters: `(actor_id, created_at)` and `(action, created_at)`.
3. Tag `v0.4`.

## B5 — Hardening (weeks 10–11)

1. Clear all dependency audit warnings.
2. Grep the logs to confirm no secrets, tokens or full JWTs.
3. Document the weekly staging backup (a phpMyAdmin export) in `docs/handover.md`.
4. Retest OAuth and the cookies over HTTPS on staging. Tag `v0.5`.

## B6 — Launch (week 12)

1. Final review, then merge `develop` → `main` and tag `v1.0`.
2. Deploy, and smoke-test the spec 20 end-to-end journey on staging.
3. Finish `docs/handover.md`: deploy steps, key rotation, creating a super admin, restoring a backup.

---

## Definition of done (every PR)

- [ ] Matches the spec; any spec change is in the same PR
- [ ] Tests written and passing; CI green
- [ ] Translation keys in en, fr and es for any user-facing text
- [ ] No secrets, `var_dump` or commented-out code
- [ ] The PR follows the template, and the folder owner has approved
- [ ] Branch name `feature/p1-Fxx-short-name`; Conventional Commits with the feature id
