# Skimr

A research workspace that turns a pile of academic sources into an organised, evidence-backed literature review. Every claim traces back to the exact passage it came from.

- **Spec (single source of truth):** [docs/specs/00-MASTER-SPEC.md](docs/specs/00-MASTER-SPEC.md)
- **Your batch-by-batch brief:** `docs/specs/P<n>-*.md`
- **How we use git:** [docs/git-workflow.md](docs/git-workflow.md)
- **API contract:** [docs/api/openapi.yaml](docs/api/openapi.yaml)

| Folder | What | Owner |
| --- | --- | --- |
| `backend/` | PHP 8.2+ (Slim 4): public site, JSON API at `/api/v1`, hosts the React build | P1, P2, P5 |
| `frontend/` | React + TypeScript dashboard at `/app` | P4 (admin screens: P5) |
| `ai-service/` | Python 3.11 FastAPI service: extraction, OCR, embeddings, LLM jobs | P3 |
| `shared/` | `design-tokens.css`, used by both the Twig site and React | P4 |
| `docs/` | Specs, API contracts, decisions | P1 |
| `deploy/` | Staging deploy scripts | P1 |

---

## Setup on Windows

You only need the tools for the part you work on, but everyone should be able to run the backend.

### 1. Install the tools

| Tool | Version | Who needs it | Check with |
| --- | --- | --- | --- |
| Git | any recent | everyone | `git --version` |
| PHP | 8.2 or newer, **latest patch only** — at least 8.2.36 / 8.3.37 / 8.4.28 / 8.5.13 (Nov 2026 security release). Laragon, XAMPP, or a plain PHP zip | everyone | `php -v` |
| Composer | 2.x | everyone | `composer --version` |
| MySQL | 8.0 | everyone | `mysql --version` |
| Node.js | 20 LTS or newer | P4, P5 (and `npm run mock`) | `node -v` |
| Python | **3.11** (not 3.12+: some ML packages lag behind) | P3 | `py -3.11 --version` |
| Docker Desktop | any recent | P3 | `docker --version` |

**PHP extensions.** Open your `php.ini` (`php --ini` shows where it is) and make sure these lines have **no** leading `;`:

```ini
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=sodium
extension=zip
```

While you're there, raise the upload limits so 20-image uploads work (spec 6.6):

```ini
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 110M
```

**HTTPS certificates (Windows, plain PHP zip).** PHP for Windows ships without a list of trusted certificate authorities, so every outgoing HTTPS call (Cloudflare Turnstile, Resend, Google sign-in) fails with `cURL error 60` and sign-up/login answer `TURNSTILE_FAILED`. Fix it once (Laragon and XAMPP usually already have this):

```powershell
$php = Split-Path (Get-Command php).Source
New-Item -ItemType Directory -Force "$php\extras\ssl" | Out-Null
Invoke-WebRequest https://curl.se/ca/cacert.pem -OutFile "$php\extras\ssl\cacert.pem"
```

Then add these two lines to `php.ini` (forward slashes, your PHP folder) and restart any running `composer start`:

```ini
curl.cainfo = "C:/php-8.5/php/extras/ssl/cacert.pem"
openssl.cafile = "C:/php-8.5/php/extras/ssl/cacert.pem"
```

Check: `php -r "var_dump((bool) file_get_contents('https://curl.se'));"` prints `bool(true)`.

Check with `php -m`: every extension above should be listed.

**Composer** (if `composer --version` fails): install it from https://getcomposer.org/download/ (the Windows installer is easiest).

### 2. Clone and configure

```powershell
git clone https://github.com/joenash156/skimr.git
cd skimr
git switch dev
Copy-Item .env.example .env
```

Edit `.env` and set at least `DB_USER` and `DB_PASS` for your local MySQL. Leave the Turnstile test keys as they are. Ask P1 privately for any real keys you need. **Never commit `.env`, and never paste keys into chats or AI assistants.**

Generate your own local JWT secrets:

```powershell
php -r "echo bin2hex(random_bytes(64)), PHP_EOL;"   # run twice: one for JWT_ACCESS_SECRET, one for JWT_REFRESH_SECRET
```

The API refuses to start while these are still the `change-me` placeholders (every `/api/` call returns 500 and the log says why).

### 3. Backend (PHP), at http://localhost:8080

```powershell
mysql -u root -p -e "CREATE DATABASE skimr_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cd backend
composer install
composer migrate
composer start
```

Open http://localhost:8080/api/v1/health. You should see `{"data":{"status":"ok",...}}`.

Useful commands (run in `backend/`):

| Command | What it does |
| --- | --- |
| `composer start` | Dev server on http://localhost:8080 |
| `composer test` | PHPUnit. Needs MySQL running: it creates and migrates a separate `skimr_test` database first, so your dev data is untouched |
| `composer stan` | PHPStan, level 6 |
| `composer cs` / `composer cs:fix` | Check / fix PSR-12 code style |
| `composer migrate` / `composer migrate:prev` | Apply all migrations / undo the last one |
| `vendor/bin/doctrine-migrations generate` | Create a new empty migration |
| `php bin/console list` | CLI commands |

### 4. API mock server (no backend needed), at http://localhost:4010

Frontend work against endpoints that aren't built yet uses mocks generated from the contract:

```powershell
npm run mock        # in the repository root
```

### 5. Frontend (React), at http://localhost:5173/app/

Set up by P4 in batch B0. Vite proxies `/api` to `http://localhost:8080`, so run the backend too (or point the proxy at the mock).

### 6. AI service (Python), at http://localhost:8000

Set up by P3 in batch B0. It has its own `ai-service/.env`.

---

## Working on a task

1. Read your brief's section 0, then the batch you're on.
2. Branch from `dev`: `git switch dev; git pull; git switch -c feature/p2-F06-pdf-upload`.
3. Stay inside the folders you own (spec 18, and section 2 of your brief).
4. Commit small and often: `feat(sources): validate PDF magic bytes (F06)`.
5. Push your branch and open a PR into **`dev`**. Fill in the template.

Full details: [docs/git-workflow.md](docs/git-workflow.md).

### Adding backend code without touching shared files

- **Routes:** create `backend/config/routes/<module>.php` returning `function (Slim\App $app): void { ... }`. It's loaded automatically.
- **Services:** create `backend/config/container/<module>.php` returning an array of PHP-DI definitions.
- **API messages:** create `backend/translations/api/<module>.{en,fr,es}.json`. They're merged automatically.
- **CLI commands:** add `'console.commands' => \DI\add([YourCommand::class])` to your container file.
- **Errors:** throw an exception from `App\Shared\Exception` (`NotFoundException`, `ValidationException`, ...). `ErrorHandler` turns it into the spec 10.1 error response.
- **Responses:** use `App\Shared\Http\JsonResponder`, never `json_encode` in a controller.

## Staging

https://skimr.foliony.app (InfinityFree + Render). Deployed by P1 at the end of each batch with `deploy/deploy-staging.ps1`. Test the API locally, not against staging: InfinityFree blocks requests that don't come from a real browser.
