# Blog Landing — Design

**Date:** 2026-05-20
**Scope:** Blog landing page only (the listing at `/blog/`). Single-post, archive, search, and category templates are out of scope.

## Goal

Give the theme a properly designed blog landing page that fits the Pediment design system (banded full-bleed layout, Insight card grid, themed pagination), backed by the WordPress main query so URLs like `/blog/page/2/` work natively.

## Background

Current state:

- `starter/blog-index` block renders a 3-column Insight card grid (image, category badge, date, title, excerpt, optional client-side filter). It runs its own bounded `WP_Query` and is intended for embedding on marketing pages — it's used as a band inside `patterns/pediment-landing.php`.
- The blog templates (`templates/index.html`, `templates/archive.html`, `templates/single.html`) are bare-bones `wp:query` markup with no styling, no header, no Pediment treatment.
- There is no `templates/home.html`. The seeder already creates a `Blog` page and sets it as Reading → Posts page (`page_for_posts`), but with `index.html` as the only available template, `/blog/` currently renders unstyled.
- `inc/block-styles.php` already registers band styles (`band-surface`, `band-elevated`, `band-navy`) on `core/group`. Theme-level CSS lives in `assets/css/theme.css`.

## Design

### Architecture

Three changes, one new file:

| File | Change |
| --- | --- |
| `templates/home.html` | **new** — banded blog landing (heading band + paginated Insight grid band) |
| `inc/block-styles.php` | register `is-style-insights-grid` block style on `core/query` |
| `assets/css/theme.css` | add shared Insight card visuals + pagination styles |
| `src/blocks/blog-index/style.scss` | strip card visuals (now in `theme.css`); keep only `.starter-blog-index`-rooted layout rules (grid template, filter row, root class) |
| `inc/seed.php` | clear the `Blog` page's seeded `post_content` (currently `wp:starter/blog-index`, made redundant by `home.html`) |

Out of scope, explicitly: `templates/index.html`, `archive.html`, `single.html`, `search.html`, category/tag templates, single-post styling, related-posts, author pages.

### `templates/home.html` structure

Three full-bleed bands stacked without gaps (Pediment convention: outer groups are `align:full` + `starter-band`, with `margin:0` and `layout:constrained`):

```
header template-part
  ├ Band 1: group .starter-band is-style-band-surface (white)
  │   • paragraph .kicker (centered): "INSIGHTS"
  │   • heading level:1 (centered):   "Latest thinking from the team"
  │   • paragraph .lead (centered):   short one-line intro
  │
  ├ Band 2: group .starter-band (default white, no band-style variant)
  │   • core/query
  │       attrs: align:wide, queryId:0, query.inherit:true, className:"is-style-insights-grid"
  │       ├ core/post-template (layout:{type:grid, columnCount:3})
  │       │     ├ core/group .starter-insight-card__media
  │       │     │     ├ core/post-featured-image (isLink:true, aspectRatio:"16/11")
  │       │     │     └ core/post-terms {term:"category"}   ← becomes the badge via CSS
  │       │     └ core/group .starter-insight-card__body (layout:{type:flex, orientation:vertical})
  │       │           ├ core/post-date
  │       │           ├ core/post-title (isLink:true, level:3)
  │       │           ├ core/post-excerpt (moreText:"", showMoreOnNewLine:false)
  │       │           └ core/read-more (content:"Read more →")
  │       ├ core/query-no-results
  │       │     └ paragraph: "No posts yet."
  │       └ core/query-pagination (paginationArrow:"arrow")
  │             ├ core/query-pagination-previous
  │             ├ core/query-pagination-numbers
  │             └ core/query-pagination-next
footer template-part
```

Notes:

- `core/query` uses `inherit:true`, so the URL drives the query and `theme.json`'s `postsPerPage` controls page size. `/blog/page/2/` works natively.
- `wp:post-terms {term:"category"}` renders a comma-separated category list. CSS positions it absolutely over the media wrapper as a pill badge. Multi-category posts will show a comma list inside one pill — acceptable for v1; a `starter/primary-category` block could replace it later if needed.
- The heading band's copy is baked into the template. Editable via the Site Editor like other Pediment band copy.

### Block style registration

`inc/block-styles.php` gains one more `register_block_style` call:

```php
register_block_style( 'core/query', array(
    'name'  => 'insights-grid',
    'label' => __( 'Insights grid', 'starter' ),
) );
```

This both adds `is-style-insights-grid` to the query's wrapper class when selected in the editor and surfaces the style as a toggle in the editor's Styles panel. The template hard-codes the className so the editor selection isn't required for `home.html` to render correctly.

### CSS — shared Insight card visuals

`assets/css/theme.css` gains a new section with the card shell, media, badge, body, date, title, excerpt, and "Read more" rules. Rules use grouped selectors so both blocks share one source of truth:

```css
.starter-blog-index__item,
.is-style-insights-grid .wp-block-post-template > li { /* card shell */ }

.starter-blog-index__media,
.is-style-insights-grid .starter-insight-card__media { /* media wrapper */ }

.starter-blog-index__badge,
.is-style-insights-grid .wp-block-post-terms { /* badge pill */ }

/* …__body, __title, __excerpt, __readmore similarly */
```

Existing `is-style-band-navy` overrides (filter button colors on dark band) stay in `src/blocks/blog-index/style.scss` since they only apply to the curated block.

