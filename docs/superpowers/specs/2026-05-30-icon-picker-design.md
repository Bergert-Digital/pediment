# Icon Picker — Design

**Date:** 2026-05-30
**Status:** Approved (design)
**Author:** Jonas Bergert

## Problem

Editors set block icons by typing a Phosphor slug into a plain `TextControl`
(in the `pediment/feature` block and in `pediment/mega-menu` columns). There is
no validation, no preview, and the available slugs are duplicated across four
places that drift apart:

- the `enum` in `src/blocks/feature/block.json`
- that block's `description` text
- the `mega-menu` help text
- the committed sprite `assets/icons/phosphor-sprite.svg`

The catalog is also tiny: exactly **11** hand-picked icons, materialised by
`tools/build-phosphor-sprite.sh` (which `curl`s regular-weight SVGs from
`@phosphor-icons/core@2.1.1` on unpkg). The full ~1,500-icon Phosphor library is
**not** in the repo.

## Goal

Replace the text inputs with a **searchable grid icon picker**, backed by the
**full regular-weight Phosphor catalog (~1,500 icons)**, implemented as a
**shared, reusable editor component**. Any chosen icon must render on the
frontend with no per-edit build/sync step and no runtime CDN dependency.

## Decisions (locked)

| Decision | Choice |
| --- | --- |
| Icon vocabulary | Phosphor slugs (existing), regular weight only |
| Catalog size | Full regular set (~1,500 icons) |
| Picker UX | Button + popover with search box + scrollable grid |
| Scope | Shared component reused by `feature` and `mega-menu` |
| Sync strategy | Render-time lookup from a committed data file (no sync, drift impossible) |
| Sprite machinery | **Removed** (replaced by inline rendering) |
| Repo size cost | ~1.8 MB of committed icon data — **accepted** |

## Architecture

### 1. Data pipeline (build-once, committed)

New script **`tools/build-phosphor-data.sh`** — mirrors the style of the existing
`build-phosphor-sprite.sh` (curl from unpkg `@phosphor-icons/core@2.1.1`, no new
runtime/npm dependencies). It enumerates the regular-weight icon set and emits
two committed artifacts from the one source:

- **`assets/icons/phosphor-icons.php`** — returns a PHP array
  `['trend-up' => '<path .../>', …]` mapping slug → inner SVG markup.
  Opcache-compiled, so per-request lookup is near-zero cost. Read by PHP at
  render time.
- **`assets/icons/phosphor-icons.json`** — the same slug → markup map. Fetched
  lazily by the block editor for the picker grid and previews.

How the script discovers the full slug list: it queries unpkg's directory-meta
endpoint
`https://unpkg.com/@phosphor-icons/core@2.1.1/assets/regular/?meta` (returns a
JSON file listing), parses out every `*.svg` filename to get the slug set, then
fetches each icon and extracts its inner markup with the same `sed` transform
already used in `build-phosphor-sprite.sh`. (Exact endpoint/parse to be confirmed
during implementation; fallback is downloading the package tarball and reading
`assets/regular/`.)

**Size:** ~0.9 MB per file, ~1.8 MB total committed. Neither file is ever
downloaded wholesale by site visitors:

- PHP inlines only the icons a page actually uses.
- The JSON is fetched only inside the block editor (admin), only when a picker
  popover is first opened, and is cached thereafter.

Rendered pages therefore stay lean, consistent with the "only ship what's used"
philosophy.

### 2. Render-time lookup (replaces the sprite)

`pediment_icon( $name, $extra_class = '' )` in `inc/icons.php` changes from
emitting `<svg class="i"><use href="#ph-slug"></use></svg>` to **inlining** the
markup from the PHP map:

```php
function pediment_icon( $name, $extra_class = '' ) {
    $slug = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) );
    $map  = pediment_icon_map(); // cached require of phosphor-icons.php
    if ( '' === $slug || ! isset( $map[ $slug ] ) ) {
        return ''; // graceful: unknown slug renders nothing
    }
    $class = 'i' . ( '' !== $extra_class ? ' ' . sanitize_html_class( $extra_class ) : '' );
    return sprintf(
        '<svg class="%s" viewBox="0 0 256 256" aria-hidden="true" focusable="false">%s</svg>',
        esc_attr( $class ),
        $map[ $slug ] // theme-controlled trusted markup, same trust model as the old sprite
    );
}
```

`pediment_icon_map()` is a small helper that `require`s
`assets/icons/phosphor-icons.php` once and memoises it in a static variable.

**Removed as now-dead code:**

- `tools/build-phosphor-sprite.sh`
- `assets/icons/phosphor-sprite.svg`
- `pediment_icon_sprite_contents()`
- `pediment_print_icon_sprite()` and its `wp_body_open` hook
- `pediment_enqueue_editor_icon_sprite()` and its editor-iframe injection
  (the MutationObserver / iframe-injection hack goes away entirely)

The one raw `<use href="#ph-check-circle">` in `src/blocks/hero/render.php` is
switched to `pediment_icon( 'check-circle', … )`. After this change, nothing in
the codebase references `#ph-*` symbols.

### 3. Shared component — `src/components/icon-picker/`

