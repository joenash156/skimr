# Deploying Skimr to staging (InfinityFree)

Owner: P1. Spec: 17.2 (setup) and 17.3 (free-tier limits).

| What | Where |
| --- | --- |
| PHP app, built React dashboard | InfinityFree, `htdocs/` (this folder's scripts and the GitHub workflow) |
| MySQL | InfinityFree, created in its control panel |
| Python AI service (P3) | **Render** free tier, not InfinityFree: InfinityFree runs only PHP |

## How deploys happen

**Automatically, on every push to `main`** (`.github/workflows/deploy-staging.yml`):

1. Builds the dashboard (once `frontend/` exists) and the backend with production-only Composer packages.
2. Refuses to continue if the build contains `.env` files, keys, `tests/` or `var/`.
3. Uploads **only the files that changed**, over FTPS (FTP encrypted with TLS). It keeps a hash list in `htdocs/.ftp-deploy-sync-state.json` and deletes only files it uploaded itself, so the server's `.env` and `var/` (logs) are never touched.
4. Writes a summary with the manual steps that apply (see "What can't be automated").

`main` only receives release PRs from `dev` that passed CI, so the deploy doesn't re-run tests.

**By hand**, if needed: `./deploy/deploy-staging.ps1` (Windows, WinSCP) or `./deploy/deploy-staging.sh` (lftp). Same build and the same exclusions.

## One-time setup

### 1. InfinityFree

1. Create the account and the site; add `skimr.foliony.app` with CNAME verification (spec 17.2). Turn on the free SSL certificate.
2. Create the MySQL database in the panel. Note its host, name, user and password.
3. Note the FTP host, user and password (panel > FTP details).

### 2. The server's `.env` (by hand, once)

The server's secrets live **only** in `htdocs/.env` on the server. They are never in the repo, never in GitHub and never uploaded by a deploy.

1. Copy `.env.example` locally to a temporary file and fill in **staging** values:
    - `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://skimr.foliony.app`
    - the InfinityFree database values
    - **new** JWT secrets (`php -r "echo bin2hex(random_bytes(64)), PHP_EOL;"` twice). Never reuse your local ones.
    - real Turnstile keys for the domain (the test keys are refused when `APP_ENV=production`)
    - Google OAuth with redirect URI `https://skimr.foliony.app/api/v1/auth/google/callback`
    - Resend, Cloudinary and AI service values when those parts are live
2. Upload it as `htdocs/.env` with the InfinityFree **file manager**, which runs over HTTPS. Then delete the local copy.
3. Check it isn't reachable: `https://skimr.foliony.app/.env` must answer **403** or **404**, never the file.

### 3. GitHub

1. **Settings > Environments > New environment** `staging`.
2. **Deployment branches and tags**: *Selected branches* > add `main`. Feature branches and PRs then can't read these secrets.
3. Add **environment secrets**: `STAGING_FTP_HOST`, `STAGING_FTP_USER`, `STAGING_FTP_PASSWORD`.
4. Add an **environment variable** (not a secret): `STAGING_URL` = `https://skimr.foliony.app`.
5. Optional: **Required reviewers** = you, so every deploy waits for your click.

## What can't be automated (spec 17.3)

InfinityFree's browser check blocks GitHub's servers, and its MySQL can't be reached from outside. So:

- **Health check:** open `https://skimr.foliony.app/api/v1/health` in a browser after a deploy.
- **Database migrations:** a deploy uploads new migration files but can't run them. The plan is a migration runner that only a signed-in super admin can trigger from the browser, with the password re-entered and an audit entry. P1 builds it as part of the staging setup, before the first deploy. Until then, nothing that needs the database should go to staging.
  - Why not import an SQL dump through phpMyAdmin? Doctrine's `--write-sql` export leaves out the `doctrine_migration_versions` rows, so the next real migration run would try to create every table again.

## Security checklist for every deploy

- [ ] `.env` on the server has `APP_ENV=staging` (or `production`) and `APP_DEBUG=false`. The app ignores `APP_DEBUG=true` outside local anyway.
- [ ] `https://<site>/.env`, `/vendor/autoload.php`, `/var/log/app.log` and `/.ftp-deploy-sync-state.json` all answer 403 or 404.
- [ ] Response headers show the CSP, HSTS and `X-Robots-Tag: noindex` (staging isn't production).
- [ ] A secret that was ever pasted into a chat, screenshot or commit is rotated the same day (spec 11.2).
