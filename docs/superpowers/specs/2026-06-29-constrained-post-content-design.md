# Constrained-layout `post-content` in `page.html` & `front-page.html`

**Date:** 2026-06-29
**Status:** Approved

## Problem

`pediment/*` blocks (cta, feature-grid, prose, hero, …) all declare `align: ["wide"]`
or `["wide","full"]` in their `block.json`. Those alignments only do anything when the
block lives inside a container with a **constrained** layout — that is what reads
`theme.json`'s `contentSize` (720px) / `wideSize` (1200px) and caps width accordingly.

Today the `core/post-content` block carries a constrained layout only on
`single.html`. On `page.html` and `front-page.html` it is `{"align":"full"}` with **no
layout**, so its children inherit no constraint: default blocks stretch full-bleed and
`align:wide` is a no-op. Child sites compensate downstream with manual width-cap
wrappers (e.g. a `.wc-wrap` element), a per-block papercut that should not be necessary.

## Goal

Give `post-content` a constrained layout on `page.html` and `front-page.html` so
width-capping happens automatically for every `pediment/*` block, removing the need for
downstream wrapper hacks. Bring those two templates in line with the already-correct
`single.html`.

## The change

In `templates/page.html` and `templates/front-page.html`:

```diff
- <!-- wp:post-content {"align":"full"} /-->
+ <!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /-->
```

This is byte-for-byte the pattern `single.html` already uses. `align:full` keeps the
content region able to host full-bleed bands; `layout:constrained` caps default children
at `contentSize` and honors `align:wide` / `align:full`.

## Out of scope (verified — no change needed)

- `single.html` — already `{"align":"full","layout":{"type":"constrained"}}`.
- `home.html`, `index.html`, `archive.html` — render via `core/query`, not
  `core/post-content`; unaffected.
- `theme.json` — `contentSize: 720px` / `wideSize: 1200px` already set.
- `src/blocks/*/block.json` — already declare the correct `align` supports.

## Breaking-change handling (option A — clean break)

This changes rendered width for existing page / front-page content on every child site:
content authored as bare full-width blocks will narrow to `contentSize` (720px) and must
opt into `align:wide` / `align:full` for wider treatment. The downstream `.wc-wrap`
workaround becomes redundant.

- Commit with a breaking-change type (`feat!` / `fix!`) so release-please cuts a **major**
  version, consistent with the theme's "major bumps for breaking changes" convention.
- Add a CHANGELOG / migration note: child themes can drop `.wc-wrap` (or equivalent
  manual width caps) for blocks inside page/front-page content; default content now caps
  at 720px.

## Testing

- Run the existing Playwright e2e suite — confirm no regressions.
- Run CI lint gates before pushing: `lint:colors` and `phpcs` (fails on warnings).
- Optional: a focused e2e assertion that a default block inside `post-content` on a
  `page` renders at constrained width. Decide whether it earns its maintenance cost when
  writing the plan.

## Risk

Any existing page/front-page content authored as bare full-width blocks visibly narrows
to 720px after upgrade. This is the intended, documented behavior of the major bump.
