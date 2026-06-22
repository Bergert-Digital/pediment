# Slider Editor Redesign — Sidebar-Managed Slides — Design

**Date:** 2026-06-19
**Status:** Approved (pending spec review)
**Supersedes the editor/authoring model of:** `2026-06-19-image-content-slider-design.md`

## Goal

Replace the slider's InnerBlocks authoring model with a **sidebar-managed data
model** (like `pediment/mega-menu`). In the editor, slides are added/edited/reordered
entirely in the Inspector sidebar, and the canvas renders the **finished slider**
(rounded card, full-bleed image, colored panel) for the active slide — clickable
arrows/dots switch the previewed slide. This removes the messy stacked-InnerBlocks
canvas. The front-end appearance, interactivity, and styling are unchanged.

## Why

The current editor renders every slide stacked in the canvas (all InnerBlocks
visible at once), which gets cluttered fast. Authors prefer managing slides in the
sidebar with a true WYSIWYG preview. The cost — accepted — is that slide panels can
no longer hold arbitrary blocks; each slide is a fixed set of fields.

## Scope decision: clean replacement, no migration

The slider is brand-new and unreleased; no production content uses the InnerBlocks
version. So this is a **clean replacement**:

- `pediment/slider` is rebuilt as a single self-contained block.
- The `pediment/slide` child block and `src/blocks/slide/` are **deleted**.
- **No deprecation / no migration code.** Any pre-existing InnerBlocks-based slider
  instance would show WordPress's "unexpected content" notice (there are none in
  real content).

## Data model

`pediment/slider` attributes:

```
mediaPosition: "left" | "right"   // kept — image side (default "left")
panelColor:    string             // kept — panel background (default "#0A1B33")
slides: array  // default []      // NEW — ordered list of slide objects
```

Each slide object:

```
{
  mediaId:    number,  // attachment id (0 = none → placeholder)
  altOverride:string,  // optional alt text override
  eyebrow:    string,  // small kicker above the heading
  heading:    string,  // slide title
  body:       string,  // multiline plain text (line breaks preserved)
  buttonText: string,  // optional CTA label
  buttonUrl:  string   // optional CTA url
}
```

- `slides` is a block attribute of `type: "array"` (default `[]`).
- `save` returns `null` — the block is fully server-rendered from attributes
  (matches `mega-menu`).
- Body is **plain multiline text**, rendered with line breaks. No inline rich
  formatting in the sidebar (the accepted cost of sidebar editing). The button
  renders only when **both** `buttonText` and `buttonUrl` are non-empty.

## Editor (`slider/edit.tsx`)

Split mirrors `mega-menu`: canvas = display preview, sidebar = all editing.

### Canvas — React preview (NOT ServerSideRender)

Renders the real slider card for the currently-active slide, reproducing
`render.php`'s DOM/classes so the shipped stylesheet styles it:

- `.starter-slider.is-media-{left|right}` wrapper with `--slide-panel-bg` /
  `--slide-panel-fg` set inline from `panelColor` (fg computed by the same
  luminance rule as the front end, reused via a shared TS helper — see below).
- `.starter-slider__track` → one `.starter-slide` for the **active** slide only:
  - `.starter-slide__media`: image from the selected media's URL
    (`useSelect` → `core` `getMedia(mediaId)`), or the SVG placeholder.
  - `.starter-slide__panel`: eyebrow / `<h2>` heading / body paragraphs / optional
    button — same elements/classes as `render.php`.
- `.starter-slider__arrow--prev/--next` and `.starter-slider__pagination` dots are
  **clickable**, wired to a local React `activeIndex` state (editor-only; clamped /
  wrap-around). This previews each slide without the front-end Interactivity runtime
  (which does not execute in the editor).
- A small "Slide {active+1} / {count}" affordance.
- Empty state (no slides): a placeholder prompting "Add your first slide".

### Sidebar (`InspectorControls`)

- **Layout `PanelBody`:** image-side `ToggleControl` (→ `mediaPosition`) + panel
  color `PanelColorSettings` (→ `panelColor`). Kept from today.
- **One `PanelBody` per slide** titled "Slide N":
  - Image: `MediaUpload` (thumbnail + Replace / Remove), `altOverride` `TextControl`.
  - `eyebrow`, `heading` `TextControl`s; `body` `TextareaControl`; `buttonText`,
    `buttonUrl` `TextControl`s.
  - **↑ / ↓ / Remove** buttons using the `move()` + `filter` helpers from
    `mega-menu/edit.tsx`.
  - The most-recently-added slide's `PanelBody` opens automatically
    (`initialOpen` + an `autoOpenIndex` state, as in `mega-menu`).
