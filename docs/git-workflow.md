# Git workflow

How the five of us share one repo without breaking each other's work. Summary rules are in master spec §18.2; this is the step-by-step version.

## Branches

| Branch | What it is | Who writes to it |
| --- | --- | --- |
| `main` | Released code, tagged `v0.1`, `v0.2`, ... at the end of each batch | P1 only, by merging `dev` |
| `dev` | Finished, reviewed work from everyone | P1 only, by merging pull requests |
| `feature/p<n>-F<xx>-short-name` | Your work in progress | You |

Other prefixes: `fix/p4-sidebar-collapse`, `chore/p1-ci`, `docs/p5-seo`.

**Never commit directly to `main` or `dev`.** Both are protected on GitHub, so the push will be refused anyway.

## First-time setup (once per laptop)

```powershell
git clone <repo-url> skimr
cd skimr
git config user.name  "Your Name"
git config user.email "the-email-on-your-github-account"
git config pull.rebase true
git switch dev
```

## Daily loop

### 1. Start a task: branch from the latest `dev`

```powershell
git switch dev
git pull
git switch -c feature/p3-F07-pdf-extract
```

### 2. Commit small and often (Conventional Commits, with the feature id)

```powershell
git add ai-service/app/pipeline/intake/pdf
git commit -m "feat(ai): extract text page by page with PyMuPDF (F07)"
```

Stage the files by path, not with `git add .`, so you don't commit stray files by accident. Run `git status` before every commit.

### 3. Every morning, and before you open a PR: catch up with `dev`

```powershell
git fetch origin
git rebase origin/dev
```

If you had already pushed this branch, the rebase rewrites its history, so push it with:

```powershell
git push --force-with-lease
```

Only ever force-push **your own feature branch**. Never force-push `dev` or `main`.

### 4. Push and open a pull request into `dev`

```powershell
git push -u origin feature/p3-F07-pdf-extract
```

On GitHub, open a PR with **base: `dev`** (not `main`). Fill in the template: what, why, feature id, screenshots, checklist. Tell P1 it's ready.

### 5. After your PR is merged

```powershell
git switch dev
git pull
git branch -d feature/p3-F07-pdf-extract
```

Then start the next task from step 1. Don't keep working on a merged branch.

## Avoiding merge conflicts

- **Stay inside the folders you own** (spec §18, section 2 of your brief). This rule prevents nearly all conflicts.
- Keep branches short: under 3 days, one task or a few small related ones.
- Rebase on `dev` every morning (step 3).
- **Shared files** change only in a small PR of their own, made by their owner: `composer.json`, `package.json`, the lock files, `docs/api/openapi.yaml`, `shared/design-tokens.css` and `docs/specs/*`. If you need a library or an endpoint change, ask the owner.
- Never hand-edit `composer.lock` or `package-lock.json`. If one conflicts, take `dev`'s version, then re-run `composer install` or `npm install`.

## Resolving a conflict during a rebase

```powershell
git status                      # lists the conflicted files
# open each file and fix the <<<<<<< ======= >>>>>>> blocks, keeping both sides' intent
git add path/to/fixed-file
git rebase --continue
# to abandon and go back to how things were:
git rebase --abort
```

If the conflict is in a file you don't own, stop and ask that file's owner. Don't guess.

## For P1: merging and releases

- Merge PRs on GitHub with **"Create a merge commit"**. Squash and rebase merging are turned off, so every member's individual commits stay visible.
- Merge order inside a batch: shared and contract PRs first (`openapi.yaml`, `composer.json`, migrations, design tokens), then the feature PRs that depend on them.
- If GitHub says a PR "can't be merged automatically", ask its author to rebase (step 3) and push again. Don't fix it in the browser.
- At the end of each batch:

```powershell
git switch dev
git pull
git switch main
git pull
git merge --no-ff dev -m "release: batch B<n>"
git tag -a v0.<n> -m "Batch B<n>"
git push origin main --tags
```

With branch protection on, the same thing is done as a PR from `dev` into `main`, and the tag is added after it's merged:

```powershell
git switch main
git pull
git tag -a v0.<n> -m "Batch B<n>"
git push origin v0.<n>
```

**Merging into `main` deploys to staging automatically** (`.github/workflows/deploy-staging.yml`, guide in `deploy/README.md`). Only merge into `main` what should be live, then do the manual checks listed in the workflow's summary: open `/api/v1/health` in a browser, and run migrations if the summary says they changed.

**Every PR that adds or changes an `/api/v1` route must update `docs/api/openapi.yaml` in the same PR.** CI's contract check (`composer contract`) fails otherwise.
