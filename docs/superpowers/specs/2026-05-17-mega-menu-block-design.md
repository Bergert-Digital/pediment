# Mega Menu Block — Design

**Date:** 2026-05-17
**Status:** Approved
**Scope:** A theme-provided mega-menu, integrated into the core Navigation block as a
nav item, with structured link columns (icon + label + description), hover/focus open on
desktop and an accordion inside the mobile overlay. Approach A (InnerBlocks trio +
Interactivity API).

## Context

The theme already ships custom blocks: `inc/register-blocks.php` auto-registers every
`build/blocks/<name>/` (compiled from `src/blocks/<name>/` by wp-scripts) into the
`starter` block category. There is a strong parent/child InnerBlocks precedent —
`starter/faq` (`useInnerBlocksProps` + `allowedBlocks` + `TEMPLATE`) → `starter/faq-item`
(`"parent"`, `"inserter": false`, attribute-driven, server `render.php`). Icons come from
`inc/icons.php`: `starter_icon( $name )` returns an `aria-hidden` inline `<svg><use
href="#ph-…"></use></svg>` from a Phosphor sprite (string name, no picker UI). The header
nav is the core Navigation block (`overlayMenu:"mobile"`), bound to a seeded
`wp_navigation` entity (see the default-top-navigation spec).

WordPress has no native mega menu; core's Navigation submenu only accepts
navigation-link/submenu children and is single-column. Core's submenu open/close,
keyboard, Escape, and mobile-accordion behavior is implemented with the **Interactivity
API**; this design mirrors that mechanism rather than inventing a parallel one.

## Decisions (from brainstorming)

| Topic | Decision |
|-------|----------|
| Content model | Structured link columns (no freeform blocks, no promo slot) |
| Integration | Inside `core/navigation` as a nav item |
| Desktop trigger | Hover + focus open; Escape / click-outside / blur close |
| Mobile | Accordion: expand columns inline within core's hamburger overlay |
| Link item | Icon (Phosphor name) + label + description |
| Trigger role | Toggle-only `<button>` (no destination link) |
| Approach | A — InnerBlocks trio + Interactivity API |

## Architecture

Three nested blocks under `src/blocks/`, auto-registered by the existing loader, in the
`starter` category:

| Block | `parent` | `inserter` | Attributes | InnerBlocks |
|---|---|---|---|---|
| `starter/mega-menu` | `core/navigation` | yes | `label` (string) | `starter/mega-column` (template 3, soft-cap 4) |
| `starter/mega-column` | `starter/mega-menu` | no | `heading` (string, optional) | `starter/mega-link` |
| `starter/mega-link` | `starter/mega-column` | no | `label`, `url`, `description`, `icon` (all string) | — |

- **Nav allow-list:** new `inc/mega-menu.php` (required from `functions.php` next to
  `inc/nav-active.php` / `inc/nav-seed.php`) adds `starter/mega-menu` via the
  `block_core_navigation_allowed_blocks` filter. `starter/mega-menu` also declares
  `"parent": ["core/navigation"]` so the inserter only offers it inside a nav.
- **Editor:** `mega-menu` and `mega-column` use `useInnerBlocksProps` with
  `allowedBlocks` + a `TEMPLATE` (3 columns × 4 links). `mega-column`/`mega-link` are
  `"inserter": false`, `supports.html:false`. `mega-link` edits `label` and
  `description` via RichText, `url` via the standard link control, `icon` via a plain
  text field (Phosphor name) — consistent with `starter_icon()`, no custom picker
  (YAGNI). `mega-menu` exposes `label` (trigger text) via a RichText/plain field.
- **Render:** server `render.php` per block.
  - `mega-menu`: a `<button>` (trigger, `label`) + a panel container with a stable
    generated `id`; `aria-expanded`, `aria-controls`, and Interactivity directives.
    Guards: if no columns, the panel container is omitted.
  - `mega-column`: column wrapper + optional `<… >` heading (omitted when empty).
  - `mega-link`: `<a href>` containing `starter_icon( $icon )` (omitted when `icon`
    empty), the label, and the description (omitted when empty), all inside the `<a>`.

## Front-end interaction (Interactivity API)

`mega-menu/block.json` declares `viewScriptModule`; the store mirrors core Navigation
submenu directives so markup/behavior conventions match core.

- Trigger `<button>`: `aria-expanded` (false/true), `aria-controls` → panel id; panel id
  stable per instance. Disclosure pattern (button + controlled region), **not** a menu
  role — correct for a panel of links.
- **Pointer, desktop:** pointer-enter on the item opens; pointer-leave closes after a
  ~150 ms intent delay (diagonal travel into panel). Keyboard `focus` into trigger/panel
  opens; `focusout` of the whole item closes.
