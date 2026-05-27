# Custom Logo (header + footer)

**Date:** 2026-05-21
**Status:** Draft

## Goal

Let site owners display a wide (non-square) logo image in the header and footer
via the WordPress-native `custom-logo` theme feature, replacing the current
`starter/brand-mark` icon + `core/site-title` text pair.

## Why not Site Icon

Site Icon is square by design (favicon / PWA touch icon). A wider-than-tall
brand mark won't fit without crop. WordPress's purpose-built mechanism for a
header logo image is the `custom-logo` feature combined with the
`core/site-logo` block.

## Architecture

Five changes:

1. **Theme support** — register `custom-logo` with `flex-width` and
   `flex-height` so the media picker accepts any aspect ratio.

2. **Header markup** — replace the `.brand` group's
   `wp:starter/brand-mark` + `wp:site-title` pair in `parts/header.html` with a
   single `wp:site-logo` block.

3. **Footer markup** — same replacement in `parts/footer.html`.

4. **Remove the brand-mark block** — delete `src/blocks/brand-mark/` entirely.
   Auto-registration in `inc/register-blocks.php` scans `build/blocks/*`, so
   the next build drops the registration cleanly. (The
   `starter_icon( …, 'brand-mark' )` reference in `tests/phpunit/IconsTest.php`
   uses `brand-mark` as a CSS class name on a generic icon — unrelated to the
   block; nothing to change.)

5. **Demo seed** — extend `inc/seed.php` to sideload a wide demo SVG logo
   (Pediment bank glyph + wordmark) and call
   `set_theme_mod( 'custom_logo', $id )` so fresh dev installs show a logo
   immediately. Mirrors the existing `starter_seed_demo_image()` pattern with
   a `_starter_seed_demo_logo` marker for idempotency / cleanup.

## File-level details

### `functions.php`

Inside the existing `after_setup_theme` action callback (currently registers
`add_editor_style`):

```php
add_theme_support( 'custom-logo', array(
    'flex-width'  => true,
    'flex-height' => true,
    'header-text' => array( 'site-title', 'site-description' ),
) );
```

`header-text` is the conventional list of class names WordPress hides when a
custom logo is set on classic themes. For block themes it's mostly a hint and
costs nothing — keep it for completeness.

### `parts/header.html`

Current:

```html
<!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group brand">
  <!-- wp:starter/brand-mark /-->
  <!-- wp:site-title {"level":0,"style":{"typography":{"fontWeight":"800","textDecoration":"none","fontSize":"1.2rem","letterSpacing":"-0.02em"}}} /-->
</div>
<!-- /wp:group -->
```

Replace with:

```html
<!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group brand">
  <!-- wp:site-logo {"width":200} /-->
</div>
<!-- /wp:group -->
```

`width: 200` is a starting value for the rendered logo width in the header
(tunable in the Site Editor per template part — not a hard constraint). The
site-logo block falls back to the site title text when no logo is uploaded,
so this remains friendly to fresh installs even before the seed runs.

### `parts/footer.html`

Current `.brand` group:

```html
<!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group brand">
  <!-- wp:starter/brand-mark /-->
  <!-- wp:site-title {"level":0,"style":{"typography":{"fontWeight":"800","textDecoration":"none","fontSize":"1.2rem"}}} /-->
</div>
<!-- /wp:group -->
```

Replace with:

```html
<!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group brand">
  <!-- wp:site-logo {"width":180} /-->
</div>
<!-- /wp:group -->
```

The tagline paragraph and social-links group below remain untouched.

### Deleting the brand-mark block

Remove the directory `src/blocks/brand-mark/` (block.json, edit.tsx,
index.tsx, render.php). `wp-scripts build` does **not** clean stale output —
webpack only emits files for entries it currently sees — so any working
checkout that previously built the block will still have
`build/blocks/brand-mark/` on disk, and `starter_register_blocks()` would
keep registering it. `build/` is gitignored, so the fix is per-developer:
`rm -rf build/blocks/brand-mark/` locally (and document it in the plan's
"after the merge" notes). CI builds from a clean tree and won't see the
ghost.

No other code references the block; no migration needed (the only consumers
are the header and footer template parts, which we're updating in the same
change).

### Demo seed asset