`src/blocks/blog-index/style.scss` is refactored to keep only:

- `.starter-blog-index__filter` (filter row — curated block only)
- `.starter-blog-index__list` (3-column grid template)
- `.starter-blog-index__empty` (empty state)
- The `is-style-band-navy` overrides

…and `@import`s nothing from the new theme.css rules (CSS doesn't need to; the rules co-exist in cascade order).

### Pagination styles

`assets/css/theme.css` also adds:

```css
.is-style-insights-grid .wp-block-query-pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 48px;
}
.is-style-insights-grid .wp-block-query-pagination a,
.is-style-insights-grid .wp-block-query-pagination span {
    /* Pediment pill — uses --wp--preset--color-- tokens; accent on hover / [aria-current=page] */
}
```

### Seeder change

`inc/seed.php`, in the `$pages['blog']` entry: replace

```php
'content' => '<!-- wp:starter/blog-index {"count":10,"align":"wide"} /-->',
```

with

```php
'content' => '', // home.html renders the listing; page content is unused.
```

The `Blog` page row, the `page_for_posts` option, sample categories, and sample posts are all untouched.

### Editor preview

In the Site Editor, `home.html` opens with the band layout visible. The `core/query` inside the grid band shows the standard query-loop preview; with `is-style-insights-grid` applied via className, the editor stylesheet picks up the same `assets/css/theme.css` rules, so cards look the same in the editor as on the front-end.

### Accessibility

- `core/post-title` with `isLink:true` is the primary link — the read-more is decorative (`aria-hidden` not needed since it's still a real link; just ensure it points to the same URL).
- `core/post-featured-image` with `isLink:true` adds a redundant link; set `alt` via the underlying media. The badge (post-terms) inherits link semantics from core, which is fine — terms link to category archives.
- Pagination uses core's `wp:query-pagination`, which ships with `aria-label="Pagination"` and `aria-current="page"` for the active number.

## Testing

### PHPUnit — `tests/phpunit/HomeTemplateTest.php` (new)

Parse `templates/home.html` with `parse_blocks()` and assert:

- Two top-level band groups (heading band, grid band).
- A `core/query` block with `attrs.query.inherit === true` and `attrs.className` contains `is-style-insights-grid`.
- Inner post-template contains: `core/post-featured-image`, `core/post-terms`, `core/post-date`, `core/post-title`, `core/post-excerpt`, `core/read-more`.
- `core/query-pagination` present with three children (previous, numbers, next).
- `core/query-no-results` present.

### PHPUnit — `tests/phpunit/BlockStylesTest.php` (extend if exists, else new)

After `do_action('init')`, assert `is-style-insights-grid` is registered on `core/query` via `WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block('core/query')`.

### Playwright — `tests/e2e/blog-landing.spec.ts` (new)

Visit `/blog/` (after seeder runs in CI). Per memory: Playwright runs against the child-theme env at `localhost:8890` after merging to `development`.

- `h1` with text matching "Latest thinking from the team" is visible.
- At least one `.wp-block-post-template > li` rendered.
- That `<li>` contains a `<a>` whose `href` matches a post permalink and whose text matches the post title.
- The badge (`.wp-block-post-terms` inside the media wrapper) is visible and positioned absolutely (computed `position: absolute`).
- Pagination: if more than 1 page of posts exists (seeder currently creates 9 posts, fits one page of 9; either bump seeder to 10+ posts or assert that pagination is absent when ≤ perPage). Decision: keep seeder at 9, assert pagination *block exists in DOM* but no `next` link when there's only one page.

## Risks & mitigations

- **`wp:post-terms` comma-list looks ugly for multi-category posts.** Acceptable for v1. If it becomes a real problem, introduce a `starter/primary-category` server-rendered block (mirroring the badge logic in `starter/blog-index/render.php` lines 43–49) and swap it in. Not in scope.
- **`theme.json` `postsPerPage` interaction.** `inherit:true` honors the WP setting (`Reading → Posts per page`), not `theme.json`. We won't set `perPage` on the block. Acceptable — admins control posts-per-page via the standard Reading settings.
- **Editor preview mismatch.** Block styles registered in PHP are visible in the editor (`register_block_style` registers in both contexts). The theme stylesheet that defines `is-style-insights-grid` rules must be loaded in the editor — we'll confirm `assets/css/theme.css` is enqueued via `add_editor_style()` in `functions.php` (already the case for the band system; verify during implementation).
- **Read-more "Read more →" arrow.** `core/read-more` doesn't ship with an icon out of the box. We'll set its `content` attr to `Read more →` (with the actual arrow character) to match the existing Insight card affordance. No SVG injection needed.

## What we deliberately rejected

- **No category filter on the landing.** With paginated server-side queries, a JS-only hide/show filter only affects the visible page — confusing. Category badges link to native `/category/<slug>/` archives instead.
- **No featured-post hero band.** Adds template complexity (a separate single-post query at the top) without strong value for v1; the heading band already provides visual entry.
- **No new `starter/post-card` server block.** Core blocks + CSS get us pixel-parity without a new block class. A future server block is a possibility if `wp:post-terms` becomes too limiting.
- **No `templates/index.html` restyling.** Keeps the fallback minimal and the diff focused. `home.html` is the canonical blog-landing template; `index.html` exists only as a deep fallback.