- **Escape:** closes, returns focus to the trigger. **Click-outside:** pointer-down
  outside the item closes.
- **No-hover / touch** (`(hover: none)` or coarse pointer): hover path suppressed; first
  tap on the trigger toggles — touch is unambiguous.
- One panel open at a time; opening a mega item closes sibling mega items.
- `prefers-reduced-motion`: no slide/transition.

## Accessibility

- Disclosure (button + `aria-expanded` + `aria-controls`); links are real `<a>`s in DOM
  order. Tab through links, Shift+Tab back to trigger; Escape anywhere in the panel
  closes and restores focus to the trigger.
- Icons decorative (`aria-hidden`, as `starter_icon()` already renders); the link's
  accessible name is its label text; description is plain text inside the `<a>` for
  context.
- Inherits the existing `.wp-block-navigation a:focus-visible` focus-ring token from
  `theme.json`.
- Known limitation: JS-dependent (panel needs the Interactivity API to open) — content
  is in the DOM regardless; matches core submenu's JS dependence. Documented, accepted.

## Responsive / mobile

- Inside core's overlay (`.wp-block-navigation__responsive-container.is-menu-open`), the
  mega item is an **accordion**: trigger toggles a vertically-stacked single-column
  inline expansion (matching core submenu mobile UX). No hover, no positioned panel —
  same `aria-expanded` state drives it.
- Breakpoint = core's `overlayMenu:"mobile"`; no competing breakpoint introduced.
- Desktop panel: wide dropdown anchored to the header, aligned to the theme's `1200px`
  wide layout, `surface` bg, `border` + `lifted` shadow.

## Styling

- Block-scoped SCSS (`src/blocks/*/style.scss` → `style-index.css` via `block.json`
  `style`). Colors/spacing/shadow only via `var(--wp--preset--*)` (palette-only policy;
  `npm run lint:colors` covers `src/blocks/`).
- Desktop: CSS grid columns (auto-fit, 1–4), heading `text-muted`, link hover →
  `accent`, icon sized to link line-height.
- Mobile: columns collapse to one, indented, no shadow/positioning.
- No new global CSS in `theme.json`; styling stays with the blocks (self-contained,
  theme-switch-isolated).

## Testing

- **PHPUnit** (`tests/phpunit/MegaMenu/`): the allowed-blocks filter adds
  `starter/mega-menu`; each `render.php` — trigger `<button>` with
  `aria-expanded="false"` + `aria-controls`, panel id linkage, column heading, link with
  `starter_icon()` + label + description + href; safe degradation (no icon → no `<svg>`,
  no description → omitted, no columns → no panel container).
- **Playwright** (`tests/e2e/mega-menu.spec.ts`, vs :8890): hover opens / leave closes;
  focus opens, Escape closes + focus returns to trigger, `aria-expanded` toggles;
  click-outside closes; mobile viewport → inside hamburger overlay the trigger expands
  the accordion inline; reduced-motion disables transition.
- A deterministic fixture: a header pattern (via `inc/patterns.php`) wiring a
  `core/navigation` containing one `starter/mega-menu` with sample columns/links.

## Files touched

- `src/blocks/mega-menu/` — block.json (incl. `viewScriptModule`), edit.tsx, index.tsx,
  render.php, style.scss, view (interactivity) module
- `src/blocks/mega-column/` — block.json, edit.tsx, index.tsx, render.php, style.scss
- `src/blocks/mega-link/` — block.json, edit.tsx, index.tsx, render.php, style.scss
- `inc/mega-menu.php` — `block_core_navigation_allowed_blocks` filter (new)
- `functions.php` — `require_once __DIR__ . '/inc/mega-menu.php';`
- `inc/patterns.php` — sample mega-menu header pattern (test/demo fixture)
- `tests/phpunit/MegaMenu/` — render + filter tests
- `tests/e2e/mega-menu.spec.ts` — interaction/a11y/responsive tests

## Out of scope (YAGNI)

- Promo/featured slot, images, arbitrary blocks in the panel
- Per-link landing-page trigger (toggle-only button chosen)
- Classic-menu / non-FSE support
- Multi-level nesting (columns → links only)
- Custom icon-picker UI (plain Phosphor-name field)
- New global theme.json CSS for the mega menu

## Known limitations (accepted)

- JS-dependent open (Interactivity API), like core submenu.
- Theme-registered blocks orphan their content on theme switch — consistent with the
  theme's other custom blocks; inherent to a theme-shipped block.
- Subdirectory installs unaffected (no URL/path logic here).
