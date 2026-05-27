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

### Corrected assumption (post-implementation, 2026-05-15)

The original Change 1 below assumed *every top-level child of post-content is a
section*, so it gave `> * + *` the large gap. This is false for real AI output:
the model does **not** wrap prose in `starter/prose`. It emits bare
`core/heading` / `core/paragraph` / `core/list` as flat top-level siblings
intermixed with `starter/*` blocks. `> * + *` then put the 6rem gap between every
paragraph and list, blowing apart intra-section content. Confirmed by DOM
reproduction in wp-env.

There is no DOM wrapper marking a section, but the custom `starter/*` blocks
(hero, cta, faq, pull-quote, prose, contact-form) and `core/separator` **are**
the real section boundaries; bare `core/*` blocks are intra-section content.
Decision (user-confirmed): scope the large gap to those section signals only;
bare content runs flow at the normal tight `blockGap`. A bare run sandwiched
between two starter sections reads as one section (tight internals, 6rem
above/below). A page of pure bare prose flows at normal rhythm — acceptable, that
is normal body-copy reading, not the original "cramped sections" complaint.

### Change 1 — Section gap (corrected)

Append to `theme.json` › `styles.css` a rule applying the large fluid top margin
only at section signals: a top-level `starter/*` block or `core/separator`, and
the element immediately following one. Global `blockGap` (~1.5rem) is untouched,
so bare content flows tight.

```css
.entry-content.wp-block-post-content > :where([class*="wp-block-starter-"], .wp-block-separator):not(:first-child),
.entry-content.wp-block-post-content > :where([class*="wp-block-starter-"], .wp-block-separator) + *{
  margin-block-start: clamp(3.25rem, 2rem + 6vw, 6rem);
}
```

- `clamp(3.25rem, 2rem + 6vw, 6rem)` → 6rem desktop, ~3.25rem narrow mobile,
  fluid between.
- `:not(:first-child)` keeps the first section flush (header part provides top
  separation).
- `> S + *` puts the gap *after* a section/separator (covers bare content
  starting a new logical section, and section-after-section).
- Separator is part of `S`: its own `:not(:first-child)` margin is overridden to
  0 by Change 2, so a neutralized separator contributes a single ~6rem gap via
  the `S + *` rule on the following element — no doubling.
- `.entry-content.wp-block-post-content` wrapper confirmed by DOM inspection in
  wp-env (WP renders post-content's top-level blocks as its direct children).

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
2. **Fixture must be representative of real AI output** — bare `core/heading`
   /`core/paragraph`/`core/list` at top level intermixed with `starter/*`
   blocks, NOT everything pre-wrapped in `starter/prose`. Verifying against a
   fully prose-wrapped page is what hid the original defect. In wp-env, open
   such a page and confirm:
   - No horizontal rules anywhere.
   - ~6rem gap before/after each `starter/*` section and at each (neutralized)
     separator at desktop width.
   - A run of bare `core/*` blocks between two sections stays tightly grouped
     (~1.5rem `blockGap`), NOT 6rem apart.
   - Narrow the viewport to mobile: section gap compresses to ~3.25rem, layout
     not cramped.
3. Open a hand-built pattern page ([patterns/hero-cta-faq.php](../../../patterns/hero-cta-faq.php))
   and confirm no regression to section or internal spacing.
4. `npm run lint:colors` and existing lint tasks still pass (no new violations;
   changes are spacing-only).
