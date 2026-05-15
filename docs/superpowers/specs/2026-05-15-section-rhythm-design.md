# Section Rhythm & Divider Removal — Design

**Date:** 2026-05-15
**Status:** Awaiting user review

## Problem

Generated pages (e.g. the "Grounds & Grace" coffee-shop landing page) stack their
sections too tightly and place a full-width horizontal rule between every section.
Both make the page look unfinished and cramped.

### Root causes

1. **Tight spacing.** `theme.json` sets a single global
   `styles.spacing.blockGap` of `var(--wp--preset--spacing--40)` (~1.5rem).
   `<!-- wp:post-content /-->` renders the page's top-level blocks as siblings
   inside the constrained `main` group ([templates/front-page.html](../../../templates/front-page.html)),
   so section-to-section spacing uses the same ~1.5rem gap as paragraph-to-paragraph
   spacing inside a section. There is no distinct "section" rhythm.
2. **Ugly dividers.** `core/separator` is allowlisted as a composable block in
   wp-starter-ai (`SchemaBuilder.php`). With no layout guidance in the system
   prompt, the model inserts a full-width `core/separator` between sections as a
   crude boundary. The theme provides no styling for `core/separator`, so it
   renders as the browser-default full-width hairline.

## Decisions (from brainstorming)

- **Fix layer:** theme only. Must work for any page regardless of what the AI
  emits; no changes to wp-starter-ai or to generation/templates.
- **Section separation:** whitespace only. No rules, no background tints.
- **Spacing scale:** airy — ~6rem between sections on desktop, compressing ~45%
  on mobile.

## Approach

Smallest change that satisfies all three decisions: edit **only** `theme.json`'s
`styles.css` string. No `spacing` settings change, no block `.scss` change, no
block rebuild.

### Deviation from the design presented during brainstorming

The presented design proposed replacing `settings.spacing.spacingScale` with
explicit named `spacingSizes` and auditing/remapping block CSS. Investigation
found every block `style.scss` hardcodes the numbered presets
`--wp--preset--spacing--10..60`. Switching to named slugs would stop those CSS
custom properties from being generated and break every block's internal padding.
The remap is therefore both risky and unnecessary. This spec instead expresses
the section gap as a literal fluid `clamp()` in a single CSS rule, requiring no
preset and touching nothing else. All approved requirements are still met.

Trade-off accepted: the section gap is a literal value in one rule rather than a
reusable named token. Acceptable because it is used in exactly one place.

### Change 1 — Section gap

Append to the existing `theme.json` › `styles.css` string a rule that applies a
large fluid top margin between the direct children of post content (the section
boundaries), leaving the global `blockGap` (~1.5rem) intact for rhythm *within*
a section.

```css
.entry-content.wp-block-post-content > * + *{
  margin-block-start: clamp(3.25rem, 2rem + 6vw, 6rem);
}
```

- `clamp(3.25rem, 2rem + 6vw, 6rem)` → 6rem at desktop widths, ~3.25rem on
  narrow mobile (~46% compression), fluid in between.
- `* + *` leaves the first section with no extra top margin (the header
  template part already provides separation).
- **Selector is provisional.** `wp:post-content` wrapper markup varies by WP
  version. Implementation MUST inspect the rendered DOM in wp-env and confirm
  the actual wrapper/child relationship, adjusting the selector if needed
  (candidates: `.entry-content.wp-block-post-content > * + *`, or
  `main.wp-block-group > .entry-content > * + *`). The intent is fixed: large
  gap between top-level page sections only, never nested content.

### Change 2 — Neutralize separators

Append to the same `styles.css` string a rule rendering any emitted
`core/separator` as zero-footprint and invisible. Section rhythm from Change 1
provides all visible separation.

```css
.wp-block-separator{
  border:0!important; background:transparent!important;
  height:0!important; margin:0!important;
}
```

`!important` is used because core block-supports styles for `core/separator` are
injected with high specificity; this rule must win unconditionally.

## Non-goals

- No changes to wp-starter-ai (prompt, schema, separator allowlisting).
- No alternating backgrounds, contained-width changes, or per-block padding
  changes.
- No change to intra-section rhythm (paragraph/heading spacing within a section
  stays as-is).

## Verification

1. `theme.json` is valid JSON and the `styles.css` string parses (load site, no
   CSS console/PHP errors).
2. In wp-env, open the generated coffee-shop landing page:
   - No horizontal rules anywhere.
   - ~6rem between sections at desktop width; intra-section paragraph spacing
     unchanged (~1.5rem).
   - Narrow the viewport to mobile: section gap compresses to ~3.25rem, layout
     not cramped.
   - Confirm via devtools that Change 1's selector matches the real post-content
     children and does not leak into nested blocks.
3. Open a hand-built pattern page ([patterns/hero-cta-faq.php](../../../patterns/hero-cta-faq.php))
   and confirm no regression to section or internal spacing.
4. `npm run lint:colors` and existing lint tasks still pass (no new violations;
   changes are spacing-only).
