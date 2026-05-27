# Mega Menu Sidebar-Form Editor — Design

**Date:** 2026-05-19
**Status:** Approved
**Scope:** Sub-project **A2**. Replace the `starter/mega-menu` editing model
(InnerBlocks trio + canvas "visual approximation", spec
`2026-05-19-mega-menu-editor-layout-design.md`) with a single self-rendered
block edited through a structured form in the block Inspector sidebar.
Supersedes A1's editor approach. Sub-projects **B** (full-Phosphor icon
delivery) and **C** (searchable icon picker) remain out of scope and deferred;
the icon field stays a Phosphor-name text input that C will later upgrade.

## Problem

The nested-block model (`starter/mega-menu` → `starter/mega-column` →
`starter/mega-link`, all dynamic with `render.php`) makes editing fiddly: deep
block selection in a cramped canvas approximation, per-link trips to the
sidebar for URL/icon, no icon preview. The editing experience is poor.

## Decisions (from brainstorming)

| Topic | Decision |
|-------|----------|
| Data model | One `starter/mega-menu` block with a structured `columns` attribute; delete `starter/mega-column` and `starter/mega-link` blocks entirely |
| Form placement | Block Inspector sidebar (`InspectorControls`) — not modal, not inline canvas |
| Canvas | `ServerSideRender` of `render.php`; editor-only CSS reveals the panel on `:hover` of the trigger (collapsed by default); display-only, no Interactivity in editor |
| Icon field | Phosphor-name `TextControl` for now; visual picker deferred to C (depends on B) |
| Column/link limits | None (front-end grid `repeat(auto-fit, minmax(12rem,1fr))` degrades gracefully; YAGNI) |
| Migration | Rewrite our own pattern + `/mega-demo/` fixture + e2e tests only; no block deprecation and no server-side shim; stray old markup becomes an unregistered block (acceptable — controlled dev content only) |

## Architecture & data model

Single block `starter/mega-menu`, `parent: [ "core/navigation" ]`,
`supports: { "html": false, "reusable": false }`. No InnerBlocks.

Attributes:

```json
{
  "label":   { "type": "string", "default": "" },
  "columns": { "type": "array", "default": [] }
}
```

`columns` item shape (enforced in `edit.tsx`/`render.php`, not JSON Schema):

```
{
  "heading": string,
  "links": [
    { "label": string, "url": string, "description": string, "icon": string }
  ]
}
```

Removed: `src/blocks/mega-column/`, `src/blocks/mega-link/`, their
`build/blocks/*` output, and any column/link-specific entries in
`inc/mega-menu.php` (the `block_core_navigation_listable_blocks` filter keeps
only `starter/mega-menu`).

## Sidebar form

`InspectorControls` → `PanelBody` "Mega Menu":

- `TextControl` **Menu label** → `label`.
- Ordered list of **Column** cards (collapsible). Each card:
  - `TextControl` **Heading**.
  - **Remove column**, **Move up**, **Move down**.
  - Ordered list of **Link** rows; each row: `TextControl` **Label**,
    `LinkControl` (or `URLInput`) **URL**, `TextControl` **Description**,
    `TextControl` **Icon (Phosphor name)** (help: "e.g. gear, bank, article"),
    plus **Remove link**, **Move up**, **Move down**.
  - **+ Add link** button.
- **+ Add column** button.

All mutations clone `columns` immutably and call
`setAttributes({ columns: next })`. No `@wordpress/data` block-tree plumbing —
plain attribute state. Reordering is index swap within the cloned array.

## Canvas (display-only)

`edit.tsx`:

```
<div { ...useBlockProps() }>
  <ServerSideRender block="starter/mega-menu" attributes={ attributes } />
</div>
```

ServerSideRender re-fetches on attribute change (its built-in debounce is
sufficient). No inline editing in the canvas; all editing is the sidebar form.

`src/blocks/mega-menu/editor.scss` (editor-only via `editorStyle`):

