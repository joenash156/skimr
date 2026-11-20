# Instructions for AI coding assistants

This repository is **Skimr** (slug `skimr`), a 5-person monorepo.

## Read before doing anything

1. `docs/specs/00-MASTER-SPEC.md` is the single source of truth.
2. `docs/specs/P<n>-*.md` is the brief for the member you are working with. Each brief is split into batches (B0–B6).

If the user says something short like "start batch 2" or "B3 task 4":

- Find out which member (P1–P5) they are. If they haven't said, **ask first**. Don't guess from the branch name or the files.
- Open that member's brief and the master spec, then follow section 0 ("Instructions for the AI agent") of the brief. Read section 3a ("Notes from P1") too: it describes what already exists in the repo (loaders, shared classes, CI requirements, the CSP nonce). Build on it instead of recreating it.
- Confirm the batch and task back to the user before you write code.

Team (master spec §19):

| Role | Member | GitHub |
| --- | --- | --- |
| P1 – Tech lead, backend core | tech lead | @joenash156 |
| P2 – Backend features | Prince | @Prina5 |
| P3 – AI service | Kwantsimah | @kwantsimahschool |
| P4 – Dashboard frontend | Nana | @NanaBanyinKwofie |
| P5 – Public site, SEO, admin UI | Caleb | @CALEB-KOUFIE |

## First session on a machine

Before the first batch, check the toolchain against `README.md` ("Tools") and stop if it is behind:

- `php -v` must be the **latest patch** of 8.2–8.5: at least 8.2.36 / 8.3.37 / 8.4.28 / 8.5.13 (November 2026 security release). Older patches have remote-code-execution bugs. Everyone runs PHP, including P3 and P4, to serve the app locally.
- P3: `python --version` is 3.11.x. P4/P5: `node -v` is 20.x.
- The PHP app's `.env` lives at the **repo root**, copied from `.env.example`. Don't create `backend/.env`: the bootstrap reads it first and it would silently replace the root one. (P3's service has its own `ai-service/.env`.)

## Hard rules

- Edit files **only inside the folders the member owns** (master spec §18, and section 2 of their brief). If a change is needed elsewhere, say exactly what to ask the owner for. Don't make the change yourself.
- Use only the libraries in master spec §8.
- Shared files (`composer.json`, `package.json`, lock files, `docs/api/openapi.yaml`, `shared/design-tokens.css`, anything in `docs/specs/`) change only in small dedicated PRs by their owner.
- Never commit secrets. `.env` is git-ignored, and `.env.example` holds placeholders only.
- Follow the git workflow in `docs/git-workflow.md`. Work on a `feature/p<n>-F<xx>-short-name` branch cut from `dev`, never on `main` or `dev`.
