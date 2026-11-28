# Translations awaiting review

Spec 13: machine translation is allowed for a first draft, but a team member who reads the language reviews it before release. Until then, the strings are listed here.

When you review a file, tick it and write your name and the date. When you add machine-drafted FR or ES strings, add a row.

| File | Keys | Drafted by | FR reviewed | ES reviewed |
| --- | --- | --- | --- | --- |
| `backend/translations/api/shared.{fr,es}.json` | all `errors.*` (11 keys) | P1, B0 (machine draft) | [ ] | [ ] |
| `backend/translations/api/shared.{fr,es}.json` | `errors.turnstile_failed`, `errors.invalid_token`, `errors.email_not_verified` | P1, B1 (machine draft) | [ ] | [ ] |
| `backend/translations/api/auth.{fr,es}.json` | `validation.password.*` (3 keys) | P1, B1 (machine draft) | [ ] | [ ] |
| `backend/translations/api/shared.{fr,es}.json` | `validation.*` (8 keys: required, too_long, too_short, invalid, email, phone, accepted, confirmation) | P1, B1 (machine draft) | [ ] | [ ] |
| `backend/translations/api/auth.{fr,es}.json` | `auth.errors.*` (5 keys), `auth.messages.reset_link_sent` | P1, B1 (machine draft) | [ ] | [ ] |
| `backend/translations/api/auth.{fr,es}.json` | `auth.messages.email_change_sent` | P1, B2 (machine draft) | [ ] | [ ] |
| `backend/translations/public/{fr,es}.json` | `public.home.demo.*` (hero example synthesis, 8 keys) | P1, polish of P5 B2 (machine draft) | [ ] | [ ] |
| `backend/translations/public/{fr,es}.json` | `public.slogan` (moved from `home.eyebrow`), `public.theme.{legend,light,dark,system}`, `public.home.kickers.*` (5 keys) | P1, public UI v2 (machine draft) | [ ] | [ ] |
| `backend/translations/public/{fr,es}.json` | `public.get_started`, `public.home.slides.*` (default carousel headings and alt text), `public.home.carousel.*` | P1, hero carousel (machine draft) | [ ] | [ ] |
| `backend/translations/public/{fr,es}.json` | `public.pages.common.*`, `public.pages.about.*` (mission, audience, principles, facts, CTA), `public.pages.contact.*` (topics, reply note, links, form heading), `public.pages.features.tier_intros.*`, `public.pages.how_it_works.cta_heading` | P1, inner pages redesign (machine draft) | [ ] | [ ] |
| `backend/translations/public/{fr,es}.json` | `public.socials.*`, `public.footer.{product,legal,contact}`, `public.pages.contact.reach_heading`; page titles now use "|" | P1, brand + socials (machine draft) | [ ] | [ ] |
| `frontend/src/locales/{fr,es}/auth.json` | `modal.{tabs_label,tab_login,tab_register}` (auth modal switch) | P1, auth modal (reuses the existing "Log in"/"Sign up" wording) | [ ] | [ ] |