- Default: panel keeps `render.php`'s `hidden`/absolute positioning.
- `.starter-mega-menu:hover .starter-mega-menu__panel`,
  `.starter-mega-menu__trigger:hover + .starter-mega-menu__panel` → revealed
  (override `display`/`hidden`, position for preview). CSS only — the
  Interactivity runtime does not execute in the editor iframe.
- Accepts the absolute-overlay clipping / hover-flicker caveat (chosen).
- Colours/spacing use `var(--wp--preset--*)` only (palette policy;
  `lint:colors` covers `src/blocks/`).

## render.php

Rewrite to source content from `$attributes`:

- `label` → trigger text/aria exactly as today.
- Loop `$attributes['columns']`; for each non-empty column emit
  `.starter-mega-column` (+ `.starter-mega-column__heading` when set) and loop
  its `links`, emitting the existing `.starter-mega-link` anchor DOM
  (`starter_icon( $icon, 'starter-mega-link__icon' )` reused unchanged).
- `$has_panel` = at least one column with at least one link with a non-empty
  label or url (mirrors the current per-link `'' === $label && '' === $url`
  skip rule). When false, omit the panel and its `aria-controls` exactly as the
  current code path does.
- Interactivity wrapper directives, panel `hidden`/`id`, `view.ts`, and the
  `inc/` navigation binding are **unchanged**. Front-end `style.scss` and all
  markup classes are **unchanged** — only the content source changes.

## Migration & assets

- [patterns/mega-menu-header.php](patterns/mega-menu-header.php): replace the
  nested markup with a single
  `<!-- wp:starter/mega-menu {"label":"Products","columns":[ … ]} /-->`
  reproducing the current demo (Product column: Pricing/Docs, etc.).
- Recreate the `/mega-demo/` fixture page from the new pattern body.
- No deprecation, no `render_block_data` shim. Old nested markup elsewhere
  (none expected) renders as an unregistered block and must be recreated.

## Testing

- `tests/e2e/mega-menu-editor.spec.ts` (reworked): seed a page with the new
  single-block markup; open in the Site Editor via the admin-bar path; assert
  (a) the sidebar form renders label + columns + links, (b) adding a column and
  a link updates the `ServerSideRender` preview, (c) the editor hover-reveal
  CSS exposes the panel on trigger hover. Pinned to the child-theme env
  (`WP_ENV_CWD`) per existing util conventions.
- Front-end `tests/e2e/mega-menu.spec.ts` (4/4) and full PHPUnit must stay
  green — front-end render output and CSS/JS are behaviourally unchanged;
  regression-guarded, not re-implemented.
- Build/lint gates: `npm run build` emits `index.css` for the block;
  `lint:blocks`, `lint:colors`, `lint:js` pass for `src/blocks/mega-menu/`.

## Files touched

- `src/blocks/mega-menu/{block.json,index.tsx,edit.tsx,render.php,editor.scss}`
- Delete: `src/blocks/mega-column/`, `src/blocks/mega-link/` (+ their
  `build/blocks/*`)
- `inc/mega-menu.php` (listable-blocks filter: keep only `starter/mega-menu`)
- `patterns/mega-menu-header.php`
- `tests/e2e/mega-menu-editor.spec.ts`
- `/mega-demo/` fixture content (recreated from the pattern, not a repo file)

## Out of scope (YAGNI)

- Sub-project B: full-Phosphor icon delivery (theme-wide). Separate brainstorm.
- Sub-project C: searchable icon picker. Separate brainstorm; depends on B.
- No change to front-end `style.scss`, `view.ts`, `inc/icons.php`, the sprite,
  the Interactivity behaviour, or dependencies.
- No block deprecation / legacy auto-conversion machinery.

## Known limitations (accepted)

- Canvas preview is display-only; no hover/click/touch/keyboard interactivity
  in the editor (Interactivity runs front-end only). Hover-reveal is CSS-only
  and may clip at canvas edges / flicker.
- The editor icon affordance shows the icon *name*, not the rendered glyph
  (sprite is front-end-only; editor glyphs are sub-project B).
- Old nested-block mega menus do not auto-migrate.