Add `docs/images/logo-demo.svg` — a wide SVG (~400×96) authored as part of
this change, showing the Pediment bank glyph next to a "Pediment" wordmark.
The glyph reuses the same Phosphor "bank" path that lives in
`src/blocks/brand-mark/render.php` today, so the demo logo visually matches
the icon being retired. The SVG should:

- Use `viewBox="0 0 400 96"` so it scales cleanly.
- Use `fill="currentColor"` for the glyph so the seeded logo respects color
  context.
- Inline the wordmark as `<text>` with a generic stack
  (`-apple-system, system-ui, sans-serif`). Exact font-match isn't required —
  this is a placeholder dev logo a site owner replaces with their real brand
  asset.

### Seed function

Add to `inc/seed.php`:

```php
/**
 * Idempotently sideload the demo wide logo and set it as the site's
 * Custom Logo. Mirrors starter_seed_demo_image().
 */
function starter_seed_demo_logo(): int {
    $existing = get_posts( array(
        'post_type'   => 'attachment',
        'post_status' => 'inherit',
        'numberposts' => 1,
        'fields'      => 'ids',
        'meta_key'    => '_starter_seed_demo_logo',
        'meta_value'  => '1',
    ) );
    if ( ! empty( $existing ) ) {
        $id = (int) $existing[0];
        if ( (int) get_theme_mod( 'custom_logo', 0 ) !== $id ) {
            set_theme_mod( 'custom_logo', $id );
        }
        return $id;
    }

    $src = get_template_directory() . '/docs/images/logo-demo.svg';
    if ( ! file_exists( $src ) ) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $uploads = wp_upload_dir();
    if ( ! empty( $uploads['error'] ) ) {
        return 0;
    }
    $filename = wp_unique_filename( $uploads['path'], basename( $src ) );
    $dest     = trailingslashit( $uploads['path'] ) . $filename;
    if ( ! @copy( $src, $dest ) ) {
        return 0;
    }

    $attach_id = wp_insert_attachment( array(
        'post_mime_type' => 'image/svg+xml',
        'post_title'     => 'Demo logo (Pediment)',
        'post_content'   => '',
        'post_status'    => 'inherit',
    ), $dest );
    if ( is_wp_error( $attach_id ) || ! $attach_id ) {
        @unlink( $dest );
        return 0;
    }

    update_post_meta( (int) $attach_id, '_starter_seed_demo_logo', '1' );
    set_theme_mod( 'custom_logo', (int) $attach_id );

    return (int) $attach_id;
}
```

Note: this bypasses `wp_check_filetype()` because WordPress disallows SVG
uploads by default. The file is copied directly into `uploads/` and the
attachment is created with an explicit `image/svg+xml` mime. SVG attachments
don't generate sub-sizes, so `wp_generate_attachment_metadata` is intentionally
omitted (it would be a no-op for SVG and could noisily warn).

Call `starter_seed_demo_logo()` from `starter_seed_run()` after the existing
`starter_seed_demo_image()` call.

## Open scope / what's not in this change

- No styling overhaul of `.brand` (existing flex layout works for one child as
  well as it did for two).
- No "logo position / size" customizer UI — the site-logo block exposes width
  in the Site Editor; that's enough.
- No theme.json work — site-logo respects block-level width attribute set in
  the template part.
- No frontend tests for the rendered logo (Playwright doesn't have a
  meaningful assertion here beyond "image exists"; left to manual smoke
  after merge).

## Risk / rollback

Three failure modes worth naming:

1. **Site-logo block fallback** — when no `custom_logo` is set, the block
   renders the site title as a fallback. Confirmed in core. Means we don't
   regress fresh installs even before the seed runs.

2. **SVG mime rejection** — if a future WP hardening change blocks attaching
   `image/svg+xml`, the seed silently no-ops (returns 0). The header still
   works via fallback. To mitigate, the seed could fall back to a PNG; not
   worth doing pre-emptively.

3. **brand-mark deletion is irreversible in the build** — but it's reversible
   in git. If the change needs to be reverted, restore the `src/blocks/brand-mark/`
   directory and the two template parts.

## Verification

- `composer phpcs` clean on touched PHP.
- `pnpm build` succeeds; `build/blocks/brand-mark/` is gone.
- Open the home page in the existing wp-env: header and footer show the
  seeded wide logo (no brand-mark icon + Pediment text pair).
- Site Editor → header template part → site-logo block → "Replace" lets the
  user upload a different image. Aspect ratio not constrained.
- Removing the seeded attachment + reloading falls back to site title text.
