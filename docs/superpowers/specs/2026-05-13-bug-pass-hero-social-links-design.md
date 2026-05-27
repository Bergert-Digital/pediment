# Bug pass: hero `split` variant + social-links rendering

**Status:** Draft
**Date:** 2026-05-13
**Owner:** Jonas Bergert
**Scope:** `wp-starter-theme` (the parent theme). No changes to `wp-client-template` or `wp-starter-ai`.

## Context

A code-level audit of `wp-starter-theme` (the parent theme distributed to all
client sites as Composer package `bergert/wp-starter-theme`) surfaced three
findings that contradict what the theme's editor UI promises. Two of the three
are confirmed defects; the third is uncertain and deferred to a follow-up spec
pending verification.

This spec addresses the two confirmed defects as a single "bug pass" slice:

- **Hero `split` variant** — the `starter/hero` block's `block.json` declares
  four variants (`default`, `split`, `centered`, `media-bg`) and the
  `edit.tsx` SelectControl exposes all four. But `render.php` emits identical
  markup for the first three; only `media-bg` has a special branch (it sets
  the wrapper's background-image inline style). The `split` variant has no
  way to produce a two-column layout: there is no second content slot, no
  image picker beyond the `media-bg` case, and no `.is-variant-split` CSS
  rules. A user picking "Split" gets a default-style hero — the editor lies
  to them.

- **Footer social links** — `Brand::get('social_links')` stores brand-wide
  social links via the Brand Settings admin screen, but `parts/footer.html`
  is a static block-theme template part with no way to call PHP, so the data
  layer and the presentation layer are disconnected. Every client site
  silently drops the social links that an admin configures.

The deferred third finding is whether `faq-item.answer` and `pull-quote.quote`
silently lose `RichText`-typed markup. WP's `RichText` returns HTML strings
that flow into `type: "string"` attributes; `render.php` outputs them via
`wp_kses_post()` which preserves the standard formatting tags. On code inspection
this should round-trip correctly. Verifying in the editor is one Playwright run
or one manual click-through; deferring keeps the present spec focused.

## Goals

1. The hero's editor UI matches the rendered output — `Split` no longer appears
   as a selectable variant since it doesn't produce a split layout.
2. Brand-configured social links render on every client site that has any
   configured, in a visually coherent way that fits the theme's "well-crafted"
   aim.
3. Both fixes ship together as one PR so the bug-pass slice closes cleanly.
4. The change set is small enough for one implementation session, large enough
   to be worth its own brainstorm + spec.

## Non-goals

- No fix or modernisation of the RichText source on `faq-item` / `pull-quote`.
  Pending verification; separate spec if confirmed.
- No re-design of the hero block, no new variants, no new attributes. Removal
  of `split` only.
- No re-design of the footer. One new row gets added to `parts/footer.html`
  containing the new block, with minimal spacing changes. Title / copyright
  row stays as-is.
- No back-compat shim for content saved with `variant: "split"`. Existing
  content keeps rendering identically (since the saved variant was indistinct
  from `default`) and editor users land on `Default` on next edit.
- No fix for the broader audit findings: unstyled core blocks
  (`styles.blocks.core/*`), bare `front-page.html`, hardcoded English in
  `404.html`, missing post-author / categories in `single.html`, `image`
  size hardcoded in `image-caption`, button styling duplicated by `hero` /
  `cta`, social-link-config UX. All separate specs.

## Approach

Two independent fixes, one spec, one PR. They share no code but they share the
same "things this theme advertises but doesn't actually do" framing, and the
combined PR is small enough to review together.

**Fix A** is a removal: drop `split` from the variant enum and the editor
SelectControl. Three of four variants remain. No new code; this is a deletion
+ description edit.

**Fix B** is an addition: a new server-side block `starter/social-links` that
reads `Brand::get('social_links')` and renders a list of `<a>` elements with
inline SVG icons for known platforms (with a text-label fallback for unknown
platforms). The block has zero editor attributes — Brand Settings is the
single source of truth. Drop it into `parts/footer.html` as a new centred row.

## Components

### File-by-file changes

| File | Action | Notes |
| --- | --- | --- |
| `src/blocks/hero/block.json` | Edit | Remove `split` from `attributes.variant.enum`; update `description` to drop "split" mention |
| `src/blocks/hero/edit.tsx` | Edit | Remove `{ label: 'Split', value: 'split' }` from the SelectControl options |
| `src/blocks/social-links/block.json` | New | Block metadata, zero attributes, server-rendered |
| `src/blocks/social-links/edit.tsx` | New | `ServerSideRender` preview + empty-state placeholder |
| `src/blocks/social-links/render.php` | New | Reads `Brand::get('social_links')`; inline icon library + text-label fallback |
| `src/blocks/social-links/style.scss` | New | Frontend + editor styles using design tokens |
| `parts/footer.html` | Edit | Add second row inside `<footer>` group containing the new block, centred |
| `tests/php/blocks/HeroVariantTest.php` | New | Two assertions on `block.json` shape |
| `tests/php/blocks/SocialLinksTest.php` | New | Eight assertions covering render behaviour |

No changes to:

- `theme.json`
- `inc/register-blocks.php` (assuming current auto-registration scans
  `src/blocks/<name>/block.json` — plan task verifies)
- Any existing block other than `hero`
- Build config (`package.json`, `tsconfig.json`)

### Hero changes

`src/blocks/hero/block.json` — change two fields:

```diff
-  "description": "A page-opening hero with headline, subheadline, and primary CTA. Variants: default, split, centered, media-bg.",
+  "description": "A page-opening hero with headline, subheadline, and primary CTA. Variants: default, centered, media-bg.",
   "attributes": {
     "variant": {
       "type": "string",
       "default": "default",
-      "enum": [ "default", "split", "centered", "media-bg" ]
+      "enum": [ "default", "centered", "media-bg" ]
     },
```

`src/blocks/hero/edit.tsx` — drop one entry from `options`:

```diff
   options={ [
     { label: 'Default', value: 'default' },
-    { label: 'Split', value: 'split' },
     { label: 'Centered', value: 'centered' },
     { label: 'Media BG', value: 'media-bg' },
   ] }
```

`src/blocks/hero/render.php` — no change. The existing render path produces
identical output for `default`, `centered`, and `split`; only `media-bg`
branches.

### Social-links block — `block.json`

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "starter/social-links",
  "title": "Social Links",
  "category": "starter",
  "description": "Renders the social links configured in Settings → Brand Settings. Hides itself when none are configured.",
  "textdomain": "starter",
  "supports": { "html": false, "align": [ "wide" ] },
  "attributes": {},
  "editorScript": "file:./index.js",
  "style": "file:./style-index.css",
  "render": "file:./render.php"
}
```

No `attributes` block — the design intentionally has no per-instance state.
All data flows from `Brand::get('social_links')`. `align: ["wide"]` is exposed
in case a client wants to centre it across a wide column; `full` is excluded
(a row of icons rarely benefits from edge-to-edge).

### Social-links block — `edit.tsx`

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { Placeholder } from '@wordpress/components';

export default function Edit() {
  const blockProps = useBlockProps();
  return (
    <div { ...blockProps }>
      <ServerSideRender
        block="starter/social-links"
        EmptyResponsePlaceholder={ () => (
          <Placeholder
            label={ __( 'Social links', 'starter' ) }
            instructions={ __(
              'No social links configured. Add them under Settings → Brand Settings → Social.',
              'starter'
            ) }
          />
        ) }
      />
    </div>
  );
}
```

