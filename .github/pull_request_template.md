<!--
PR title: Conventional Commit style with the feature id, e.g.
  feat(sources): validate PDF magic bytes (F06)
Base branch must be `dev` (not `main`).
-->

## What

<!-- One or two sentences. -->

## Why

<!-- Link the spec section and/or issue. -->

**Feature id:** F__ · **Batch:** B_ · **Member:** P_

## Screenshots / recording

<!-- Required for UI changes: light + dark, 360 px + 1440 px. Delete for backend-only PRs. -->

## How to test

<!-- Exact steps or commands a reviewer can run. -->

## Definition of done (spec 20)

- [ ] The code matches the spec. Any change to the spec is in this same PR.
- [ ] Unit tests for the new logic pass, and CI is green (lint, static analysis, tests, dependency audit).
- [ ] Every user-facing string uses translation keys in EN, FR and ES (FR/ES may be listed in `docs/i18n-review.md`).
- [ ] Works in light and dark mode, at 360 px and 1440 px, using only the keyboard (UI changes).
- [ ] Loading, empty and error states are handled (UI changes).
- [ ] No secrets, `console.log`, `var_dump` or commented-out code.
- [ ] New or changed endpoints are in `docs/api/openapi.yaml` (merged first, or in this PR).
- [ ] I only changed files in folders I own, or the owner is tagged below.

<!-- Owners of any other folder touched: -->
