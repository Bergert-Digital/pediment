# `starter/section-head` Block + Landing Layout Fixes — Design

**Date:** 2026-05-20
**Scope:** New `starter/section-head` block, migration of the Services and Insights bands on `patterns/pediment-landing.php` to use it, and three theme-level layout fixes the audit surfaced. A regression-guard E2E spec is included.

## Goal

Stop fighting WordPress's layout system with `!important` overrides on a plain `.head` class, and align the three rendered landing bands that currently diverge from [docs/design/pediment-mockup.html](docs/design/pediment-mockup.html). Close the verification loop so the same class of bug can't ship blind again.

## Background

Audit at viewport 1440×900 ([tools/audit-landing.mjs](tools/audit-landing.mjs), output in `test-results/audit/`) compared the rendered front page on `:8890` to the mockup. Measured pixel positions surfaced three concrete bugs:

| Band | Mockup intent | Rendered | Root cause |
| --- | --- | --- | --- |
| Services | `.head` and `.grid3` share left edge at x=170 (inside `.wrap`) | `.head` at x=0, feature-grid at x=120 — 120px gap | `assets/css/theme.css:34-40` `.head { margin-inline-start:0 !important }` anchors to the band's *full-bleed* left edge instead of the `alignwide` content edge |
| Testimonial | `.quote` is 880px centered (`max-width:880px;margin:0 auto`) | Quote is 1200px wide, single line | `starter/pull-quote` is set to `align:wide` with no internal cap; mockup expects narrower |
| CTA | Card at x=130 w=1180 (wide-size) | Card at x=60 w=1320 — 60px wider on each side than alignwide allows | `<section class="starter-cta">` computes `box-sizing: content-box` (no global `border-box` reset in our CSS), so `max-width:1200 + padding:60+60 = 1320` outer width |

The Services bug, observable in the user's screenshot, is the most visible one. The other two are the same class of mistake — block-internal styling silently disagreeing with the band's layout contract — and the lack of a global `box-sizing` reset is a latent landmine for any future block.

## Design

### A. New block: `starter/section-head`

A server-side block that owns the eyebrow + headline + lead pattern. It replaces the `.head` CSS shim in `theme.css` and gives every band that needs a section intro one consistent, contract-respecting source of truth.

**Files:**

| File | Change |
| --- | --- |
| `src/blocks/section-head/block.json` | new — block manifest |
| `src/blocks/section-head/edit.tsx` | new — editor UI (three `RichText` inputs + InspectorControls for alignment/level) |
| `src/blocks/section-head/render.php` | new — server-side render |
| `src/blocks/section-head/style.scss` | new — block styles (front + editor share) |
| `src/blocks/section-head/index.ts` | new — `registerBlockType` entry, following existing block pattern |

**Attributes:**

```jsonc
{
  "eyebrow":   { "type": "string", "default": "" },
  "headline":  { "type": "string", "default": "" },
  "lead":      { "type": "string", "default": "" },
  "alignment": { "type": "string", "enum": ["start", "center"], "default": "start" },
  "level":     { "type": "number", "enum": [2, 3], "default": 2 }
}
```

**Supports:** `{ "html": false, "align": ["wide"] }`. The block expects to be used at `align:wide`; that's where the layout contract lives.

**Rendered markup:**

```html
<div class="wp-block-starter-section-head starter-section-head is-alignment-start alignwide">
  <div class="starter-section-head__inner">
    <p class="starter-section-head__eyebrow">What we do</p>
    <h2 class="starter-section-head__headline">A short headline framing the services you offer</h2>
    <p class="starter-section-head__lead">One sentence describing how your services fit together…</p>
  </div>
</div>
```

Empty fields are suppressed (the wrapping `<p>`/`<hN>` is not emitted), matching the convention in `starter/cta` and `starter/hero`. The `__inner` element exists so the outer can sit at full alignwide width (sharing the left edge with sibling alignwide blocks) while the inner column is constrained — without using `!important` to fight WordPress's auto-centering.

**Layout contract (the part that has to be exactly right):**

- Outer (`.starter-section-head`) gets `alignwide` from the block's `align` support → parent constrained band positions it at the wide-size content edge (x=120 on a 1440 viewport, *same x as sibling `.starter-feature-grid`*).
- Inner (`.starter-section-head__inner`) carries the column constraint:
  - `is-alignment-start` → `max-width: 600px; margin-inline: 0 auto;` (anchored to alignwide left edge)
  - `is-alignment-center` → `max-width: 620px; margin-inline: auto; text-align: center;`

The "anchor head to band-inside-left-edge, same as the cards" requirement is satisfied by *not* margin-tricking — the outer's natural alignwide centering gives us the right left edge for free.

**Editor:**

`edit.tsx` renders the same DOM as the front-end via `useBlockProps`, with three `RichText` controls (one per field, `allowedFormats={[]}` to keep them plaintext) and an `InspectorControls` panel for `alignment` (`ToggleGroupControl`: Start | Center) and `level` (H2 | H3). No nested block-editing — the block is a closed unit.

