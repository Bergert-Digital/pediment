# Pediment Landing Page — Design Spec

**Date:** 2026-05-19
**Status:** Approved (brainstorming) — pending implementation plan
**Repo:** `wp-starter-theme` (parent) only

## Problem

Plans 1–7 built every Pediment block and the design tokens, but the locked
mockup (`docs/design/pediment-mockup.html`) was never composed into an actual
page. `inc/seed.php`'s `home` is a placeholder (`hero + cta + blog-index`), so
the static front page is a bare stub, not the designed landing page.

## Goal

Ship the Pediment landing page as a **durable, fork-clean parent deliverable**:
a registered block pattern that composes the mockup's content bands from the
existing `starter/*` blocks, wired into the seed so `wp starter-theme seed`
(and Playwright global-setup) produce a real Pediment homepage on any
environment — image-free, generic/rebrandable copy.

## Locked Decisions (from brainstorming)

1. **Delivery:** Parent ships an auto-loaded block pattern; `inc/seed.php`
   builds the Home page from it and sets it as the static front page.
2. **Media:** Image-free. No binary assets, no attachment IDs. Blocks use
   their built-in CSS placeholders (hero stat-card frosted glass on tinted
   panel; blog-index `surface-elevated` media placeholder + badge; testimonial
   no avatar; steps numbered/text). The image-only `logo-cloud` band is
   omitted (see The Pattern Composition). The editor pattern and the seeded
   page are byte-identical.
3. **Copy:** Generic, rebrandable starter copy that mirrors the mockup's
   structure/lengths/tone — never the fictional "Pediment consultancy" voice.
4. **Sample content:** The seed also creates ~6 idempotent published posts
   across 3 categories so the Insights band renders fully (cards + badges +
   the presentational filter).

## Architecture & Delivery

- New file `patterns/pediment-landing.php`. WordPress auto-registers patterns
  from the theme `patterns/` directory via the file header — no code change in
  `inc/patterns.php` (the `starter` category already exists there).
- Pattern header:
  - `Title: Pediment Landing`
  - `Slug: starter/pediment-landing`
  - `Categories: starter`
  - `Block Types: core/post-content` (offered as a page-content starter)
- `inc/seed.php`: the `home` page's `content` is sourced from the registered
  pattern at seed time:
  `WP_Block_Patterns_Registry::get_instance()->get_registered( 'starter/pediment-landing' )['content']`
  (fallback: the prior inline stub string if the pattern is somehow
  unregistered, so seeding never fatals). Front-page wiring
  (`show_on_front=page`, `page_on_front`) is unchanged. `About`/`Contact`/
  `Blog` seed entries are **unchanged**.

## The Pattern Composition

