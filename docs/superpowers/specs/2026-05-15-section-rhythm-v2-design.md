# Section Rhythm v2 — Separator-as-Boundary — Design

**Date:** 2026-05-15
**Status:** Awaiting user review
**Supersedes:** `2026-05-15-section-rhythm-design.md` (reverted; see commit `275e7a0`)

## Problem

Generated pages stack sections too tightly and show ugly full-width
horizontal rules between them. The v1 spec assumed top-level post-content
children could be classified into "sections" by CSS sibling heuristics. Two
implementations failed in production because real AI output is a flat,
semantically-ambiguous block stream: the same sibling adjacency means
different things (a bare heading is a section *title* hugging the next block;
a separator is a real *break*; `stat`×3 is a tight *band*). v1 was reverted.

## Evidence — real generated pages

Inspected the actual block markup of 5 generated pages in the child-theme
env (`localhost:8890`): Coffee Shop (8), Hair Salon (12), Mechanic (16/18/20).
Consistent structure on every page:

```
starter/hero
starter/stat × 3            ← tight band, no separators between
core/separator              ← SECTION BREAK
core/heading  (bare title)
starter/prose               ← title's body, no separator before it
core/separator              ← SECTION BREAK
starter/pull-quote
core/separator              ← SECTION BREAK
core/heading + starter/faq
core/separator              ← SECTION BREAK
core/heading + starter/cta
starter/contact-form
starter/social-links
```

**Key finding:** `core/separator` is the AI's deliberate, consistent section
delimiter. Blocks that belong together (a bare heading + its body block, the
`stat`×3 band, the trailing contact cluster) have **no** separator between
them. Real section breaks **always** have one.

## Decisions (from re-brainstorming)

- **Fix layer:** theme only. Pure CSS in `theme.json`. No PHP, no AI changes.
- **Boundary signal:** `core/separator` (the AI's own delimiter) — not
  synthesized from other adjacencies.
- **Separator appearance:** invisible — pure whitespace, no visible line.
- **Section gap:** airy — ~6rem desktop, ~3.25rem mobile (fluid).
- **Intra-section spacing:** unchanged (default `blockGap` ≈ 1.5rem stays).

## Approach

Append one rule to `theme.json` › `styles.css`. The separator becomes a
zero-height, invisible element whose symmetric vertical margin *is* the
section gap.

```css
.wp-block-separator{
  border:0!important;
  background:transparent!important;
  height:0!important;
  margin-block:clamp(3.25rem, 2rem + 6vw, 6rem)!important;
}
```

- `border/background/height` zeroed → no visible rule.
- `margin-block: clamp(3.25rem, 2rem + 6vw, 6rem)` → ~6rem desktop, ~3.25rem
  narrow mobile, fluid between. Applied symmetrically (top and bottom) so the
  gap reads centered where the AI placed the delimiter.
- `!important` — core block-supports styles for `core/separator` are injected
  with high specificity; this rule must win unconditionally.
- Everything that is **not** a separator keeps the default `blockGap`
  (~1.5rem): a bare heading hugs its following body block, `stat`×3 stays a
  tight band, the contact cluster stays grouped. This is correct because the
  AI omits separators exactly where it intends grouping.

### Why this is robust where v1 was not

v1 tried to *hide* separators and *infer* boundaries from non-separator
adjacency — impossible, because adjacency is ambiguous. v2 uses the one
unambiguous, consistently-present signal the AI emits. No classification, no
sibling heuristics, no per-block-type rules. Graceful degradation: a page
with no separators flows at normal tight rhythm (normal body copy, not the
original "cramped sections" complaint).

## Non-goals

- `starter/stat` band horizontal layout (separate concern).
- `starter/prose` internal heading/paragraph rhythm (block's own `style.scss`).
- wp-starter-ai prompt/schema changes.
- Changing `settings.spacing` or any block `style.scss`.

## Verification

Verify against **real generated pages in the child-theme env
(`localhost:8890`)** — NOT a hand-built fixture (verifying against a
prose-wrapped fixture is what hid the v1 defect):

1. `theme.json` valid JSON; site loads with no CSS/PHP errors.
2. Playwright, pages 8/12/16/20 at 1280px width — read computed
   `margin-block-start`/`-end` of every top-level post-content child:
   - Each `core/separator`: height 0, no visible border/background. Confirm
     the *effective* rendered gap at the separator ≈ 96px (6rem) — measure
     the actual distance between the adjacent sections, not just the declared
     margin, so vertical margin-collapse with neighbors' `blockGap` is
     accounted for. If collapse changes the effective gap materially, adjust
     (e.g. switch to padding-block or a one-sided margin) and re-verify.
   - The "Services We Offer" `core/heading` → following `starter/prose`: gap ≈
     default `blockGap` (~24px / 1.5rem), NOT ~96px. (This is the exact
     regression from the v1 screenshot.)
   - `stat`×3: tight (~`blockGap`) between each, not ~96px.
   - No element renders a visible horizontal rule.
3. Same pages at 375px: separator margin compresses to ≈ 52–55px (~3.25rem);
   intra-section spacing unchanged.
4. A hand-built pattern page ([patterns/hero-cta-faq.php](../../../patterns/hero-cta-faq.php))
   — confirm no regression (it has no separators → flows at normal rhythm,
   acceptable and unchanged from pre-v1 baseline).
5. `npm run lint:colors` / `lint:blocks` pass (spacing-only change).
