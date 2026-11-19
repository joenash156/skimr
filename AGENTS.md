# Instructions for AI coding assistants

This repository is **Skimr** (slug `skimr`), a 5-person monorepo.

## Read before doing anything

1. `docs/specs/00-MASTER-SPEC.md` is the single source of truth.
2. `docs/specs/P<n>-*.md` is the brief for the member you are working with. Each brief is split into batches (B0–B6).

If the user says something short like "start batch 2" or "B3 task 4":

- Find out which member (P1–P5) they are. If they haven't said, **ask first**. Don't guess from the branch name or the files.
- Open that member's brief and the master spec, then follow section 0 ("Instructions for the AI agent") of the brief.
- Confirm the batch and task back to the user before you write code.

## Hard rules

- Edit files **only inside the folders the member owns** (master spec §18, and section 2 of their brief). If a change is needed elsewhere, say exactly what to ask the owner for. Don't make the change yourself.
- Use only the libraries in master spec §8.
- Shared files (`composer.json`, `package.json`, lock files, `docs/api/openapi.yaml`, `shared/design-tokens.css`, anything in `docs/specs/`) change only in small dedicated PRs by their owner.
- Never commit secrets. `.env` is git-ignored, and `.env.example` holds placeholders only.
- Follow the git workflow in `docs/git-workflow.md`. Work on a `feature/p<n>-F<xx>-short-name` branch cut from `develop`, never on `main` or `develop`.
