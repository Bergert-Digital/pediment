# Testimonial Grid Block — Design

**Date:** 2026-06-10
**Status:** Approved, ready for implementation plan
**Repos touched:** `pediment` (parent theme — new blocks, styles), `pediment-ai` (composer prompt)

## Problem

AI-composed "Kundenstimmen / Was unsere Kunden sagen" sections render as a wall of
oversized bold text. Root cause: the composer has no dedicated testimonial-collection
block, so it reaches for `pediment/pull-quote` (the standalone big-quote block) several
times in a row with `variant:"default"` and no attribution. The result is four ~34px
(`clamp(1.5rem, 2.6vw, 2.1rem)`), weight-700, centered quotes stacked vertically, with
no names or roles.

Customer names + roles/companies **are** available for these testimonials — they just
aren't being emitted.

## Goal

Give the composer the right tool: a responsive grid of compact testimonial **cards**
(quote + name + role + optional avatar), styled to the approved "Option A" direction —
soft surface cards, ~half the current text size, a name/role byline. It must look right
for any count the AI emits (2, 3, 4, 5+).

## Approved direction (Option A)

Soft surface cards in a 2-up responsive grid. Decorative accent opening-quote mark,
medium-weight quote text, byline with avatar-or-initials + bold name + muted role.

## Architecture

Mirror the existing `pediment/feature-grid` + `pediment/feature` parent/child pattern
exactly. Two new blocks in `pediment/src/blocks/`.

### `pediment/testimonial-grid` (parent)

- `block.json`: `apiVersion: 3`, `category: "pediment"`, `supports: { html: false, align: ["wide","full"] }`, `attributes: {}`.
- **Description (this is what teaches the composer when to use it):**
  *"A responsive grid of customer testimonial cards. Use for 'what our clients say' /
  Kundenstimmen sections. Contains Testimonial child blocks."*
- `render.php`: `<section class="starter-testimonial-grid">` wrapping `$content`
  (inner blocks pre-rendered) — identical shape to `feature-grid/render.php`.
- `edit.tsx`: `useInnerBlocksProps` with `allowedBlocks: ['pediment/testimonial']`,
  a template of 2–3 empty testimonials, `templateLock: false`.
- `index.tsx`: registration.

### `pediment/testimonial` (child)

- `block.json`: `parent: ["pediment/testimonial-grid"]`, `supports: { html: false, inserter: false }`.
- Attributes:
  - `quote` (string, default "")
  - `authorName` (string, default "")
  - `authorRole` (string, default "")
  - `avatarId` (integer, default 0)
- Description: *"A single customer testimonial card: quote + author name + role + optional avatar."*
- `render.php`: guard — if `quote` is empty, return "" (same as `feature`). Otherwise
  render the card (see below).
- `edit.tsx` / `index.tsx`: editor UI + registration.

## Card markup & styling

Rendered card structure (`render.php`):

```html
<figure class="starter-testimonial">
  <span class="starter-testimonial__mark" aria-hidden="true">&ldquo;</span>
  <blockquote class="starter-testimonial__quote">{quote}</blockquote>
  <figcaption class="starter-testimonial__by">
    {avatar img | initials circle}        <!-- omitted if no name and no avatar -->
    <div class="starter-testimonial__meta">
      <b class="starter-testimonial__name">{authorName}</b>
      <span class="starter-testimonial__role">{authorRole}</span>
    </div>
  </figcaption>
</figure>
```

Byline logic:
- `avatarId` set → `wp_get_attachment_image(... 'thumbnail' ...)` with class `starter-testimonial__avatar`.
- else if `authorName` set → initials circle: first letters of the first two words of the
  name, in an accent-filled circle (`starter-testimonial__initials`).
- else → no avatar element.
- `figcaption` omitted entirely if both `authorName` and `authorRole` are empty.

`style.scss` (compiled via `wp-scripts`, BEM, theme tokens — no hard-coded brand hex):

- `.starter-testimonial-grid`: `display:grid; grid-template-columns: repeat(2,1fr); gap: 22px;`
  → `@media (max-width:781px) { grid-template-columns: 1fr; }`.
- `.starter-testimonial`: `background: var(--wp--preset--color--surface)`, subtle
  `1px solid var(--wp--preset--color--border)`, `border-radius: var(--r-lg, 20px)`,
  `padding: 28px 30px`, gentle hover lift (`transform: translateY(-3px)` + subtle shadow),
  matching the feature-card interaction.
- `.starter-testimonial__mark`: `color: var(--wp--preset--color--accent)`, large, decorative.
- `.starter-testimonial__quote`: `font-size: clamp(1.05rem, 1.4vw, 1.2rem); font-weight: 600;
  line-height: 1.5; color: var(--wp--preset--color--foreground); margin: 10px 0 16px;` —
  roughly half the previous size.
- `.starter-testimonial__by`: flex row, `gap: 12px`, `align-items: center`.
- `.starter-testimonial__avatar` / `__initials`: 40px circle; initials use
  `background: var(--wp--preset--color--accent)`, white text, centered, weight 700.