```
[ ⬆ trend-up ▾ ]   ← Button: live preview + current slug
      │ click
      ▼
┌─────────────────────────────┐
│ [🔍 search…              ]  │
│ ┌──┬──┬──┬──┬──┬──┬──┬──┐   │  scrollable grid,
│ │▦ │▦ │▦ │▦ │▦ │▦ │▦ │▦ │   │  filtered by search,
│ ├──┼──┼──┼──┼──┼──┼──┼──┤   │  only the filtered
│ │▦ │▦ │▦ │▦ │▦ │▦ │▦ │▦ │   │  subset is rendered
│ └──┴──┴──┴──┴──┴──┴──┴──┘   │
└─────────────────────────────┘
```

Public interface:

```tsx
<IconPicker value={ string } onChange={ ( slug: string ) => void } label?={ string } />
```

- Built from `@wordpress/components` (`Button`, `Popover`/`Dropdown`,
  `SearchControl`) and `@wordpress/element`.
- **Catalog loading:** the `phosphor-icons.json` URL is provided to the editor
  via `wp_add_inline_script` on the block editor script handle (a small
  `window.pediment = { iconsUrl: '…' }` global). The component `fetch`es the
  JSON on first popover open, caches the parsed map in a module-level variable
  shared across all picker instances, and shows a loading state until ready.
- **Preview rendering:** each grid cell and the trigger button render the inner
  SVG markup via `dangerouslySetInnerHTML` into an `<svg viewBox="0 0 256 256">`
  wrapper. The markup is trusted theme data (same source as the PHP map).
- **Performance:** only the search-filtered subset is rendered to the DOM. Empty
  search shows the full list; if that proves sluggish in practice, simple
  windowing is a follow-up enhancement (out of scope now).
- **Accessibility:** grid cells are real `<button>`s with `aria-label` = slug;
  the popover traps focus per `@wordpress/components` defaults.

### 4. Consumers and attribute changes

- **`src/blocks/feature/edit.tsx`** — replace the `TextControl` with
  `<IconPicker>`; the on-canvas preview switches from `<use>` to inline markup
  drawn from the fetched catalog (reuse the shared cache).
- **`src/blocks/mega-menu/edit.tsx`** — replace the per-column `TextControl`
  with `<IconPicker>`; delete the stale "Available: …" help text.
- **`src/blocks/feature/block.json`** — remove the 11-value `enum` from the
  `icon` attribute (it would reject any other slug) and remove the icon list
  from the `description`. `icon` stays `type: string`, `default: "trend-up"`.

## Data flow

1. **Build (once / when bumping Phosphor):** run `tools/build-phosphor-data.sh`
   → writes `phosphor-icons.php` + `phosphor-icons.json`, both committed.
2. **Editing:** editor loads block → user opens IconPicker → JSON fetched once,
   cached → user searches + clicks → `onChange` writes the slug to the block
   attribute.
3. **Rendering:** `render.php` calls `pediment_icon( $slug )` → looks up the slug
   in the memoised PHP map → inlines the SVG. Unknown slug → empty string.

## Error handling

- Unknown / empty slug at render time → `pediment_icon` returns `''` (no broken
  markup).
- JSON fetch failure in the editor → picker shows an error state and keeps the
  current value editable as a fallback text field.
- `phosphor-icons.php` missing (e.g. data script never run) → `pediment_icon_map`
  returns `[]` and all icons render empty; the build step is documented as a
  prerequisite.

## Testing

- **PHP:** unit-test `pediment_icon()` for a known slug (returns inline `<svg>`
  containing the mapped markup), an unknown slug (returns `''`), and slug
  sanitisation (`Trend_Up!` → `trend-up`). Confirm no `#ph-` / `<use>` strings
  remain in rendered output across blocks.
- **JS:** test `IconPicker` renders the trigger with the current value, opens the
  popover, filters the grid by search text, and fires `onChange` with the
  clicked slug. Mock the catalog fetch.
- **Manual (in canonical wp-env at port 8890):** add a Feature block, pick a
  non-original icon (e.g. `rocket`), confirm it renders on the frontend; repeat
  for a mega-menu column.

## Out of scope (YAGNI)

- Non-regular icon weights (bold, duotone, etc.).
- Color / size controls inside the picker.
- Virtualised grid (revisit only if the filtered-render approach lags).
- Migrating `social-links`, which uses a separate inline-SVG system.

## Affected files

| File | Change |
| --- | --- |
| `tools/build-phosphor-data.sh` | **new** — generates the two data files |
| `assets/icons/phosphor-icons.php` | **new (generated)** — slug → markup map (PHP) |
| `assets/icons/phosphor-icons.json` | **new (generated)** — slug → markup map (JSON) |
| `src/components/icon-picker/` | **new** — shared `<IconPicker>` component |
| `inc/icons.php` | rewrite `pediment_icon`; add `pediment_icon_map`; remove sprite print/enqueue; enqueue JSON URL inline script |
| `src/blocks/feature/edit.tsx` | use `<IconPicker>`; inline canvas preview |
| `src/blocks/feature/block.json` | drop `enum`; clean `description` |
| `src/blocks/mega-menu/edit.tsx` | use `<IconPicker>`; drop stale help text |
| `src/blocks/hero/render.php` | `<use #ph-check-circle>` → `pediment_icon('check-circle')` |
| `tools/build-phosphor-sprite.sh` | **delete** |
| `assets/icons/phosphor-sprite.svg` | **delete** |
