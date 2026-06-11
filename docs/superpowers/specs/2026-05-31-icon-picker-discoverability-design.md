# Icon Picker — Discoverability & Swappable Sets — Design

**Date:** 2026-05-31
**Status:** Approved (design)
**Author:** Jonas Bergert
**Builds on:** [2026-05-30-icon-picker-design.md](2026-05-30-icon-picker-design.md)

## Problem

The shipped IconPicker can search the full ~1,500-icon Phosphor catalog, but the
**no-search browse view is capped at the first 150 icons** (`NO_QUERY_LIMIT` in
`src/components/icon-picker/index.tsx`). If an editor doesn't know an icon's
slug, they can only eyeball the first 150 alphabetically — the other ~1,360 are
effectively undiscoverable by browsing.

Two further gaps:

- **Search is slug-only.** Searching "delete" does not surface `trash`; "launch"
  does not surface `rocket`. Phosphor ships tag metadata that would fix this, but
  our build does not capture it.
- **No way to browse by theme.** There is no category grouping or filter.

Separately, the current implementation **hardcodes Phosphor specifics** in theme
code, making it medium-hard to swap the icon set:

- `viewBox="0 0 256 256"` is hardcoded in `pediment_icon()` (Phosphor's
  coordinate system; most other sets use `0 0 24 24`).
- The committed data files are named `phosphor-icons.{php,json}`.
- The renderer assumes **filled-path** icons colored via `fill: currentColor`.
  Stroke-based sets (Lucide, Tabler, Heroicons-outline) carry presentation
  attributes (`fill="none" stroke="currentColor" stroke-width="2" …`) on the
  `<svg>` element itself; our pipeline strips the outer `<svg>` and re-wraps with
  its own, so a stroke set would render as solid black blobs. This breakage is
  invisible until someone tries to swap.

## Goal

1. **Browse everything** — remove the 150 ceiling; every icon reachable by
   scrolling.
2. **Search finds the right icon** — match against slug **and** Phosphor tags.
3. **Browse by category** — filter by Phosphor's 18 categories.
4. **Make the icon set swappable** — isolate *all* set-specific knowledge into
   generated data so theme code (PHP render + React picker) has zero icon-set
   assumptions. Swapping becomes "write one builder that emits files matching a
   documented contract."

## Decisions (locked)

| Decision | Choice |
| --- | --- |
| Browse UX | Flat grid, progressive render on scroll (IntersectionObserver) |
| Search scope | Slug + tags (case-insensitive substring) |
| Category UX | `SelectControl` dropdown ("All categories" + 18 categories) |
| Metadata source | `@phosphor-icons/core` `dist/index.mjs` `icons` array |
| Metadata file | New committed `icon-meta.json` (editor-only) |
| Set-render config | New committed `icon-set.json` manifest (viewBox + svgAttrs) |
| File naming | **Generic** (`icon-markup.*`, `icon-meta.json`, `icon-set.json`) — no "phosphor" in theme-consumed names |
| Builder | `tools/build-phosphor-data.sh` = reference implementation of a documented file contract |
| Out of scope | Runtime multi-set switcher; per-icon `<svg>` storage; config UI; multiple weights |

## The data contract (the swappability interface)

The committed data files **are** the interface between "an icon set" and the
theme. Any builder that emits these files in these shapes works with no theme
code changes. The Phosphor builder is the reference implementation.

| File | Shape | Consumed by | Required? |
| --- | --- | --- | --- |
| `assets/icons/icon-markup.php` | `return array( 'slug' => '<inner svg markup>', … );` | PHP render (`pediment_icon`) | **Yes** |
| `assets/icons/icon-markup.json` | `{ "slug": "<inner svg markup>", … }` | Editor picker (grid/preview) | **Yes** |
| `assets/icons/icon-meta.json` | `{ "slug": { "c": ["category", …], "t": ["tag", …] }, … }` | Editor picker (search + category) | **No** — picker degrades to slug-only search, no category filter |
| `assets/icons/icon-set.json` | `{ "name", "version", "viewBox", "svgAttrs": {…}, "license" }` | PHP render + editor preview | **Yes** |

Rules:

- `icon-meta.json` keys MUST be a subset of `icon-markup.*` keys (no meta for a
  slug we can't render). The builder enforces this via set intersection.
- `inner svg markup` is everything between the outer `<svg …>` and `</svg>` —
  the same extraction already used today.
- `svgAttrs` carries every presentation attribute that must live on the wrapper
  `<svg>` for the set to render correctly. Phosphor: `{ "fill": "currentColor" }`.
  Lucide: `{ "fill": "none", "stroke": "currentColor", "stroke-width": "2",
  "stroke-linecap": "round", "stroke-linejoin": "round" }`.

## Architecture

### 1. Data pipeline — `tools/build-phosphor-data.sh`

Extends the existing script (which already `npm pack`s `@phosphor-icons/core`
and extracts inner markup from `assets/regular/*.svg`):

- Rename outputs to `icon-markup.php` / `icon-markup.json`.
- After unpacking, a small inline `node` step `import()`s
  `package/dist/index.mjs` (exports the `icons` array, each entry has
  `name`, `categories: string[]`, `tags: string[]`) and writes
  `icon-meta.json`. The `"*new*"` marker tag is stripped; only slugs present in
  the regular SVG set are emitted (intersection, so meta never drifts from
  markup).
- Write `icon-set.json`:
  `{ "name": "phosphor", "version": "2.1.1", "viewBox": "0 0 256 256",
  "svgAttrs": { "fill": "currentColor" }, "license": "MIT" }`.

`node` is available because the script already runs `npm pack`. No new runtime
or npm dependency. Estimated `icon-meta.json` size ~150–250 KB; **editor-only**,
fetched lazily in the admin, never shipped to site visitors.

### 2. PHP render — `inc/icons.php` (set-agnostic)

- `pediment_icon_map()` reads `assets/icons/icon-markup.php` (renamed path).
- New `pediment_icon_set()`: memoised read of `icon-set.json` returning
  `[ 'viewBox' => …, 'svgAttrs' => [...] ]`, with safe fallbacks
  (`viewBox` → `'0 0 256 256'`, `svgAttrs` → `[ 'fill' => 'currentColor' ]`) if
  the manifest is missing.
- `pediment_icon()` builds the wrapper `<svg>` from the manifest: `viewBox` from
  `icon-set.json`, plus each `svgAttrs` key/value (escaped). No hardcoded `256`,
  no hardcoded fill anywhere in code. Existing `class="i"`, `data-icon`,
  `aria-hidden`, `focusable` behavior preserved; `$extra_class` still appended.
- The inline editor script exposes URLs for **both** the markup JSON and the
  meta JSON and the set manifest via the existing `window.pedimentIcons` global:
  `{ markupUrl, metaUrl, setUrl }` (renamed from `catalogUrl`).

### 3. Catalog loading — `src/components/icon-picker/catalog.ts`

- Reads the three URLs from `window.pedimentIcons`.
- Fetches markup JSON + meta JSON + set manifest; caches all three module-level,
  shared across picker instances (unchanged pattern).
- Returns `{ markup: Record<slug,string>, meta: Record<slug,{c,t}> | null,
  set: { viewBox, svgAttrs } }`. `meta` is `null` if its fetch fails or the URL
  is absent — the picker then runs slug-only with no category control.
- Markup fetch failure remains a hard error (existing behavior).

### 4. Filtering — `src/components/icon-picker/filter.ts`

Becomes the single source of truth for "which slugs show", pure and
React-free:

```ts
filterIcons(slugs, query, category, meta): string[]
```

- **Category first:** `''`/`all` → all slugs; else keep slugs where
  `meta?.[slug]?.c` includes `category`.
- **Query then:** case-insensitive substring against the slug **and** every tag
  in `meta?.[slug]?.t`.
- Returns matches in original (alphabetical) order. Blank query + `all` → full
  set. Missing meta → category filter inactive, search falls back to slug-only.

### 5. Picker UI — `src/components/icon-picker/index.tsx`

Inside the popover, above the grid:

- `SelectControl` — "All categories" plus the 18 category options (label-cased,
  e.g. "Maps & travel"). Only rendered when `meta` is present.
- Existing `SearchControl` below it.

Grid rendering:

- Replace the hard `NO_QUERY_LIMIT` cap with **progressive rendering**: keep a
  `visibleCount` state (initial chunk ~120). An `IntersectionObserver` watches a
  sentinel `<div>` at the bottom of the scroll area; when it enters view,
  `visibleCount` grows by another chunk. Changing query or category resets
  `visibleCount` to the initial chunk.
- Render `matches.slice(0, visibleCount)`.
- Replace the "Showing first 150…" hint with a count (e.g. "342 icons"); the
  sentinel only renders while `visibleCount < matches.length`.
- `IconGlyph` builds its preview `<svg>` from the manifest's `viewBox` +
  `svgAttrs` (so stroke sets preview correctly), not a hardcoded `0 0 256 256`.

### 6. Consumers

No change. `src/blocks/feature/edit.tsx` and `src/blocks/mega-menu/edit.tsx`
already use `<IconPicker value onChange label? />`; the public component API is
unchanged.

## Data flow

1. **Build (once / when bumping or swapping the set):** run the builder →
   writes `icon-markup.{php,json}`, `icon-meta.json`, `icon-set.json`, all
   committed.
2. **Editing:** editor loads block → opens IconPicker → fetches markup + meta +
   set once, cached → user picks a category and/or types → `filterIcons`
   narrows → scroll reveals more → click fires `onChange(slug)`.
3. **Rendering:** `render.php` calls `pediment_icon($slug)` → looks up markup in
   the memoised PHP map → wraps with viewBox/attrs from the manifest → inlines
   the SVG. Unknown slug → `''`.

## Error handling

- Unknown/empty slug at render → `pediment_icon` returns `''` (unchanged).
- `icon-markup.php` missing → `pediment_icon_map()` returns `[]`, icons render
  empty (unchanged); build step documented as prerequisite.
- `icon-set.json` missing → `pediment_icon_set()` returns Phosphor-shaped
  defaults so existing content still renders.
- `icon-meta.json` fetch fails in editor → picker drops the category control and
  searches slug-only; current value stays editable.
- Markup JSON fetch fails in editor → existing error Notice + text fallback.

## Testing

- **`filter.ts` unit tests** (extend `filter.test.ts`): category narrowing;
  tag-match search surfaces a slug that doesn't contain the term; combined
  category + query; blank query + `all` returns full set; `meta === null`
  falls back to slug-only with no category filtering.
- **`index.tsx`**: category select narrows the grid; progressive sentinel
  reveals more rows when the (mocked) observer fires; search-by-tag surfaces an
  icon whose slug lacks the term; category control absent when meta is null.
- **PHP** (`pediment_icon`): wrapper viewBox/attrs come from a stubbed manifest
  (assert a non-Phosphor `viewBox` + stroke attrs are emitted); known slug
  inlines markup; unknown slug → `''`; slug sanitisation.
- **Build script smoke:** `icon-meta.json` keys ⊆ `icon-markup.json` keys;
  `icon-set.json` parses and has `viewBox` + `svgAttrs`.
- **Manual (canonical wp-env, port 8890):** open the picker, filter by a
  category, search by a tag (e.g. "delete" → trash family), scroll past 150,
  pick a deep icon, confirm it renders on the frontend.

## Out of scope (YAGNI)

- Runtime/admin multi-set switcher — one committed set at a time; swapping is a
  build-time developer action.
- Storing full per-icon `<svg>` — set-level `svgAttrs` covers presentation;
  per-icon overrides are unnecessary for the supported sets.
- Virtualised grid (revisit only if progressive-chunk rendering lags).
- Non-regular weights (bold/duotone/etc.), color/size controls in the picker.
- Migrating `social-links` (separate inline-SVG system).

## Affected files

| File | Change |
| --- | --- |
| `tools/build-phosphor-data.sh` | emit `icon-meta.json` + `icon-set.json`; rename markup outputs to `icon-markup.*` |
| `assets/icons/phosphor-icons.php` → `icon-markup.php` | **rename (regenerated)** |
| `assets/icons/phosphor-icons.json` → `icon-markup.json` | **rename (regenerated)** |
| `assets/icons/icon-meta.json` | **new (generated)** — slug → { categories, tags } |
| `assets/icons/icon-set.json` | **new (generated)** — render manifest |
| `inc/icons.php` | renamed paths; add `pediment_icon_set()`; build wrapper from manifest; expose `markupUrl`/`metaUrl`/`setUrl` |
| `src/components/icon-picker/catalog.ts` | fetch + cache markup/meta/set; return combined catalog; null-meta tolerance |
| `src/components/icon-picker/filter.ts` | add `category` + `meta`; tag-aware search |
| `src/components/icon-picker/index.tsx` | category `SelectControl`; progressive IntersectionObserver render; manifest-driven preview |
| `src/components/icon-picker/filter.test.ts` | extend for category + tag + null-meta cases |
| `docs/superpowers/specs/2026-05-30-icon-picker-design.md` | (reference only — superseded render details noted here) |