### B. Pattern migration

In [patterns/pediment-landing.php](patterns/pediment-landing.php):

1. **Services band** (current lines 19–30). Replace:

   ```html
   <!-- wp:group {"className":"head","align":"wide","layout":{"type":"constrained"}} -->
   <div class="wp-block-group head alignwide">
     <!-- wp:paragraph {"className":"kicker"} -->
       <p class="kicker">What we do</p>
     <!-- /wp:paragraph -->
     <!-- wp:heading {"level":2} -->
       <h2 class="wp-block-heading">A short headline framing the services you offer</h2>
     <!-- /wp:heading -->
     <!-- wp:paragraph {"className":"lead"} -->
       <p class="lead">One sentence describing how your services fit together and the outcome you deliver.</p>
     <!-- /wp:paragraph -->
   </div>
   <!-- /wp:group -->
   ```

   with:

   ```html
   <!-- wp:starter/section-head {"align":"wide","alignment":"start","eyebrow":"What we do","headline":"A short headline framing the services you offer","lead":"One sentence describing how your services fit together and the outcome you deliver."} /-->
   ```

2. **Insights band**. Prepend a centered section-head before `starter/blog-index`:

   ```html
   <!-- wp:starter/section-head {"align":"wide","alignment":"center","eyebrow":"Insights","headline":"A short headline introducing the insights grid","lead":"One sentence framing what readers will find here."} /-->
   <!-- wp:starter/blog-index {"align":"wide"} /-->
   ```

   Copy stays generic/rebrandable per the existing pattern's tone.

No other bands are migrated; their intro content lives inside the respective starter blocks already (hero, cta, faq-intro).

### C. Theme-level fixes

1. **Global box-sizing reset.** Top of [assets/css/theme.css](assets/css/theme.css):

   ```css
   *, *::before, *::after { box-sizing: border-box; }
   ```

   The mockup has this; we don't. Fixes the CTA width bug and pre-empts the same bug class for any future block.

2. **Remove the `.head` shim.** Delete lines 29–42 (`.is-layout-constrained > .head, .head { … }`, `.head h2 { … }`, `.lead { … }`). The new block owns these styles in its own `style.scss`.

3. **Pull-quote testimonial width.** In [src/blocks/pull-quote/style.scss](src/blocks/pull-quote/style.scss), add inside `.starter-pull-quote.is-variant-testimonial`:

   ```scss
   max-width: 880px;
   margin-inline: auto;
   ```

   Other variants are unchanged. This brings the rendered testimonial to the mockup's `.quote { max-width:880px; margin:0 auto }` shape.

### D. Regression guard

New file `tests/e2e/landing-layout.spec.ts`, runs at viewport `1440×900`:

```ts
test('services band: section-head and feature-grid share the same left edge', …)
test('testimonial band: quote width ≤ 900px', …)              // 880 + 20px slack
test('cta band: bounding box width matches max-width (border-box)', …)
test('insights band: section-head is centered (text-align + symmetric margins)', …)
```

Each test fetches the bounding box of the relevant elements on `/` and asserts numeric invariants. Failing → the band has drifted; the test message points at which contract is broken. The audit script ([tools/audit-landing.mjs](tools/audit-landing.mjs)) stays around for ad-hoc visual diffing but is not wired into CI — the targeted specs above are.

### E. Removals checklist

After implementation, these things should no longer exist anywhere in the repo:

- `<group className:head>` markup in patterns
- `.head` selector in `assets/css/theme.css`
- `.lead` selector in `assets/css/theme.css` (replaced by the new block's internal `__lead` class)
- `!important` overrides for layout positioning on `.head`

`grep -rn "className.*head\|\\.head\\b" assets/ patterns/ src/blocks/` should return zero matches at the end.

## Verification

Per-task TDD: each failing E2E test goes red first, then implementation makes it green. Final verification on a fresh build:

1. `npm run build`
2. `node tools/audit-landing.mjs` — visually confirm the four bands now match the mockup screenshots side-by-side in `test-results/audit/index.html`
3. `npm run e2e -- landing-layout.spec.ts` — all four tests green
4. Full PHPUnit suite (`tests/phpunit/`) — pre-existing tests still pass; the pattern's structural assertions in `PedimentLandingTest` may need updating to reflect the new `starter/section-head` block in place of the `<group className:head>` (1 expected assertion change per band migrated).

## Out of scope

- Migrating intro content for hero, CTA, FAQ, approach, or stats bands.
- Renaming or refactoring `starter/blog-index` itself.
- Mobile-specific layout fixes (the audit is desktop-only; mobile is a separate pass once desktop is parity).
- Wider `box-sizing` audit of every existing block (the global reset takes care of new code; we only fix the one block that's observably broken today — CTA).