**Eight** full-bleed bands. Each band = `core/group {"align":"full"}` carrying
`is-style-band-surface` or `is-style-band-navy` (registered core/group block
styles) plus the `starter-band` class for the shared `--section` vertical
rhythm, wrapping a content-width inner stack. Band styles alternate as in the
locked mockup (the mockup's logo-cloud band is omitted — see below).

| # | Band | Style | Blocks | Generic copy intent |
|---|------|-------|--------|---------------------|
| 1 | Hero | surface | `starter/hero` `variant:"stat-card"` (image-free) | eyebrow, headline, subheadline, primary + secondary CTA, `ticks[]`, `statValue`/`statText`, 2 `metrics` |
| 2 | Services | surface | `starter/feature-grid` → 3–4 `starter/feature` | icon + title + one-line generic service description |
| 3 | How we work | surface | `starter/steps` → 4 `starter/step` | `title` + `text` per step |
| 4 | Stats | **navy** | 4 × `starter/stat` in a row group | `value`/`label` (e.g. "120+ / Projects shipped") |
| 5 | Testimonial | surface | `starter/pull-quote` `variant:"testimonial"` (no avatar) | `quote` + `authorName` + `authorRole` |
| 6 | FAQ | surface | `starter/faq` → 5 `starter/faq-item` | generic `question`/`answer` |
| 7 | CTA | **navy** | `starter/cta` | `title`/`body` + primary/secondary buttons |
| 8 | Insights | surface | `starter/blog-index {"count":6,"showFilter":true}` | renders the seeded posts |

**Omitted — "Trusted by" / logo-cloud band:** `starter/logo-cloud`'s
InnerBlocks allow **only `core/image`** (it is a real client-logo strip by
design, Plan 4). Under the locked image-free decision it has nothing to
render, so the band is dropped rather than shipping an empty strip. A fork
that wants it adds `starter/logo-cloud` with its own logo images.

Header and footer are template parts (`parts/header.html`, `parts/footer.html`)
already styled to Pediment in earlier plans — they are **not** page content
and are out of scope here.

Exact generic copy strings and block attribute JSON are an implementation
concern for the plan; this spec fixes the band set, order, styles, block
choices, and variants. The plan must keep all copy industry-agnostic and
rebrandable (no "Pediment", no fictional consultancy specifics).

## Seed Changes

`inc/seed.php` gains an idempotent sample-content step inside
`starter_seed_run()`:

- Ensure 3 categories exist (create-if-missing, by slug): `insights`
  ("Insights"), `briefings` ("Briefings"), `notes` ("Notes").
- Ensure ~6 published posts exist (create-if-slug-missing), distributed across
  those categories, with generic titles + `post_excerpt`. No featured images.
- Idempotent: a second `starter_seed_run()` creates nothing new and changes
  nothing (same contract as the existing page seeding).
- The `home` page content becomes the pattern content (see Delivery). The
  existing **skip-if-slug-exists** behaviour is preserved unchanged: a fresh
  seed (no Home page) creates Home from the pattern; an install that already
  has a Home page is **never clobbered** (protects real content). This is the
  correct, safe contract and is intentionally not changed.
- Consequence for viewing on `:8890`: that env already has a stub Home
  (page ID 25), so `wp starter-theme seed` will skip it and the stub remains.
  Refreshing `:8890` to the new landing is therefore an **explicit one-off
  controller action after merge** (not part of the seed contract): update the
  existing Home page's content to the registered pattern via WP-CLI. This is
  acceptable because `:8890`'s stub Home has no real content; it is a viewing
  step, deliberately separate from the idempotent seed.

## Testing

**`tests/phpunit/Patterns/PedimentLandingTest.php`** (or the repo's existing
patterns test location):

- The pattern `starter/pediment-landing` is registered after `init`.
- Its content `parse_blocks()` with zero invalid/`null`-name top-level blocks.
- It contains the eight expected blocks: `starter/hero` (with
  `variant:stat-card`), `starter/feature-grid`, `starter/steps`,
  `starter/stat` (≥4), `starter/pull-quote` (with `variant:testimonial`),
  `starter/faq`, `starter/cta`, `starter/blog-index`; it does **not**
  contain `starter/logo-cloud`; and both band styles
  (`is-style-band-surface`, `is-style-band-navy`) are present.
- `do_blocks()` of the pattern content emits no `Block "…" not found`
  / block-error markup and includes `is-variant-stat-card`, a navy band, and
  the blog-index list/empty wrapper.
- No "Pediment"/consultancy-specific copy guard: the rendered/raw pattern
  content does not contain the string `Pediment` (case-insensitive) outside
  the band-style class names, enforcing the rebrandable-copy decision.

**Seed assertions** (extend the existing seed test, or a new `SeedTest` case):

- After `starter_seed_run()`: Home exists, its content equals the pattern
  content, `show_on_front === 'page'`, `page_on_front` is the Home ID.
- ≥6 published posts across ≥2 categories exist.
- Running `starter_seed_run()` a second time adds no posts/pages and leaves
  options unchanged (idempotency).

**Verification model:** the execution worktree is not wp-env-mounted.
Per-task env-independent gates: `php -l`, pattern-header presence,
`parse_blocks()` validity of the pattern content, `npm run build`. Full
PHPUnit runs post-merge in the parent `:8888`/`:8889` base. After merge the
controller, as an explicit one-off viewing step (separate from the seed
contract — see Seed Changes), updates `:8890`'s existing Home page content to
the registered pattern via WP-CLI so the live homepage can be viewed.

## Scope Boundaries

- **Parent repo only.**
- **New:** `patterns/pediment-landing.php`; the pattern/seed tests.
- **Modified:** `inc/seed.php` (home content from the pattern; sample
  posts/categories step).
- **NOT touched:** any block source, `theme.json`, `parts/*`,
  `inc/patterns.php` logic, the child repo, `About`/`Contact`/`Blog` seed
  pages, anything `mega-*`.
- No binary assets, no images, no external URLs, no `starter_*_variants`
  changes.

## Non-Goals

- Per-client real photography/branding (revisit in the child theme later).
- Restyling or extending any block (the blocks are final from Plans 1–7).
- A page-template change — this is page *content* via a pattern, served
  through the existing front-page mechanism.