- `.starter-testimonial__name`: weight 700, `foreground`. `.starter-testimonial__role`:
  `foreground-muted`, ~0.9rem.
- **No dark-band text override** (corrected from earlier draft): unlike `pull-quote`,
  testimonial cards carry their own `surface` background, so they stay light-with-dark-text
  even on a `.is-style-band-navy` band. A `pull-quote`-style override would put light text on
  a light card — a bug. The card just sits on the band as-is.

Count behavior with the 2-up grid: 4 → 2×2, 3 → 2 + 1 (orphan left-aligned, acceptable),
2 → side-by-side, 1 → single card. No special-casing.

## Registration

Both blocks auto-register through the existing `pediment_register_blocks()` /
block-manifest flow once their source dirs exist and the build runs. No manual wiring.

## AI composition (`pediment-ai`)

- Both new blocks become available to the composer automatically via `SchemaBuilder`
  (they have `description`s in `block.json`).
- Add one line of guidance to `PromptBuilder` system prompt: customer-quote /
  testimonial / Kundenstimmen sections use a `pediment/testimonial-grid` (`align:"wide"`)
  containing `pediment/testimonial` children — **not** repeated `pull-quote` blocks.
  `pull-quote` remains for a single standalone quote.
- The `section-head` block ("KUNDENSTIMMEN" / "Was unsere Kunden sagen") is unchanged and
  continues to sit above the grid inside the `starter-section` wrapper.

## Pull-quote cleanup

With `testimonial-grid` owning testimonials end-to-end, `pull-quote`'s `testimonial`
variant is redundant — and a second testimonial mechanism is exactly the tool ambiguity
that caused the composer to misbehave. Remove it, reverting `pull-quote` to a plain
quote + citation block. The variant machinery exists *only* to support the testimonial
variant (see the doc comment in `inc/pull-quote-variants.php`), so it collapses entirely.

Changes:
- `src/blocks/pull-quote/block.json`: drop the `variant`, `authorName`, `authorRole`,
  and `avatarId` attributes. Update `description` to "An emphasized quotation with
  optional citation."
- `src/blocks/pull-quote/render.php`: remove the variant normalization and the entire
  `testimonial` `<figure>` branch; keep only the `blockquote` + `cite` path. Drop the
  call to `pediment_pull_quote_variants()`.
- `src/blocks/pull-quote/style.scss`: remove the `.is-variant-testimonial` rules and the
  `is-style-band-navy ... .is-variant-testimonial` override. Keep base + band-navy
  default-quote styles. (Note: `__quote` for the plain variant is currently the same
  large `clamp(1.5rem, 2.6vw, 2.1rem)` — leave it; a standalone pull-quote is *meant* to
  be large. The size problem was four of them stacked, which the grid now owns.)
- `src/blocks/pull-quote/edit.tsx` / `index.tsx`: remove the variant selector UI and any
  testimonial-only fields / `window.pedimentPullQuoteVariants` usage.
- `inc/pull-quote-variants.php`: **delete**, and remove its `require`/include from
  `functions.php` (or wherever it's loaded). This also removes the
  `pediment_pull_quote_variants` filter and the `enqueue_block_editor_assets` inline
  script.
- `patterns/pediment-landing.php`: the one `pull-quote {"variant":"testimonial",...}`
  instance (line ~80) is replaced with a `testimonial-grid` containing `testimonial`
  children, so the landing pattern demonstrates the new block.

Migration note: any existing content using `pull-quote` with `variant:"testimonial"`
degrades gracefully — Gutenberg ignores the now-unknown attributes and the block renders
as a plain quote (the quote text survives; avatar/name/role are dropped). Because these
sections are AI-composed and regenerated, the risk is low; recompose any affected page.
The child theme is empty, so nothing relies on the removed `pediment_pull_quote_variants`
filter.

## Out of scope (kept deliberately small)

- **No configurable column count** — fixed 2-up (responsive to 1). YAGNI.
- No changes to `section-head`, band styling, or the `starter-section` containment CSS.

## Verification

- `wp-scripts build` succeeds; both new blocks appear in the build manifest and register;
  `pull-quote` still registers after the variant attributes are removed.
- Render check: a `testimonial-grid` containing 3 `testimonial` children outputs 3
  `.starter-testimonial` cards with the expected classes; an empty-`quote` child renders
  nothing.
- Editor check: inserting `testimonial-grid` seeds the child template; child block is not
  independently insertable.
- Cleanup check: `pull-quote` no longer exposes a `variant`/testimonial UI; a `pull-quote`
  with stale `variant:"testimonial"` attributes renders as a plain quote without error;
  no remaining references to `pediment_pull_quote_variants` or
  `window.pedimentPullQuoteVariants` (grep); `inc/pull-quote-variants.php` deleted and its
  include removed.
- Visual confirm in the child-theme `wp-env` dev env (port 8890): a composed Kundenstimmen
  section matches the approved Option A look at desktop and mobile widths; the landing
  pattern renders its testimonial grid.
```