The placeholder fires only when the SSR returns an empty string, which is
exactly the "no `social_links` configured" path. Editor users who drop the
block in fresh see actionable guidance; users with configured links see the
final rendered output.

### Social-links block — `render.php`

Skeleton:

```php
<?php
/**
 * Server-side render for starter/social-links.
 */

if ( ! class_exists( '\\Starter\\Brand' ) ) {
  return '';
}

$links = (array) \Starter\Brand::get( 'social_links', array() );
$links = is_array( $links ) ? $links : array();

if ( empty( $links ) ) {
  return '';
}

$icons  = starter_social_links_icons();
$labels = starter_social_links_labels();

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-social-links' ) );

ob_start();
?>
<ul <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?> role="list">
  <?php foreach ( $links as $link ) :
    $platform = isset( $link['platform'] ) ? (string) $link['platform'] : '';
    $url      = isset( $link['url'] )      ? (string) $link['url']      : '';
    if ( '' === $platform || '' === $url ) {
      continue;
    }

    $icon  = $icons[ $platform ]  ?? '';
    $label = $labels[ $platform ] ?? ucfirst( $platform );
  ?>
    <li class="starter-social-links__item">
      <a href="<?php echo esc_url( $url ); ?>"
         aria-label="<?php echo esc_attr( $label ); ?>"
         rel="noopener noreferrer">
        <?php if ( '' !== $icon ) : ?>
          <span class="starter-social-links__icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <?php else : ?>
          <span class="starter-social-links__label"><?php echo esc_html( $label ); ?></span>
        <?php endif; ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>
<?php
return ob_get_clean();
```