- **"Add slide"** button (`PanelBody` at the bottom): appends an empty slide, opens
  its panel, and sets it active in the canvas.

State updates are synchronous (`setAttributes`), so the canvas re-renders in place
with no async/SSR lag.

### Shared luminance helper

The light/dark panel-text decision currently lives only in PHP
(`pediment_slider_panel_fg`). To keep the editor preview's text contrast identical,
extract a tiny TS equivalent (hex → light/dark CSS-var token) in a shared module
(e.g. `src/blocks/slider/panel-fg.ts`) used by `edit.tsx`. The PHP helper remains the
front-end source of truth; the TS copy mirrors its threshold/coefficients (documented
as a deliberate parallel, kept in sync).

## Front end (`slider/render.php`)

Mostly a change of loop source:

- Read `$slides = $attributes['slides']` (array). Slide count = `count($slides)`.
- For each slide, build the same `.starter-slide` markup as today:
  - `figure.starter-slide__media`: `wp_get_attachment_image($mediaId, 'large', …)`
    or the SVG placeholder.
  - `.starter-slide__panel`: `eyebrow` → `.starter-slide__eyebrow` (when set);
    `heading` → `<h2 class="starter-slide__heading">`; `body` → a single
    `<p class="starter-slide__body">` rendered as `nl2br( esc_html( $body ) )`
    (line breaks preserved, no markup); optional
    `<a class="starter-slide__button">` when both button fields are set.
  - All output escaped (`esc_html` / `esc_url` / `esc_attr`); no `wp_kses_post` on
    plain-text fields.
- Wrapper (`is-media-*`, `--slide-panel-bg/-fg`), arrows, dots (`$count > 1`), live
  region, and all `data-wp-*` Interactivity hooks are **unchanged**.
- `pediment_slider_panel_fg()` luminance helper — unchanged.

Unchanged front-end pieces: `view.ts` (Interactivity store), `.starter-slider` card /
arrows / dots styling, `is-media-*` order rules, progressive enhancement
(`is-enhanced`), responsive collapse. New small styles for `.starter-slide__eyebrow`
and `.starter-slide__button` using theme tokens only (no color literals — the
`lint:colors` + phpcs `NoColorLiteral` rules still bind, including PHP).

## Deletions

- `src/blocks/slide/` (block.json, edit.tsx, index.tsx, save.tsx, render.php,
  style.scss) — removed.
- `build/blocks/slide/` — removed on rebuild.
- The `.starter-slide` *layout* CSS (grid, media, panel, eyebrow, button,
  placeholder, `is-media-*` order, responsive) moves into the slider block's
  `style.scss` (it no longer has its own block). Class names stay `starter-slide*`
  so the front-end markup/styles are unchanged.

## Testing

- **PHPUnit `SliderTest`** (rewritten): render with a `slides` JSON attribute (no
  nested blocks). Assert:
  - N slides → N `.starter-slide__panel` and N dots; arrows gated on `$count > 1`.
  - eyebrow / heading / body render; eyebrow omitted when empty.
  - button renders only when both `buttonText` and `buttonUrl` set; omitted otherwise.
  - image (`wp_get_attachment_image`) vs SVG placeholder by `mediaId`.
  - `mediaPosition` → `is-media-left/right`; `panelColor` → `--slide-panel-bg`;
    luminance → correct `--slide-panel-fg` token (kept).
  - Drop all old nested-`pediment/slide` cases.
- **e2e `slider.spec.ts`**: construct the slider via a `slides` attribute in block
  markup; keep nav / dots / keyboard / wrap, panel-color, and image-side **layout**
  (media-vs-panel x-position) assertions.
- **Kitchen-sink `editor-blocks.spec.ts`**: update the slider entry to the new
  attribute-based markup.
- Gates: `npm run build`, `lint:blocks`, `lint:colors`, `lint:js`, `composer lint`
  (phpcs, incl. `NoColorLiteral` on PHP), `SliderTest`, `npm run e2e -- slider
  editor-blocks`.

## Out of scope (unchanged)

Autoplay, multiple slides visible at once, per-slide color/position overrides,
thumbnail nav, touch/swipe.
