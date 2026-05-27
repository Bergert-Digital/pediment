# Section Vertical-Padding Rhythm — Design

**Date:** 2026-05-16
**Status:** Awaiting user review
**Related:** revises Component D of
`2026-05-15-generator-section-groups-design.md` (the inter-section
`margin-block-start` clamp model is superseded here).

## Problem

Generated `section.starter-section` groups have no vertical padding —
Component D set `padding-inline` only. Content sits flush against the
section's top/bottom, so backgrounded sections (hero, CTA, tinted bands)
look cramped: text and controls touch the colored background edges. Inter-
section separation today comes from a `margin-block-start` clamp between
adjacent sections, which gives *gaps between* sections but never *breathing
room inside* a backgrounded section.

## Decision (user-confirmed)

Move to a **padding-driven stacked-band model**: every section owns
symmetric fluid top/bottom padding; there is no margin between sections;
backgrounded bands stack flush and meet edge-to-edge. Separation between
adjacent content becomes 2× the section padding.

Scope is the **section shell padding only**. Inter-block rhythm inside a
section (`blockGap`, currently `--wp--preset--spacing--40`) and the
internal spacing of `starter/*` blocks are explicitly out of scope.

## Change

Parent `wp-starter-theme/theme.json` → `styles.css`, two edits:

1. **Remove** the inter-section margin rule:

   ```
   .entry-content.wp-block-post-content > section.starter-section + section.starter-section{margin-block-start:clamp(3.25rem, 2rem + 6vw, 6rem)}
   ```

2. **Add** `padding-block` to the existing section rule (keep
   `padding-inline` unchanged):

   ```
   section.starter-section{padding-block:clamp(3rem, 2rem + 5vw, 5.5rem);padding-inline:var(--wp--preset--spacing--30)}
   ```

The inner-content width rule
`section.starter-section > :where(:not(.alignfull):not(.alignwide)){max-width:var(--wp--style--global--content-size, 720px);margin-inline:auto}`
is unchanged.

## Consequences (accepted)

- First/last sections gain top/bottom padding against post-title and
  footer — intended (consistent shell).
- Two plain (no-background) sections in a row are separated only by 2×
  padding of transparent whitespace — acceptable; that is the chosen
  model.
- Adjacent backgrounded bands now touch with no gap — the intended
  stacked-band look.
- Effective adjacent-content separation ≈ 6–11rem fluid (2× the clamp),
  comparable to the prior 3.25–6rem margin plus new internal breathing
  room.

## Scope / non-goals

- Affects parent `wp-starter-theme/theme.json` only. Child `theme.json`
  does not override `styles.css`. No template, JS, generator, or schema
  changes.
- Applies to all existing and future pages immediately; no page
  regeneration required.
- Not in scope: inter-block rhythm, `starter/*` block internals,
  alternating background tints, per-archetype padding.

## Verification

Real front end at `:8890` (per the project's "verify on real pages, not
fixtures" rule), on a regenerated multi-section page:

- Backgrounded sections (hero, CTA) show clear, symmetric top/bottom
  breathing room inside their background.
- Adjacent backgrounded bands meet flush (no visible gap).
- First section sits below the post-title with its own top padding; last
  section has bottom padding before the footer.
- No horizontal/inner-width regression (inner content still ≈720px
  centered; full-bleed backgrounds intact).
- Spot-check a narrow viewport: padding scales down toward the 3rem
  floor, no overflow.