Helper functions `starter_social_links_icons()` and
`starter_social_links_labels()` live at the bottom of the same file. Each icon
is a single-path SVG string sourced from
[Simple Icons](https://simpleicons.org/) (CC0-licensed brand icon library) and
trimmed to the inner `<path>` plus a wrapper `<svg viewBox="0 0 24 24"
xmlns="http://www.w3.org/2000/svg">…</svg>`. Total bundle ≈ 5 KB for the
initial nine entries.

### Icon coverage (initial set)

| platform key | label | aliases |
| --- | --- | --- |
| `twitter` | "Twitter" | `x` (same icon) |
| `x` | "X" | — |
| `github` | "GitHub" | — |
| `linkedin` | "LinkedIn" | — |
| `instagram` | "Instagram" | — |
| `facebook` | "Facebook" | — |
| `youtube` | "YouTube" | — |
| `mastodon` | "Mastodon" | — |
| `rss` | "RSS" | — |

Unknown platform — anything not in this map — renders as a styled text pill
displaying `ucfirst($platform)`. Adding a new platform after release is a
one-PR addition to two arrays (icons + labels); the architectural surface is
already there.

### Social-links block — `style.scss`

```scss
.starter-social-links {
  display: flex;
  gap: var(--wp--preset--spacing--20);
  list-style: none;
  padding: 0;
  margin: 0;
  justify-content: center;

  &__item { display: inline-block; }

  a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    color: var(--wp--preset--color--text-muted);
    border-radius: 9999px;
    text-decoration: none;
    transition: color 0.15s ease;

    &:hover,
    &:focus-visible {
      color: var(--wp--preset--color--accent);
    }

    &:focus-visible {
      outline: 2px solid var(--wp--preset--color--accent);
      outline-offset: 2px;
    }
  }

  &__icon svg {
    width: 1.125rem;
    height: 1.125rem;
    display: block;
    fill: currentColor;
  }

  &__label {
    font-size: var(--wp--preset--font-size--xs);
    padding: 0 var(--wp--preset--spacing--10);
    color: var(--wp--preset--color--text-muted);
  }
}
```

All token-able values use theme tokens. The `2rem` icon-button size, `1.125rem`
SVG size, and `9999px` pill radius are local layout magic numbers and stay
local — promoting them to theme tokens would only matter if a second icon
block emerges, which is out of scope here.

### Footer template — `parts/footer.html`

Insert a new flex group as a *sibling* of the existing inner group (title +
copyright row), inside the outer `<footer>` group:

```html
<!-- wp:group {"tagName":"footer","backgroundColor":"surface-elevated", … } -->
<footer class="wp-block-group … ">

  <!-- existing inner group: title + copyright row — UNCHANGED -->
  <!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
  …
  <!-- /wp:group -->

  <!-- NEW: social-links row -->
  <!-- wp:group {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}}} -->
  <div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--30);justify-content:center">
    <!-- wp:starter/social-links /-->
  </div>
  <!-- /wp:group -->

</footer>
<!-- /wp:group -->
```

The wrapping group is what centres the row horizontally and adds vertical
spacing from the title/copyright row above. The social-links block itself is
layout-agnostic — clients who drop it elsewhere inherit centring from the
parent flex group, not from the block.

## Tests

`wp-starter-theme` uses PHPUnit (per `phpunit.xml.dist`). New test files
mirror the existing `tests/php/` layout — plan task confirms the exact
location before writing.

**`tests/php/blocks/HeroVariantTest.php`** — schema-only assertions, no WP
boot:

1. `test_block_json_variant_enum_excludes_split`: parses
   `src/blocks/hero/block.json`, asserts
   `attributes.variant.enum === ['default', 'centered', 'media-bg']`.
2. `test_block_json_description_no_longer_mentions_split`: asserts the
   `description` field does not contain the word `"split"` (case-insensitive).

**`tests/php/blocks/SocialLinksTest.php`** — integration tests requiring WP
boot (the renderer reads `Brand::get`):

1. `test_returns_empty_string_when_brand_social_links_is_empty`
2. `test_renders_one_anchor_per_configured_link`
3. `test_known_platform_renders_inline_svg_icon_with_aria_hidden_span`
4. `test_unknown_platform_renders_text_label_fallback_with_ucfirst`
5. `test_twitter_and_x_aliases_both_render_the_same_x_icon`
6. `test_skips_entries_with_empty_platform_or_url`
7. `test_each_anchor_has_rel_noopener_noreferrer`
8. `test_each_anchor_has_aria_label_matching_platform`

Each test sets `Brand::set('social_links', […])`, calls
`render_block( ['blockName' => 'starter/social-links'] )`, and asserts on the
returned HTML.

**Not in scope for automated tests:**

- Visual regression (Playwright). The Playwright config exists in the repo
  but extending it here would inflate the spec.
- Keyboard navigation order. Covered by the manual smoke checklist.
- Screen reader output ordering. Covered by the manual smoke checklist.

## Migration / back-compat

**Hero**:

- Pages with `variant: "split"` saved in block content keep rendering
  identically — `render.php` produced the same markup for `split` and
  `default` before the change, and the wrapper class `.is-variant-split` was
  never styled. Visual output is byte-identical on next page load.
- Editor users reopening such content see the SelectControl land on `Default`
  (no longer a `split` option). No data loss; documented in the v0.1.2
  release commit message and considered acceptable since the user never got
  a split layout anyway.

**Social-links block**: new block. No prior content references it. No
migration step.

**Theme version**: bump from `0.1.1` → `0.1.2` per the project's existing
semver discipline (bugfix + small additive feature, no breaking changes).
Plan task updates `style.css` `Version:` header and any other version
declaration.

## Verification

Manual smoke checklist, run after implementation, before opening the PR for
review:

1. `npm run env:start` boots wp-env without errors.
2. **Hero variant UI**: open Site Editor → any template with a hero, select
   the hero block, sidebar `Hero settings → Variant` Select shows three
   options: Default, Centered, Media BG. No "Split".
3. **Hero render**: front-end view of an existing hero with
   `variant: "default"` looks identical to the pre-fix screenshot — no
   visual regression.
4. **Social-links empty state (front-end)**: with `social_links` empty in
   Settings → Brand Settings, visit any page; footer shows only the existing
   title+copyright row; no extra `<ul class="starter-social-links">` in
   page source.
5. **Social-links empty state (editor)**: insert `starter/social-links` into
   a page; editor shows the `Placeholder` instructing the user to add links
   in Settings → Brand Settings.
6. **Social-links populated**: Settings → Brand Settings → Social → add at
   least three rows mixing known and unknown platforms (`twitter`, `github`,
   `bluesky`) with valid URLs → Save.
7. Reload front-end; footer shows a second row, centred, with the configured
   links. Known platforms render SVG icons; `bluesky` renders as a styled
   text pill ("Bluesky").
8. Inspect HTML: each `<a>` has `rel="noopener noreferrer"` and an
   `aria-label` (e.g., `aria-label="Twitter"`). The `<svg>` is wrapped in a
   `<span class="starter-social-links__icon" aria-hidden="true">`.
9. **Alias coverage**: change one entry to `platform=x` and reload; same X
   icon renders as the `twitter` entry.
10. **Keyboard nav**: Tab through the footer; each social link is focusable
    in DOM order, focus ring visible (2px accent outline + 2px offset).
11. `composer test` (or `vendor/bin/phpunit`) — both new test files pass.
    Existing test suite stays green.
12. `npm run env:stop` when done.

## Open questions

None. Scope (two findings, not three), implementation surface (one new block,
two file edits in hero), data source (Brand Settings only), visual treatment
(inline SVG + text fallback), and footer placement (new centred row) are all
locked.

## Out of scope / future work

- Verify and (if confirmed) fix the RichText source on `faq-item` and
  `pull-quote`.
- Style core Gutenberg blocks (`styles.blocks.core/*`) for image, quote,
  pullquote, table, separator, columns, group, list. The "well-crafted" pass.
- New blocks: testimonial, feature-grid, logo-cloud, footer-with-link-columns,
  team grid, pricing, breadcrumbs, newsletter.
- Rewrite `front-page.html` from a scaffold into an opinionated landing
  layout that exercises the existing library.
- Enrich `single.html` (post author, categories/tags, related posts).
- i18n cleanup in `404.html`.
- Refactor `image-caption` block to expose image-size control (or
  rationalise its existence vs `core/image`).
- De-duplicate button styling between `cta` / `hero` and the global `button`
  element.
- Read `contact_email` from `Brand::get` in the `contact-page.php` pattern.

Each of the above is its own brainstorming pass and spec.
