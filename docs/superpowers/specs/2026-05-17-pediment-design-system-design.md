# Pediment Design System — Theme Redesign

**Date:** 2026-05-17
**Status:** Approved design, pending implementation plan
**Repo:** `wp-starter-theme` (parent block theme)
**Reference mockup:** [`docs/design/pediment-mockup.html`](../../design/pediment-mockup.html) — the locked, pixel-level source of truth (iterated as `theme-v1`→`theme-v12` in the brainstorm companion). Demo brand "Pediment", a fictional strategy consultancy.

## Background

The starter theme ships 12 `starter/*` blocks with a cool slate/indigo
`theme.json` and ad-hoc per-section spacing. The goal was higher design
quality **and** broader block coverage, modeled on the Kadence marketing-site
aesthetic but rebuilt as a forkable, rebrandable token set. We co-designed a
full representative homepage in HTML/CSS until it was approved, then locked it.
This spec captures that design so it can be ported into the block theme; the
HTML mockup is the authority wherever this prose is ambiguous.

## Goal

Port the locked mockup into the parent theme as: (1) a new `theme.json` token
system, (2) restyled existing blocks, (3) a small number of new section blocks,
(4) a Phosphor icon-sprite mechanism, and (5) a reduced-motion-safe entry-
animation layer. Every value below is single-sourced so a fork rebrands by
editing tokens only.

## Design tokens (→ `theme.json` + base styles)

| Token | Value | theme.json home |
|---|---|---|
| Canvas / surface | `#FFFFFF` / `#F5F8FC` | color.palette `surface`, `surface-elevated` |
| Ink (text + dark sections) | `#0B1B33` (navy `#0A1B33`) | `text`, `primary` |
| Muted text | `#5C6B82` | `text-muted` |
| Accent | `#0E7490` (Deep Cyan) | `accent` |
| Accent deep (hover/active) | `#155E75` | `accent-hover` |
| Accent tint (chip/icon bg) | `#E1F1F6` | custom |
| Border / hairline | `#E4EAF2` (strong `#CDD9EC`) | `border`, `border-strong` |
| Font (body + heading) | `Plus Jakarta Sans` 400–800, system fallback | typography.fontFamilies |
| Type scale | display `clamp(2.6,5vw,4.5rem)/1.05/800`; h2 `clamp(1.9,3.2vw,2.8rem)/800`; lead `1.15rem` muted | fontSizes (fluid) |
| Vertical rhythm | `--section: clamp(88px,9vw,132px)`; `--band: clamp(76px,7.5vw,108px)`; `--head-gap: clamp(40px,5vw,60px)` | spacing presets |
| Radii | pill `999px`; lg `20px`; md `14px`; panel `28px` | custom |
| Shadows | `0 24px 50px -28px rgba(11,27,51,.30)`; sm `0 8px 24px -16px rgba(11,27,51,.25)` | custom |
| Buttons | pill; primary = accent→accent-deep on hover; ghost = white + border→accent; light = white on navy; nav variant = compact (`11px 20px/.9rem`) | block styles |
| Eyebrow | `kicker` (uppercase, .14em, accent) and `chip` (tinted pill + dot) | utility classes |

**Critical correctness note (carry into the port):** the layout-width wrapper
must own *horizontal* gutter only (`padding-inline`), never the `padding`
shorthand — a class wrapper using `padding:0 x` defeats section vertical rhythm
by specificity. In `theme.json`/block CSS, keep content-width constraints and
section block-spacing on separate properties.

## Section inventory → block mapping

| # | Mockup section | Maps to | Action |
|---|---|---|---|
| 1 | Sticky nav + logo (bank icon badge) | `parts/header.html` | Restyle; add icon logo |
| 2 | Hero: text + photo card w/ frosted stat overlay | `starter/hero` | Extend: image + glass-stats variant |
| 3 | Logo cloud ("Trusted by") | **new** `starter/logo-cloud` | New block (or pattern) |
| 4 | Services: icon + title + text + link cards | **new** `starter/feature-grid` | New block (Kadence Info-Box equiv.) |
| 5 | "How we work": numbered steps + media | **new** `starter/steps` | New block (numbered list + media split) |
| 6 | Stats band (navy, 4 figures) | `starter/stat` | Restyle + arrange in a navy band |
| 7 | Testimonial (quote + avatar + role) | `starter/pull-quote` | Extend to a testimonial variant (avatar/role) |
| 8 | FAQ: intro + accordion | `starter/faq` + `starter/faq-item` | Restyle to match; closed by default |
| 9 | CTA (navy rounded panel) | `starter/cta` | Restyle navy-panel variant |
| 10 | Insights: pill filter + media cards | `starter/blog-index` | Extend: category filter + card style + type badges |
| 11 | Footer (4-col + bottom bar) | `parts/footer.html` | Restyle; icon logo |

Sections are full-bleed bands. Keep the existing flat-block-stream + section
model: implement bands as `core/group` with named block-style variations
(`is-style-band-surface`, `is-style-band-navy`) carrying the shared
`--section` rhythm, rather than introducing a generic container block.
New blocks: **logo-cloud, feature-grid, steps** (3). Extended blocks: hero,
stat, pull-quote, cta, faq/faq-item, blog-index.

## Phosphor icon strategy

- Adopt **Phosphor** (regular weight), MIT, shipped as a single inline SVG
  sprite of `<symbol id="ph-*" viewBox="0 0 256 256">` with `fill:currentColor`.
- Sprite inlined once near `wp_footer` (or registered and output via a small
  `inc/` helper); blocks reference `<svg class="i"><use href="#ph-…">`.
- No icon font, no network/runtime dependency. Size/color inherit via a `.i`
  class + `currentColor`, so accent retokenizing cascades automatically.
- Icons used in the locked mockup: `bank` (logo), `trend-up`, `gear`, `stack`,
  `check-circle`, `caret-down`, `arrow-right`, `article`, `monitor-play`,
  `microphone`. The port ships at least these; the sprite is extensible.

## Entry animations

- Subtle fade-up (`opacity 0→1`, `translateY(22px)→0`), `.7s`
  `cubic-bezier(.16,1,.3,1)`, light per-group stagger (≤6 steps, 85ms).
- IntersectionObserver, reveal-once (unobserve after), header excluded.
- No-FOUC: hidden state gated behind an `.anim` class set before first paint.
- **`prefers-reduced-motion: reduce` fully disables** (static render).
- Ships as one small self-contained theme script + CSS (candidate for
  `@wordpress/interactivity` per-block later; not required for parity).

## Out of scope

- WP.org submission, hosted update server, child-theme changes.
- Functional blog filtering/data (Insights filter is presentational at parity;
  real query wiring is a follow-up).
- New color/style *variations* beyond the single locked Deep Cyan default
  (multi-variation theming is a future, separately-specced effort).

## Resolved decisions

- Reference: Kadence aesthetic, rebuilt as forkable tokens (not a clone).
- Topic/brand for demo content: "Pediment" consultancy, blue→**Deep Cyan**.
- Icons: **Phosphor** (over Lucide/Heroicons/Tabler), inline sprite.
- Sections as styled `core/group` bands, not a new container block.
- 3 genuinely new blocks; the rest are restyles/extensions of existing blocks.
