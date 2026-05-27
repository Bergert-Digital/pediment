# Mega Menu Editor Layout — Design

**Date:** 2026-05-19
**Status:** Approved
**Scope:** Sub-project **A** of three. Fix the broken `starter/mega-menu` editor
layout so the block is editable, presenting a visual approximation of the dropdown.
Sub-projects **B** (full-Phosphor icon-delivery system) and **C** (searchable icon
picker) are explicitly out of scope — separate later brainstorms.

## Context

`starter/mega-menu` → `starter/mega-column` → `starter/mega-link` ship as InnerBlocks
trio. All three `block.json` set BOTH `"style"` and `"editorStyle"` to the same
`file:./style-index.css` (compiled from the front-end `style.scss`). The front-end
rules are written for the *rendered* DOM, e.g. `.starter-mega-link{display:grid;
grid-template-columns:auto 1fr}` (for `<a><svg><span><span>`) and
`.starter-mega-menu__panel{position:absolute;display:grid;…;&[hidden]{display:none}}`.

`edit.tsx` reuses those same class names on a `<div>` wrapping four raw form controls
(icon `TextControl`, url `TextControl`, label `RichText`, desc `RichText`). The
front-end grid crushes those controls into the `auto 1fr` columns → each control
collapses to ~1ch and text wraps one character per line (reported screenshot). The
panel's `position:absolute`/`[hidden]` further deform the editor. Root cause: front-end
render CSS is being reused to lay out the edit form.

## Decisions (from brainstorming)

| Topic | Decision |
|-------|----------|
| Editor UX target | Visual approximation of the dropdown (expanded panel, columns side-by-side, inline-edited) — not pixel-perfect (no hover/Interactivity in editor) |
| Approach | A1 — mirror render DOM in `edit` + dedicated editor stylesheet (`style` vs `editorStyle` split) |
| Sequencing | A now (this spec). B then C are separate later brainstorms. |

## Architecture (Approach A1)

### Stylesheet split (all 3 blocks)

- Add `src/blocks/mega-{menu,link,column}/editor.scss`.
- Import `./editor.scss` in each `index.tsx` (alongside the existing `./style.scss`).
  wp-scripts emits the JS-entry CSS as `index.css`; `style.scss` continues to emit
  `style-index.css`.
- In each `block.json`: `"style": "file:./style-index.css"` (front-end, **unchanged**)
  and `"editorStyle": "file:./index.css"` (editor-only — replaces the shared
  `style-index.css`).
- Result: front-end `style.scss` is never again used to lay out the edit form; the
  coupling that caused the bug is structurally removed.

### `mega-link/edit.tsx`

Mirror `render.php`'s DOM so the shared `.starter-mega-link` grid lays it out
correctly:

- Inline wrapper `<div class="starter-mega-link">` containing, in render order:
  - icon cell: a **sprite-independent** affordance — the icon name as text when set, a dashed placeholder when empty. (The Phosphor sprite is printed only on `wp_body_open`/front-end; making it available in the editor is sub-project B. True rendered icon preview in the editor is deferred to B/C.)
  - `RichText` `__label` (label attribute);
  - `RichText` `__desc` (description attribute).
- Move `url` into a `LinkControl` (or `URLInputButton`) and the icon name into a
  `TextControl`, both inside `InspectorControls` (block sidebar). Neither appears in the
  rendered `<a>`, so relocating them out of the inline flow makes the editor match the
  output and removes the crushed controls.

### `mega-column` — unchanged

`mega-column/edit.tsx` is structurally correct (`RichText` `__heading` + InnerBlocks
`__links`) and its front-end CSS (`.starter-mega-column{display:flex;flex-direction:
column;gap}` + heading) is harmless and *helpful* inside the editor's expanded panel.
Therefore `mega-column` is **not modified at all**: no `edit.tsx` change, no
`editor.scss`, and its `block.json` keeps `editorStyle: file:./style-index.css`. This
avoids emitting an empty `index.css` and removes the "only if needed" ambiguity.

### `mega-menu/edit.tsx`

Keep trigger `RichText` (`__trigger`) + InnerBlocks panel (`__panel`). No markup change;
the panel is shown expanded via `mega-menu/editor.scss`.

## editor.scss contents (editor-only)

- `mega-menu/editor.scss`: `.starter-mega-menu__panel{ position:static;
  box-shadow:none; min-width:0; }` and neutralize hidden in the editor
  (`.starter-mega-menu__panel[hidden]{display:grid}`) so the panel shows expanded with
  its columns grid inline; trigger shown as a styled label.
- `mega-link/editor.scss`: ensure the icon cell sizes correctly; empty-icon placeholder
  is a visible dashed box (`.starter-mega-link__icon:empty`, or a dedicated placeholder
  element). Label/desc inherit the shared grid (now correct).
- `mega-column`: no `editor.scss` (see "mega-column — unchanged").

All editor.scss colors/spacing use `var(--wp--preset--*)` tokens (palette-only policy;
`lint:colors` covers `src/blocks/`).

## Testing

- **Playwright (editor)** `tests/e2e/mega-menu-editor.spec.ts` vs :8890: open the
  fixture (`/mega-demo/` content / *Mega Menu Demo Header* pattern) in the Site Editor;
  assert (a) the panel renders expanded with columns laid out side-by-side, (b) a link's
  label cell `clientWidth` exceeds a sane threshold (regression lock for the ~1ch
  collapse), (c) the icon preview element is present, (d) URL + icon controls appear in
  the inspector. Directly reproduces and locks the reported bug.
- **Regression (must stay green):** front-end `tests/e2e/mega-menu.spec.ts` (4/4) and
  full PHPUnit (109/109) — `render.php`/`style.scss` are unchanged, so this is
  regression-guarded, not re-implemented.
- **Build/lint gates:** `npm run build` emits `index.css` per touched block;
  `lint:blocks`, `lint:colors`, `lint:js` pass for the mega-* files.

## Files touched

- `src/blocks/mega-menu/{index.tsx,edit.tsx,block.json,editor.scss}`
- `src/blocks/mega-link/{index.tsx,edit.tsx,block.json,editor.scss}`
- `src/blocks/mega-column/` — **no changes** (see "mega-column — unchanged")
- `tests/e2e/mega-menu-editor.spec.ts` (new)

## Out of scope (YAGNI)

- Sub-project B: full-Phosphor icon delivery (theme-wide `inc/icons.php`/sprite
  redesign). Separate brainstorm.
- Sub-project C: searchable icon picker over the full catalog. Separate brainstorm;
  depends on B. The icon field stays a relocated `TextControl` for now.
- No change to `render.php`, front-end `style.scss`, `inc/icons.php`, the sprite, or
  dependencies.

## Known limitations (accepted)

- Editor is a faithful *approximation*, not pixel-identical (no hover/Interactivity in
  the editor) — expected for a dropdown/overlay block.
- The editor icon cell shows the icon *name*, not the rendered glyph (sprite is front-end-only; editor sprite availability is deferred sub-project B).
