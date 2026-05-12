# Adding a `client/*` block

Client themes (child themes of `starter-theme`) live in a separate repo. They follow the same block contract as starter blocks, with two differences: namespace and registration path.

## Folder layout in the child theme

```
client-theme/
  blocks/
    promo-banner/
      block.json
      index.tsx
      edit.tsx
      render.php
      style.scss
  functions.php
  theme.json
  style.css
```

## Register from the child theme

In `client-theme/functions.php`:

```php
add_action( 'init', function () {
    foreach ( glob( __DIR__ . '/blocks/*', GLOB_ONLYDIR ) as $block_dir ) {
        if ( file_exists( $block_dir . '/block.json' ) ) {
            register_block_type( $block_dir );
        }
    }
} );
```

## Namespace

Use `client/<name>` for the block's `name` in `block.json`. This keeps client and starter blocks visually separable in the inserter and prevents collisions if the starter adds a block of the same name later.

```json
{ "name": "client/promo-banner" }
```

## AI-aware convention

For the AI plugin (Plan B) to compose with your client block, the same rules from `docs/blocks.md` apply:

- **`description` is required.**
- **`attributes` must be declared explicitly** with type + default.

The AI plugin discovers your block at runtime via `WP_Block_Type_Registry`. No additional registration is required on the plugin side.

## Theming

Override `theme.json` in your child theme. Brand color and typography overrides go in `settings.color.palette` and `settings.typography.fontFamilies`. WP merges parent + child automatically; you only declare what changes.

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 2,
  "settings": {
    "color": {
      "palette": [
        { "slug": "primary", "color": "#1A2238", "name": "Primary" },
        { "slug": "accent",  "color": "#FF6A3D", "name": "Accent" }
      ]
    }
  }
}
```

That's the only place where hex values are allowed.

## Don't edit starter files

The parent theme is installed by Composer at `web/app/themes/starter-theme/`. Treat it as read-only. Anything you need to change must be done via:

- A child-theme override (templates, parts, theme.json values).
- A new `client/*` block.
- A small client-side plugin (rare; not needed for v1).

If you find yourself wanting to modify a starter block, open an issue / PR upstream instead — that's how the starter improves for every client.
